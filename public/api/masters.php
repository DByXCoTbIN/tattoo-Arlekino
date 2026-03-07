<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\UserRepo;
use App\ServiceRepository;

header('Content-Type: application/json; charset=utf-8');

$config = require dirname(__DIR__, 2) . '/config/config.php';
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/' : '/';
$uploadUrl = $config['site']['upload_url'] ?? '/uploads';

$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$serviceId = (int)($_GET['service_id'] ?? 0);

$userRepo = new UserRepo();
if ($serviceId > 0) {
    try {
        $svcRepo = new ServiceRepository();
        $all = $svcRepo->getMastersForService($serviceId);
        $masters = array_slice($all, $offset, $limit);
    } catch (\Throwable $e) {
        $masters = $userRepo->getMasters($limit, $offset);
    }
} else {
    $masters = $userRepo->getMasters($limit, $offset);
}

$list = array_map(function ($m) use ($base) {
    return [
        'id' => (int)$m['id'],
        'full_name' => $m['full_name'] ?? '',
        'bio' => $m['bio'] ?? '',
        'specialization' => $m['specialization'] ?? '',
        'rating_avg' => isset($m['rating_avg']) ? (float)$m['rating_avg'] : 0,
        'rating_count' => (int)($m['rating_count'] ?? 0),
        'is_verified' => !empty($m['is_verified']),
        'avatar_path' => !empty($m['avatar_path']) ? $base . ltrim($m['avatar_path'], '/') : null,
    ];
}, $masters);

echo json_encode(['ok' => true, 'masters' => $list]);
