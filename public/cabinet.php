<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;

Auth::init();
Auth::requireAuth();
$user = Auth::user();

if (Auth::isMaster()) {
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
    header('Location: ' . $root . 'master.php?id=' . (int)$user['id']);
    exit;
}

// Клиенты не имеют кабинета — fallback на главную
header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
exit;
