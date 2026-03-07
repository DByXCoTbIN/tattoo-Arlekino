<?php
/**
 * Применить миграцию v2. Запуск: php install/migrate_v2.php
 */
$base = dirname(__DIR__);
require $base . '/bootstrap.php';
$config = require $base . '/config/config.php';
$db = $config['db'];
$driver = $db['driver'] ?? 'mysql';
if ($driver !== 'mysql') {
    die("Миграция v2 только для MySQL.\n");
}
try {
    $pdo = \App\Database::get();
} catch (Throwable $e) {
    die("Ошибка БД: " . $e->getMessage() . "\n");
}
$sql = file_get_contents($base . '/database/migration_v2.sql');
$statements = array_filter(preg_split('/;\s*[\r\n]+/', $sql));
foreach ($statements as $s) {
    $s = trim($s);
    if ($s === '' || strpos($s, '--') === 0) continue;
    try {
        $pdo->exec($s);
        echo "OK: " . substr($s, 0, 60) . "...\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "Skip (already exists): " . substr($s, 0, 50) . "\n";
        } else {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
echo "Миграция завершена.\n";
