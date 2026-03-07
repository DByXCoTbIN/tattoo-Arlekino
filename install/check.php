<?php

/**
 * Проверка настроек и подключения к БД.
 * Запуск: php install/check.php
 */

declare(strict_types=1);

$base = dirname(__DIR__);
require $base . '/bootstrap.php';

echo "=== Проверка окружения ===\n";

$config = require $base . '/config/config.php';
$db = $config['db'];
$driver = $db['driver'] ?? 'sqlite';
echo "DB_DRIVER: $driver\n";
if ($driver === 'sqlite') {
    echo "SQLite: " . ($db['sqlite_path'] ?? 'data/circus.db') . "\n";
} else {
    echo "MySQL: host={$db['host']}, db={$db['dbname']}, user={$db['user']}\n";
}

try {
    $pdo = \App\Database::get();
    echo "Подключение к БД: OK\n";
    if (\App\Database::isSqlite()) {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
    } else {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
    }
    echo "Таблицы: " . (count($tables) ? implode(', ', $tables) : 'нет') . "\n";
    if (in_array('users', $tables, true)) {
        $n = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "Пользователей: $n\n";
    }
} catch (Throwable $e) {
    echo "Ошибка БД: " . $e->getMessage() . "\n";
    if (($db['driver'] ?? '') === 'mysql') {
        echo "\nДля MySQL: sudo mysql < install/create-db-user.sql\n";
        echo "Или задайте в .env: DB_DRIVER=sqlite (работает без установки MySQL).\n";
    } else {
        echo "\nЗапустите: php install/install.php\n";
    }
    exit(1);
}

echo "\nГотово. Запуск: php -S 0.0.0.0:8000 -t public\n";
