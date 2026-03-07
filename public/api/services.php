<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\ServiceRepository;

header('Content-Type: application/json; charset=utf-8');

$svcRepo = new ServiceRepository();
$services = $svcRepo->listAll();
$list = array_map(function ($s) {
    return [
        'id' => (int)$s['id'],
        'name' => $s['name'] ?? '',
        'description' => $s['description'] ?? '',
    ];
}, $services);

echo json_encode(['ok' => true, 'services' => $list]);
