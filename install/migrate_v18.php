<?php
/**
 * Миграция v18: выходные в расписании, скрытие профиля, override админа
 * Запуск: php install/migrate_v18.php
 */
$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap.php';
$config = require $base . '/config/config.php';
$driver = $config['db']['driver'] ?? 'sqlite';
$pdo = \App\Database::get();
$isSqlite = $driver === 'sqlite';
$sqlFile = $isSqlite ? $base . '/database/migration_v18_master_off_visibility.sqlite.sql' : $base . '/database/migration_v18_master_off_visibility.sql';
$sql = file_get_contents($sqlFile);
$statements = array_filter(preg_split('/;\s*[\r\n]+/', $sql));
foreach ($statements as $s) {
    $s = trim($s);
    if ($s === '' || strpos($s, '--') === 0) {
        continue;
    }
    if (substr($s, -1) !== ';') {
        $s .= ';';
    }
    try {
        $pdo->exec($s);
        echo "OK: " . substr(preg_replace('/\s+/', ' ', $s), 0, 80) . "\n";
    } catch (\PDOException $e) {
        $msg = $e->getMessage();
        if (preg_match('/Duplicate|already exists|duplicate column/i', $msg)) {
            echo "Skip: " . substr($msg, 0, 70) . "\n";
        } else {
            echo "Error: " . $msg . "\n";
        }
    }
}
echo "Миграция v18 завершена.\n";
