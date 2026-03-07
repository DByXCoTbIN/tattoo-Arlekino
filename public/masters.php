<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\Repositories\UserRepo;
use App\ServiceRepository;

Auth::init();
$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);
$user = Auth::user();

$userRepo = new UserRepo();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;
$serviceId = isset($_GET['service']) ? (int)$_GET['service'] : 0;
$currentService = null;
if ($serviceId > 0) {
    try {
        $serviceRepo = new ServiceRepository();
        $masters = $serviceRepo->getMastersForService($serviceId);
        $allServices = $serviceRepo->listAll();
        foreach ($allServices as $s) {
            if ((int)$s['id'] === $serviceId) {
                $currentService = $s;
                break;
            }
        }
    } catch (\Throwable $e) {
        $masters = $userRepo->getMasters($perPage, $offset);
    }
} else {
    $masters = $userRepo->getMasters($perPage, $offset);
}

$pageTitle = 'Мастера';
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/masters.php';
require __DIR__ . '/../templates/layout/footer.php';
