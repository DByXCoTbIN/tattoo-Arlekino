<?php

declare(strict_types=1);

namespace App;

use PDO;

class GroupChatRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    public function create(string $name, int $creatorId): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("INSERT INTO group_chats (name, creator_id) VALUES (?, ?)")
                ->execute([$name, $creatorId]);
            $groupId = (int) $this->pdo->lastInsertId();
            if ($groupId <= 0) {
                $this->pdo->rollBack();
                return 0;
            }
            $this->addMember($groupId, $creatorId);
            $this->pdo->commit();
            return $groupId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function addMember(int $groupId, int $userId): void
    {
        try {
            $this->pdo->prepare("INSERT INTO group_chat_members (group_id, user_id) VALUES (?, ?)")
                ->execute([$groupId, $userId]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }
    }

    public function removeMember(int $groupId, int $userId): void
    {
        $this->pdo->prepare("DELETE FROM group_chat_members WHERE group_id = ? AND user_id = ?")
            ->execute([$groupId, $userId]);
    }

    public function isMember(int $groupId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $userId]);
        return (bool) $stmt->fetch();
    }

    public function getGroupsForUser(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT g.*, (SELECT body FROM group_chat_messages WHERE group_id = g.id ORDER BY created_at DESC LIMIT 1) AS last_message,
                       (SELECT created_at FROM group_chat_messages WHERE group_id = g.id ORDER BY created_at DESC LIMIT 1) AS last_at
                FROM group_chats g
                JOIN group_chat_members m ON m.group_id = g.id AND m.user_id = ?
                ORDER BY COALESCE((SELECT created_at FROM group_chat_messages WHERE group_id = g.id ORDER BY created_at DESC LIMIT 1), g.created_at) DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function sendMessage(int $groupId, int $senderId, string $body, ?string $mediaPath = null, ?string $mediaType = null): void
    {
        try {
            $this->pdo->prepare("INSERT INTO group_chat_messages (group_id, sender_id, body, media_path, media_type) VALUES (?, ?, ?, ?, ?)")
                ->execute([$groupId, $senderId, $body ?: '', $mediaPath, $mediaType]);
        } catch (\Throwable $e) {
            $this->pdo->prepare("INSERT INTO group_chat_messages (group_id, sender_id, body) VALUES (?, ?, ?)")
                ->execute([$groupId, $senderId, $body ?: '']);
        }
    }

    public function getMessages(int $groupId, int $limit = 100, int $offset = 0): array
    {
        try {
            $limit = max(1, min(500, $limit));
            $offset = max(0, $offset);
            $stmt = $this->pdo->prepare("
                SELECT m.*, u.full_name, u.avatar_path FROM group_chat_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.group_id = ? ORDER BY m.created_at ASC LIMIT " . $limit . " OFFSET " . $offset . "
            ");
            $stmt->execute([$groupId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT g.*, u.full_name AS creator_name FROM group_chats g LEFT JOIN users u ON u.id = g.creator_id WHERE g.id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function findByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') return null;
        try {
            $stmt = $this->pdo->prepare("SELECT g.*, u.full_name AS creator_name FROM group_chats g LEFT JOIN users u ON u.id = g.creator_id WHERE LOWER(g.name) = LOWER(?) LIMIT 1");
            $stmt->execute([$name]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Удалить групповой чат. Может админ (любую) или создатель группы.
     * Участники и сообщения удаляются каскадно.
     */
    public function deleteGroup(int $groupId, int $userId, bool $isAdmin): bool
    {
        $group = $this->findById($groupId);
        if (!$group) return false;
        if (!$isAdmin && (int)($group['creator_id'] ?? 0) !== $userId) {
            return false;
        }
        $this->pdo->prepare("DELETE FROM group_chats WHERE id = ?")->execute([$groupId]);
        return true;
    }
}
