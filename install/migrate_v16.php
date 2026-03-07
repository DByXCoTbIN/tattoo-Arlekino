<?php
/**
 * Миграция v16: чат поддержки — отдельный на каждого пользователя (support_user_chats)
 * Запуск: php install/migrate_v16.php
 */
$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap.php';
$config = require $base . '/config/config.php';
$driver = $config['db']['driver'] ?? 'sqlite';
$pdo = \App\Database::get();
$isSqlite = $driver === 'sqlite';
$sqlFile = $isSqlite ? $base . '/database/migration_v16_support_per_user.sqlite.sql' : $base . '/database/migration_v16_support_per_user.sql';
$sql = file_get_contents($sqlFile);
$statements = array_filter(preg_split('/;\s*[\r\n]+/', $sql));
foreach ($statements as $s) {
    $s = trim($s);
    if ($s === '' || strpos($s, '--') === 0) continue;
    if (substr($s, -1) !== ';') $s .= ';';
    try {
        $pdo->exec($s);
        echo "OK: " . substr(preg_replace('/\s+/', ' ', $s), 0, 80) . "\n";
    } catch (\PDOException $e) {
        $msg = $e->getMessage();
        if (preg_match('/Duplicate|already exists|duplicate column|UNIQUE constraint|duplicate key/i', $msg)) {
            echo "Skip: " . substr($msg, 0, 70) . "\n";
        } else {
            echo "Error: " . $msg . "\n";
        }
    }
}
echo "Миграция v16 завершена.\n";
