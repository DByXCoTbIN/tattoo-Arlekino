<?php

declare(strict_types=1);

namespace App;

use PDO;

class WelcomePhraseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    /** Возвращает фразу для показа на дату $date (или сегодня). Только видимые. Сначала фразы с датами, иначе постоянные. */
    public function getPhraseForDate(?\DateTimeInterface $date = null): ?string
    {
        $date = $date ?? new \DateTimeImmutable();
        $d = $date->format('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT phrase, date_from, date_to FROM welcome_phrases WHERE
                COALESCE(is_visible, 1) = 1
                AND ((date_from IS NULL AND date_to IS NULL)
                    OR (date_from <= ? AND (date_to IS NULL OR date_to >= ?)))
            ORDER BY (date_from IS NOT NULL) DESC, sort_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$d, $d]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? trim($row['phrase']) : null;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM welcome_phrases ORDER BY sort_order, id");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(string $phrase, ?string $dateFrom, ?string $dateTo, int $sortOrder = 0): int
    {
        $this->pdo->prepare("INSERT INTO welcome_phrases (phrase, date_from, date_to, sort_order) VALUES (?, ?, ?, ?)")
            ->execute([$phrase, $dateFrom ?: null, $dateTo ?: null, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $phrase, ?string $dateFrom, ?string $dateTo, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare("UPDATE welcome_phrases SET phrase = ?, date_from = ?, date_to = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$phrase, $dateFrom ?: null, $dateTo ?: null, $sortOrder, $id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM welcome_phrases WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function setVisible(int $id, bool $visible): bool
    {
        $stmt = $this->pdo->prepare("UPDATE welcome_phrases SET is_visible = ? WHERE id = ?");
        $stmt->execute([$visible ? 1 : 0, $id]);
        return $stmt->rowCount() > 0;
    }
}
