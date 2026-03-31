<?php
/**
 * Миграция v9: записи к мастерам (календарь броней)
 * Запуск: php install/migrate_v9.php
 */
$base = dirname(__DIR__);
chdir($base);
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
$sqlFile = $isSqlite ? $base . '/database/migration_v9_bookings.sqlite.sql' : $base . '/database/migration_v9_bookings.sql';
$sql = file_get_contents($sqlFile);
$sql = trim(preg_replace('/^\s*--[^\n]*$/m', '', $sql));
$sql = trim($sql);

if ($isSqlite) {
    try {
        $pdo->exec($sql);
        echo "OK: master_schedule, bookings\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (preg_match('/Duplicate|already exists|duplicate column/i', $msg)) {
            echo "Skip (already exists)\n";
        } else {
            echo "Error: $msg\n";
            exit(1);
        }
    }
} else {
    $parts = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));
    foreach ($parts as $stmt) {
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt . ';');
            echo "OK: " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 60) . "...\n";
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (preg_match('/Duplicate|already exists|duplicate column/i', $msg)) {
                echo "Skip (exists)\n";
            } else {
                echo "Error: $msg\n";
                exit(1);
            }
        }
    }
}
echo "Миграция v9 завершена.\n";
