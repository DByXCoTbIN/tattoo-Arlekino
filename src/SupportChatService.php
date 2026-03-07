<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Чат поддержки: у каждого пользователя свой чат (он + все админы).
 * Пользователи между собой не пересекаются. Любой админ может писать в любой чат поддержки.
 */
class SupportChatService
{
    private const CHAT_NAME_PREFIX = 'Поддержка';
    private const SETTINGS_KEY_LEGACY = 'support_chat_id';

    private GroupChatRepository $groupRepo;
    private PDO $pdo;

    public function __construct()
    {
        $this->groupRepo = new GroupChatRepository();
        $this->pdo = Database::get();
    }

    /** Есть ли таблица support_user_chats (миграция v16) */
    public function hasPerUserSupport(): bool
    {
        try {
            if (Database::isSqlite()) {
                $stmt = $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='support_user_chats'");
            } else {
                $stmt = $this->pdo->query("SHOW TABLES LIKE 'support_user_chats'");
            }
            return $stmt && $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Получить или создать чат поддержки для пользователя.
     * В чате: сам пользователь + все админы.
     */
    public function getOrCreateSupportChatForUser(int $userId): ?int
    {
        if (!$this->hasPerUserSupport()) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT group_id FROM support_user_chats WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $groupId = (int) $row['group_id'];
            $this->syncAdminsToGroup($groupId);
            return $groupId;
        }
        $userRow = $this->pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $userRow->execute([$userId]);
        $userName = $userRow->fetchColumn() ?: 'Пользователь';
        $name = self::CHAT_NAME_PREFIX . ': ' . mb_substr($userName, 0, 50);
        $creatorId = $this->getFirstAdminId();
        $groupId = $this->groupRepo->create($name, $creatorId);
        if ($groupId <= 0) {
            return null;
        }
        $this->groupRepo->addMember($groupId, $userId);
        $this->syncAdminsToGroup($groupId);
        try {
            $this->pdo->prepare("INSERT INTO support_user_chats (user_id, group_id) VALUES (?, ?)")->execute([$userId, $groupId]);
        } catch (\Throwable $e) {
            $this->groupRepo->removeMember($groupId, $userId);
            return null;
        }
        return $groupId;
    }

    /** Является ли группа чатом поддержки (per-user или legacy общий). */
    public function isSupportChat(int $groupId): bool
    {
        if ($this->hasPerUserSupport()) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM support_user_chats WHERE group_id = ?");
            $stmt->execute([$groupId]);
            return (bool) $stmt->fetch();
        }
        $legacyId = $this->getLegacySupportChatId();
        return $legacyId !== null && $legacyId === $groupId;
    }

    /** user_id владельца чата поддержки по group_id. */
    public function getUserIdBySupportGroupId(int $groupId): ?int
    {
        if (!$this->hasPerUserSupport()) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT user_id FROM support_user_chats WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['user_id'] : null;
    }

    /** Добавить всех админов в группу (если ещё не участники). */
    public function syncAdminsToGroup(int $groupId): void
    {
        $stmt = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_banned = 0");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $adminId = (int) $row['id'];
            if (!$this->groupRepo->isMember($groupId, $adminId)) {
                $this->groupRepo->addMember($groupId, $adminId);
            }
        }
    }

    /** Убедиться, что текущий админ в группе (для входа в любой support-чат). */
    public function ensureAdminInSupportChat(int $groupId, int $adminId): void
    {
        if (!$this->isSupportChat($groupId)) {
            return;
        }
        if (!$this->groupRepo->isMember($groupId, $adminId)) {
            $this->groupRepo->addMember($groupId, $adminId);
        }
    }

    /** Список всех чатов поддержки для админа: [ user_id, group_id, user_name, last_at ]. */
    public function getSupportThreadsForAdmin(): array
    {
        if (!$this->hasPerUserSupport()) {
            return [];
        }
        $sql = "
            SELECT s.user_id, s.group_id, u.full_name AS user_name,
                   (SELECT created_at FROM group_chat_messages WHERE group_id = s.group_id ORDER BY created_at DESC LIMIT 1) AS last_at
            FROM support_user_chats s
            JOIN users u ON u.id = s.user_id
            ORDER BY last_at DESC, s.created_at DESC
        ";
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getFirstAdminId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_banned = 0 ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int) $row['id'];
        }
        $stmt = $this->pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : 1;
    }

    /** ID общего чата поддержки (когда миграция v16 не применена). */
    public function getLegacySupportChatId(): ?int
    {
        $id = Settings::get(self::SETTINGS_KEY_LEGACY, null);
        if ($id === null || $id === '') return null;
        $id = (int) $id;
        if ($id <= 0) return null;
        $group = $this->groupRepo->findById($id);
        return $group ? $id : null;
    }

    /**
     * Создать или получить общий чат поддержки (fallback, если нет таблицы support_user_chats).
     * Все пользователи попадают в один чат с админами.
     */
    public function getOrCreateLegacySupportChat(): int
    {
        $id = $this->getLegacySupportChatId();
        if ($id !== null) {
            $this->syncAdminsToGroup($id);
            return $id;
        }
        $creatorId = $this->getFirstAdminId();
        $id = $this->groupRepo->create(self::CHAT_NAME_PREFIX, $creatorId);
        if ($id > 0) {
            Settings::set(self::SETTINGS_KEY_LEGACY, (string) $id);
            $this->syncAdminsToGroup($id);
        }
        return $id > 0 ? $id : 0;
    }
}
