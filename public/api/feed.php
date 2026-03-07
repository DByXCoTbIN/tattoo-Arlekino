<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\PostRepository;

header('Content-Type: application/json; charset=utf-8');

$config = require dirname(__DIR__, 2) . '/config/config.php';
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/' : '/';

$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$postRepo = new PostRepository();
$posts = $postRepo->getFeed($limit, $offset);

$list = array_map(function ($p) use ($base) {
    $media = [];
    foreach ($p['media'] ?? [] as $m) {
        $media[] = [
            'media_type' => $m['media_type'] ?? 'image',
            'file_path' => !empty($m['file_path']) ? $base . ltrim($m['file_path'], '/') : null,
        ];
    }
    return [
        'id' => (int)$p['id'],
        'master_id' => (int)$p['master_id'],
        'full_name' => $p['full_name'] ?? '',
        'avatar_path' => !empty($p['avatar_path']) ? $base . ltrim($p['avatar_path'], '/') : null,
        'content_text' => $p['content_text'] ?? '',
        'created_at' => $p['created_at'] ?? '',
        'media' => $media,
    ];
}, $posts);

echo json_encode(['ok' => true, 'feed' => $list]);
