<?php

/**
 * Установка БД и первого админа.
 * Запуск: php install/install.php
 * По умолчанию используется SQLite (работает без MySQL). Для MySQL задайте в .env: DB_DRIVER=mysql
 * Пароль админа: admin123
 */

declare(strict_types=1);

$base = dirname(__DIR__);
require $base . '/bootstrap.php';

$config = require $base . '/config/config.php';
$db = $config['db'];
$driver = $db['driver'] ?? 'sqlite';

$adminEmail = 'admin@circus.local';
$adminPass = 'admin123';
$hash = password_hash($adminPass, PASSWORD_DEFAULT);

if ($driver === 'sqlite' && extension_loaded('pdo_sqlite')) {
    $path = $db['sqlite_path'] ?? $base . '/data/circus.db';
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    echo "Используется SQLite: $path\n";
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    echo "Создание таблиц...\n";
    $sql = file_get_contents($base . '/database/schema.sqlite.sql');
    $pdo->exec($sql);
} else {
    echo "Подключение к MySQL...\n";
    try {
        $dsn = "mysql:host={$db['host']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        echo "Ошибка MySQL: " . $e->getMessage() . "\n\n";
        echo "Создайте пользователя БД. В терминале выполните:\n\n  sudo mysql < " . realpath($base . '/install/create-db-user.sql') . "\n\n";
        echo "Или откройте: sudo mysql  и вставьте:\n\n";
        echo "  CREATE DATABASE IF NOT EXISTS circus_social CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
        echo "  DROP USER IF EXISTS 'circus'@'localhost'; DROP USER IF EXISTS 'circus'@'127.0.0.1';\n";
        echo "  CREATE USER 'circus'@'localhost' IDENTIFIED BY 'admin123';\n";
        echo "  CREATE USER 'circus'@'127.0.0.1' IDENTIFIED BY 'admin123';\n";
        echo "  GRANT ALL ON circus_social.* TO 'circus'@'localhost';\n";
        echo "  GRANT ALL ON circus_social.* TO 'circus'@'127.0.0.1';\n";
        echo "  FLUSH PRIVILEGES;\n\n";
        echo "В .env должно быть: DB_PASS=admin123 и DB_HOST=127.0.0.1\n";
        exit(1);
    }
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['dbname']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db['dbname']}`");

    echo "Создание таблиц...\n";
    $schema = file_get_contents($base . '/database/schema.sql');
    $schema = preg_replace('/--[^\n]*\n/', "\n", $schema);
    $statements = preg_split('/;\s*[\r\n]+/', $schema);
    foreach ($statements as $sql) {
        $sql = trim($sql);
        if ($sql === '') continue;
        if (preg_match('/^(CREATE DATABASE|USE\s)/i', $sql)) continue;
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'already exists') !== false || strpos($msg, 'Duplicate') !== false) continue;
            echo "Ошибка SQL: " . $msg . "\n";
            throw $e;
        }
    }
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$adminEmail]);
if ($stmt->fetch()) {
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $adminEmail]);
    echo "Пароль админа обновлён.\n";
} else {
    $pdo->prepare("INSERT INTO users (email, password_hash, role, full_name) VALUES (?, ?, 'admin', 'Админ-Мастер')")
        ->execute([$adminEmail, $hash]);
    echo "Создан админ: $adminEmail / $adminPass\n";
}

echo "Готово. Запуск: php -S 0.0.0.0:8000 -t public\n";
echo "Сайт: http://localhost:8000\n";