<?php

declare(strict_types=1);

namespace App;

use PDO;

class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                $stmt = Database::get()->query("SELECT * FROM settings");
                while ($row = $stmt->fetch()) {
                    self::$cache[$row['key']] = $row['value'];
                }
            } catch (\Throwable $e) {
                return $default;
            }
        }
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        $pdo = Database::get();
        if (Database::isSqlite()) {
            $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
        } else {
            $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$key, $value]);
        }
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }
}
