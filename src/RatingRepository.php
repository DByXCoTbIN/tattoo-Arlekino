<?php

declare(strict_types=1);

namespace App;

use PDO;

class RatingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    public function setRating(int $masterId, int $clientId, int $value, string $comment = ''): void
    {
        $value = max(1, min(5, $value));
        if (\App\Database::isSqlite()) {
            $this->pdo->prepare("INSERT INTO ratings (master_id, client_id, value, comment) VALUES (?, ?, ?, ?) ON CONFLICT(master_id, client_id) DO UPDATE SET value = excluded.value, comment = excluded.comment")->execute([$masterId, $clientId, $value, $comment]);
        } else {
            try {
                $this->pdo->prepare("INSERT INTO ratings (master_id, client_id, value, comment, status) VALUES (?, ?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE value = VALUES(value), comment = VALUES(comment), status = 'pending'")->execute([$masterId, $clientId, $value, $comment]);
                try {
                    (new NotificationRepository())->add(null, 'rating_pending', $masterId, $clientId, json_encode(['value' => $value, 'comment_preview' => mb_substr($comment, 0, 80)]));
                } catch (\Throwable $e) { }
            } catch (\Throwable $e) {
                $this->pdo->prepare("INSERT INTO ratings (master_id, client_id, value, comment) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), comment = VALUES(comment)")->execute([$masterId, $clientId, $value, $comment]);
            }
        }
        $this->recalcMasterRating($masterId);
    }

    public function recalcMasterRating(int $masterId): void
    {
        try {
            $stmt = $this->pdo->prepare("SELECT SUM(value) AS s, COUNT(*) AS c FROM ratings WHERE master_id = ? AND (status = 'approved' OR admin_restored = 1)");
            $stmt->execute([$masterId]);
        } catch (\Throwable $e) {
            $stmt = $this->pdo->prepare("SELECT SUM(value) AS s, COUNT(*) AS c FROM ratings WHERE master_id = ?");
            $stmt->execute([$masterId]);
        }
        $row = $stmt->fetch();
        $sum = $row ? (int)($row['s'] ?? 0) : 0;
        $cnt = $row ? (int)($row['c'] ?? 0) : 0;

        if (Database::isSqlite()) {
            $this->pdo->prepare("INSERT INTO master_profiles (user_id, rating_sum, rating_count) VALUES (?, ?, ?) ON CONFLICT(user_id) DO UPDATE SET rating_sum = excluded.rating_sum, rating_count = excluded.rating_count")
                ->execute([$masterId, $sum, $cnt]);
        } else {
            $this->pdo->prepare("INSERT INTO master_profiles (user_id, rating_sum, rating_count) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating_sum = VALUES(rating_sum), rating_count = VALUES(rating_count)")
                ->execute([$masterId, $sum, $cnt]);
        }
    }

    public function approveRating(int $ratingId, int $masterId): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE ratings SET status = 'approved', approved_at = NOW() WHERE id = ? AND master_id = ?");
            $stmt->execute([$ratingId, $masterId]);
            if ($stmt->rowCount() > 0) {
                $this->recalcMasterRating($masterId);
                return true;
            }
        } catch (\Throwable $e) { }
        return false;
    }

    public function rejectRating(int $ratingId, int $masterId): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE ratings SET status = 'rejected' WHERE id = ? AND master_id = ?");
            $stmt->execute([$ratingId, $masterId]);
            if ($stmt->rowCount() > 0) {
                $this->recalcMasterRating($masterId);
                return true;
            }
        } catch (\Throwable $e) { }
        return false;
    }

    public function getByClient(int $masterId, int $clientId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ratings WHERE master_id = ? AND client_id = ?");
        $stmt->execute([$masterId, $clientId]);
        return $stmt->fetch() ?: null;
    }

    public function getForMaster(int $masterId, int $limit = 50, bool $approvedOnly = true): array
    {
        $limit = max(1, min(200, $limit));
        $statusFilter = '';
        try {
            if ($approvedOnly) {
                $stmt = $this->pdo->prepare("
                    SELECT r.*, u.full_name, u.avatar_path FROM ratings r
                    JOIN users u ON u.id = r.client_id
                    WHERE r.master_id = ? AND (r.status = 'approved' OR r.admin_restored = 1) ORDER BY r.created_at DESC LIMIT " . $limit . "
                ");
                $stmt->execute([$masterId]);
                return $stmt->fetchAll();
            }
        } catch (\Throwable $e) { }
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.full_name, u.avatar_path FROM ratings r
            JOIN users u ON u.id = r.client_id
            WHERE r.master_id = ? ORDER BY r.created_at DESC LIMIT " . $limit . "
        ");
        $stmt->execute([$masterId]);
        return $stmt->fetchAll();
    }

    public function getAllForAdmin(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.*, m.full_name AS master_name, c.full_name AS client_name
                FROM ratings r
                JOIN users m ON m.id = r.master_id
                JOIN users c ON c.id = r.client_id
                ORDER BY r.created_at DESC LIMIT " . $limit . "
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function adminRestore(int $ratingId): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE ratings SET status = 'approved', admin_restored = 1 WHERE id = ?");
            $stmt->execute([$ratingId]);
            if ($stmt->rowCount() > 0) {
                $sel = $this->pdo->prepare("SELECT master_id FROM ratings WHERE id = ?");
                $sel->execute([$ratingId]);
                $row = $sel->fetch();
                if ($row) {
                    $this->recalcMasterRating((int)$row['master_id']);
                }
                return true;
            }
        } catch (\Throwable $e) { }
        return false;
    }
}
