<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\UserRepo;

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id required']);
    exit;
}

$config = require dirname(__DIR__, 2) . '/config/config.php';
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/' : '/';

$userRepo = new UserRepo();
$master = $userRepo->getMasterProfile($id, true);
if (!$master) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Master not found']);
    exit;
}

echo json_encode([
    'ok' => true,
    'master' => [
        'id' => (int)$master['id'],
        'full_name' => $master['full_name'] ?? '',
        'bio' => $master['bio'] ?? '',
        'specialization' => $master['specialization'] ?? '',
        'rating_avg' => isset($master['rating_avg']) ? (float)$master['rating_avg'] : 0,
        'rating_count' => (int)($master['rating_count'] ?? 0),
        'is_verified' => !empty($master['is_verified']),
        'avatar_path' => !empty($master['avatar_path']) ? $base . ltrim($master['avatar_path'], '/') : null,
        'banner_path' => !empty($master['banner_path']) ? $base . ltrim($master['banner_path'], '/') : null,
    ],
]);
