<?php
/**
 * Применить миграцию v8 (адреса на карте).
 * Запуск: php install/migrate_v8.php
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
$sqlFile = $isSqlite ? $base . '/database/migration_v8_map_locations.sqlite.sql' : $base . '/database/migration_v8_map_locations.sql';
$sql = file_get_contents($sqlFile);
$sql = trim(preg_replace('/^\s*--[^\n]*$/m', '', $sql));
$sql = trim($sql);

try {
    $pdo->exec($sql);
    echo "OK: map_locations\n";
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (preg_match('/Duplicate|already exists|duplicate column/i', $msg)) {
        echo "Skip (already exists)\n";
    } else {
        echo "Error: $msg\n";
        exit(1);
    }
}
echo "Миграция v8 завершена.\n";
