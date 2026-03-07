<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/../config/config.php';
            $c = $config['db'];
            $driver = $c['driver'] ?? 'sqlite';

            if ($driver === 'sqlite' && extension_loaded('pdo_sqlite')) {
                $path = $c['sqlite_path'] ?? __DIR__ . '/../data/circus.db';
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $dsn = 'sqlite:' . $path;
                self::$pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = "mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}";
                self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }
        }
        return self::$pdo;
    }

    public static function isSqlite(): bool
    {
        self::get();
        return self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
