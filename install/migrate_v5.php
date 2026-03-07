<?php
/**
 * Применить миграцию v5 (медиа в сообщениях, групповые чаты, typing).
 * Запуск: php install/migrate_v5.php
 */
$base = dirname(__DIR__);
require $base . '/bootstrap.php';
$config = require $base . '/config/config.php';
$db = $config['db'];
$driver = $db['driver'] ?? 'sqlite';
try {
    $pdo = \App\Database::get();
} catch (Throwable $e) {
    die("Ошибка БД: " . $e->getMessage() . "\n");
}
$isSqlite = $driver === 'sqlite';
$sqlFile = $isSqlite ? $base . '/database/migration_v5_messages_media_groups.sqlite.sql' : $base . '/database/migration_v5_messages_media_groups.sql';
$sql = file_get_contents($sqlFile);

function runStatement($pdo, $sql, $isSqlite) {
    $sql = trim($sql);
    if ($sql === '' || strpos($sql, '--') === 0) return true;
    try {
        $pdo->exec($sql);
        echo "OK: " . substr(preg_replace('/\s+/', ' ', $sql), 0, 70) . "...\n";
        return true;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (preg_match('/Duplicate|already exists|duplicate column/i', $msg)) {
            echo "Skip (exists): " . substr($sql, 0, 50) . "\n";
            return true;
        }
        echo "Error: $msg\n";
        return false;
    }
}

if ($isSqlite) {
    $statements = array_filter(preg_split('/;\s*[\r\n]+/', $sql));
    foreach ($statements as $s) {
        runStatement($pdo, $s, true);
    }
} else {
    $statements = array_filter(preg_split('/;\s*[\r\n]+/', $sql));
    foreach ($statements as $s) {
        runStatement($pdo, $s, false);
    }
    // Проверка: group_chats должна существовать (если members/messages есть, но chats нет)
    try {
        $r = $pdo->query("SHOW TABLES LIKE 'group_chats'")->fetch();
        if (!$r) {
            $pdo->exec("CREATE TABLE group_chats (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, creator_id INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_creator (creator_id)) ENGINE=InnoDB");
            echo "OK: group_chats создана (восстановление)\n";
        }
    } catch (\Throwable $e) { }
}
echo "Миграция v5 завершена.\n";
