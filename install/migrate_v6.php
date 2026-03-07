<?php
/**
 * Применить миграцию v6 (баннер, приветственные фразы, логотип).
 * Запуск: php install/migrate_v6.php
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
$sqlFile = $isSqlite ? $base . '/database/migration_v6_banner_phrases.sqlite.sql' : $base . '/database/migration_v6_banner_phrases.sql';
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

$statements = array_filter(preg_split('/;\s*[\r\n]+/', $sql));
foreach ($statements as $s) {
    $s = trim($s);
    if ($s === '' || strpos($s, '--') === 0) continue;
    if (substr($s, -1) !== ';') $s .= ';';
    runStatement($pdo, $s, $isSqlite);
}
$logoDir = ($config['site']['upload_path'] ?? $base . '/public/uploads') . '/logo';
if (!is_dir($logoDir)) {
    mkdir($logoDir, 0755, true);
    echo "OK: создана папка $logoDir\n";
}
echo "Миграция v6 завершена.\n";
