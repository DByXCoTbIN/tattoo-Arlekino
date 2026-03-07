<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Auth;

header('Content-Type: application/json; charset=utf-8');

Auth::init();
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$config = require dirname(__DIR__, 2) . '/config/config.php';
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/' : '/';
$uploadUrl = $config['site']['upload_url'] ?? '/uploads';

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'] ?? '',
        'email' => $user['email'] ?? '',
        'role' => $user['role'] ?? 'client',
        'avatar_path' => $user['avatar_path'] ? $base . ltrim($user['avatar_path'], '/') : null,
    ],
]);
