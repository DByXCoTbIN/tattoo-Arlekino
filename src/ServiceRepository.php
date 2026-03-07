<?php

declare(strict_types=1);

namespace App;

use PDO;

class ServiceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM services ORDER BY sort_order, id");
        return $stmt->fetchAll();
    }

    public function create(string $name, string $description = ''): int
    {
        $this->pdo->prepare("INSERT INTO services (name, description) VALUES (?, ?)")->execute([$name, $description]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $description): void
    {
        $this->pdo->prepare("UPDATE services SET name = ?, description = ? WHERE id = ?")->execute([$name, $description, $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
    }

    /** Только верифицированные мастера (и админы) по услуге. */
    public function getMastersForService(int $serviceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, mp.bio, mp.rating_sum, mp.rating_count, mp.specialization, mp.is_verified
            FROM master_services ms
            JOIN users u ON u.id = ms.master_id
            LEFT JOIN master_profiles mp ON mp.user_id = u.id
            WHERE ms.service_id = ? AND u.is_banned = 0 AND u.role IN ('master','admin')
              AND (COALESCE(mp.is_verified, 0) = 1 OR u.role = 'admin')
            ORDER BY mp.rating_count DESC
        ");
        $stmt->execute([$serviceId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $cnt = (int)($r['rating_count'] ?? 0);
            $sum = (int)($r['rating_sum'] ?? 0);
            $r['rating_avg'] = $cnt > 0 ? number_format($sum / $cnt, 1) : '0';
        }
        unset($r);
        return $rows;
    }

    public function setMasterServices(int $masterId, array $serviceIds): void
    {
        $this->pdo->prepare("DELETE FROM master_services WHERE master_id = ?")->execute([$masterId]);
        $stmt = $this->pdo->prepare("INSERT INTO master_services (master_id, service_id) VALUES (?, ?)");
        foreach ($serviceIds as $sid) {
            $stmt->execute([$masterId, (int)$sid]);
        }
    }

    public function getMasterServiceIds(int $masterId): array
    {
        $stmt = $this->pdo->prepare("SELECT service_id FROM master_services WHERE master_id = ?");
        $stmt->execute([$masterId]);
        return array_column($stmt->fetchAll(), 'service_id');
    }
}
