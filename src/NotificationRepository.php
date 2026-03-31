<?php

declare(strict_types=1);

namespace App;

use PDO;

class NotificationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    /**
     * Добавить уведомление.
     * @param int|null $targetUserId Кому: null = для всех админов, иначе ID пользователя
     */
    public function add(?int $targetUserId, string $type, ?int $refId, ?int $fromUserId, ?string $data = null): void
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO notifications (user_id, type, ref_id, from_user_id, data) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$targetUserId, $type, $refId, $fromUserId, $data]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'user_id') !== false || strpos($e->getMessage(), 'column') !== false) {
                $stmt = $this->pdo->prepare("INSERT INTO notifications (type, ref_id, from_user_id, data) VALUES (?, ?, ?, ?)");
                $stmt->execute([$type, $refId, $fromUserId, $data]);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Список непрочитанных уведомлений для пользователя.
     * @param bool $isAdmin Если true, показывать и уведомления с user_id IS NULL (для админов)
     */
    public function getForUser(int $userId, bool $isAdmin, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "SELECT n.*, u.full_name AS from_name, u.email AS from_email
                FROM notifications n
                LEFT JOIN users u ON u.id = n.from_user_id
                WHERE n.is_read = 0 AND (n.user_id = ? OR (n.user_id IS NULL AND ?))
                ORDER BY n.created_at DESC LIMIT " . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $isAdmin ? 1 : 0]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnreadForUser(int $userId, bool $isAdmin): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (user_id = ? OR (user_id IS NULL AND ?))");
        $stmt->execute([$userId, $isAdmin ? 1 : 0]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Отметить уведомление прочитанным (только если оно предназначено этому пользователю).
     */
    public function markReadForUser(int $id, int $userId, bool $isAdmin): bool
    {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR (user_id IS NULL AND ?))");
        $stmt->execute([$id, $userId, $isAdmin ? 1 : 0]);
        return $stmt->rowCount() > 0;
    }

    /** @deprecated Используйте add(null, $type, ...) для админских уведомлений */
    public function getUnread(?string $type = null, int $limit = 50): array
    {
        $sql = "SELECT n.*, u.full_name, u.email FROM notifications n LEFT JOIN users u ON u.id = n.from_user_id WHERE n.is_read = 0";
        if ($type !== null) {
            $sql .= " AND n.type = ?";
        }
        $sql .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit;
        $stmt = $type !== null ? $this->pdo->prepare($sql) : $this->pdo->query($sql);
        if ($type !== null) $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    public function markRead(int $id): void
    {
        $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
    }

    public function countUnread(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
    }

    /**
     * Есть ли уже напоминание по этой записи (чтобы не дублировать).
     */
    public function hasReminderForBooking(int $masterId, int $bookingId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM notifications WHERE user_id = ? AND type = 'booking_reminder' AND ref_id = ? LIMIT 1");
        $stmt->execute([$masterId, $bookingId]);
        return (bool) $stmt->fetch();
    }
}
