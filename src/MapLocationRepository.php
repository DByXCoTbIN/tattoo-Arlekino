<?php

declare(strict_types=1);

namespace App;

use PDO;

class MapLocationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    /** Ссылка на Яндекс.Карты с заданными координатами (для хранения в БД). */
    public static function buildYandexMapUrlFromCoords(float $lat, float $lng): string
    {
        return 'https://yandex.ru/maps/?ll=' . $lng . ',' . $lat . '&z=16&pt=' . $lng . ',' . $lat . ',pm2rdm';
    }

    /**
     * Ссылка допустима для сохранения: Яндекс.Карты или явные координаты.
     */
    public static function isAllowedMapSource(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (preg_match('/^(-?\d+\.?\d*)\s*[,;]\s*(-?\d+\.?\d*)$/', $url)) {
            return true;
        }
        return (bool) preg_match('#https?://([a-z0-9.-]+\.)?yandex\.(ru|com|by|kz)/maps#i', $url);
    }

    /**
     * Извлекает координаты из ссылки Яндекс.Карт или строки "lat,lng" (широта, долгота).
     * Возвращает ['lat' => float, 'lng' => float] или null при ошибке.
     */
    public static function parseCoordinatesFromUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Прямой ввод: 55.7558,37.6173 (широта, долгота)
        if (preg_match('/^(-?\d+\.?\d*)\s*[,;]\s*(-?\d+\.?\d*)$/', $url, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        if (!self::isAllowedMapSource($url)) {
            return null;
        }

        // Яндекс: ll=долгота,широта
        if (preg_match('/[?&]ll=(-?\d+\.?\d*)[,%2C](-?\d+\.?\d*)/', $url, $m)) {
            $lng = (float) $m[1];
            $lat = (float) $m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Яндекс: pt=долгота,широта (возможны хвосты через ~)
        if (preg_match('/[?&]pt=(-?\d+\.?\d*)[,%2C](-?\d+\.?\d*)/', $url, $m)) {
            $lng = (float) $m[1];
            $lat = (float) $m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        return null;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM map_locations ORDER BY sort_order, id");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(string $title, string $address, string $mapUrl, float $lat, float $lng, int $sortOrder = 0): int
    {
        $this->pdo->prepare("INSERT INTO map_locations (title, address, map_url, lat, lng, sort_order) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$title, $address, $mapUrl, $lat, $lng, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $title, string $address, string $mapUrl, float $lat, float $lng, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare("UPDATE map_locations SET title = ?, address = ?, map_url = ?, lat = ?, lng = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([$title, $address, $mapUrl, $lat, $lng, $sortOrder, $id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM map_locations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
