<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\RatingRepository;
use App\Repositories\UserRepo;
use App\Seo;

Auth::init();
$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);
$user = Auth::user();

$masterId = (int)($_GET['id'] ?? 0);
if (!$masterId) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
    exit;
}

$userRepo = new UserRepo();
$ratingRepo = new RatingRepository();

$master = $userRepo->getMasterProfile($masterId, false);
if (!$master) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
    exit;
}

$isOwnProfile = $user && (int)$user['id'] === $masterId;
if (!$isOwnProfile && !Auth::isAdmin()) {
    if (($master['role'] ?? '') === 'master' && empty($master['is_verified'])) {
        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
        exit;
    }
    if (!UserRepo::isEffectiveProfilePublic($master)) {
        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
        exit;
    }
}

$ratingRepo->recalcMasterRating($masterId);
$master = $userRepo->getMasterProfile($masterId, false);

$ratings = $ratingRepo->getForMaster($masterId, 100, true);

$pageTitle = 'Отзывы о мастере ' . $master['full_name'];
$canonicalUrl = Seo::absoluteUrl('reviews.php', ['id' => $masterId], $config);
$avg = (float) ($master['rating_avg'] ?? 0);
$cnt = (int) ($master['rating_count'] ?? 0);
$pageDescription = Seo::metaSnippet(
    'Отзывы клиентов о ' . $master['full_name'] . ($cnt > 0 ? ': ★' . $avg . ', ' . $cnt . ' оценок.' : '.')
);
$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $pageTitle,
    'url' => $canonicalUrl,
    'about' => [
        '@type' => 'Person',
        'name' => $master['full_name'],
    ],
];
if ($cnt > 0 && $avg > 0) {
    $structuredData['about']['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => $avg,
        'reviewCount' => $cnt,
        'bestRating' => 5,
        'worstRating' => 1,
    ];
}
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/reviews.php';
require __DIR__ . '/../templates/layout/footer.php';
