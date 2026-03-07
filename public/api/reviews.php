<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\RatingRepository;
use App\Repositories\UserRepo;

header('Content-Type: application/json; charset=utf-8');

$masterId = (int)($_GET['master_id'] ?? $_GET['id'] ?? 0);
if ($masterId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'master_id required']);
    exit;
}

$config = require dirname(__DIR__, 2) . '/config/config.php';
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/' : '/';

$userRepo = new UserRepo();
$master = $userRepo->getMasterProfile($masterId, true);
if (!$master) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Master not found']);
    exit;
}

$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$ratingRepo = new RatingRepository();
$reviews = $ratingRepo->getForMaster($masterId, $limit, true);

$list = array_map(function ($r) use ($base) {
    return [
        'id' => (int)$r['id'],
        'value' => (int)($r['value'] ?? 0),
        'comment' => $r['comment'] ?? '',
        'created_at' => $r['created_at'] ?? '',
        'client_name' => $r['full_name'] ?? 'Гость',
        'avatar_path' => !empty($r['avatar_path']) ? $base . ltrim($r['avatar_path'], '/') : null,
    ];
}, $reviews);

echo json_encode([
    'ok' => true,
    'master' => [
        'id' => (int)$master['id'],
        'full_name' => $master['full_name'] ?? '',
        'rating_avg' => isset($master['rating_avg']) ? (float)$master['rating_avg'] : 0,
        'rating_count' => (int)($master['rating_count'] ?? 0),
    ],
    'reviews' => $list,
]);
