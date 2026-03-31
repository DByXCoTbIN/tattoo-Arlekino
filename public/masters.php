<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\Repositories\UserRepo;
use App\ServiceRepository;
use App\Seo;

Auth::init();
$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);
$user = Auth::user();

$userRepo = new UserRepo();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;
$serviceId = isset($_GET['service']) ? (int)$_GET['service'] : 0;
$sort = trim((string)($_GET['sort'] ?? 'reviews'));
$allowedSorts = ['reviews', 'days', 'reviews_count', 'alphabet'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'reviews';
}
$currentService = null;
if ($serviceId > 0) {
    try {
        $serviceRepo = new ServiceRepository();
        $masters = $serviceRepo->getMastersForService($serviceId, $sort);
        $allServices = $serviceRepo->listAll();
        foreach ($allServices as $s) {
            if ((int)$s['id'] === $serviceId) {
                $currentService = $s;
                break;
            }
        }
    } catch (\Throwable $e) {
        $masters = $userRepo->getMasters($perPage, $offset, $sort);
    }
} else {
    $masters = $userRepo->getMasters($perPage, $offset, $sort);
}

$pageTitle = !empty($currentService['name'])
    ? 'Мастера: ' . $currentService['name']
    : 'Мастера тату-студии — рейтинг и портфолио';
$canonQ = [];
if ($serviceId > 0) {
    $canonQ['service'] = $serviceId;
}
if ($page > 1) {
    $canonQ['page'] = $page;
}
$canonicalUrl = Seo::absoluteUrl('masters.php', $canonQ, $config);
$pageDescription = Seo::metaSnippet(
    !empty($currentService['name'])
        ? 'Мастера студии по услуге «' . $currentService['name'] . '»: рейтинг, отзывы, переход в портфолио.'
        : 'Список тату-мастеров студии: рейтинг по отзывам, специализация и ссылки на работы.'
);
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/masters.php';
require __DIR__ . '/../templates/layout/footer.php';
