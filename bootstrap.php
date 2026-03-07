<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $base = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $base . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

date_default_timezone_set(
    (require __DIR__ . '/config/config.php')['site']['timezone'] ?? 'UTC'
);

if (!defined('BASE_PATH')) {
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    if (str_ends_with($scriptDir, '/admin')) {
        $scriptDir = dirname($scriptDir);
    }
    define('BASE_PATH', $scriptDir === '' ? '' : $scriptDir);
}

// Полифилл mbstring для систем без расширения (рекомендуется: sudo apt install php-mbstring)
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $str, ?string $encoding = null): string {
        return strtoupper($str);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $str, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $str, ?string $encoding = null): int {
        return strlen($str);
    }
}
