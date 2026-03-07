<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\RatingRepository;
use App\Repositories\UserRepo;

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

$master = $userRepo->getMasterProfile($masterId, true);
if (!$master) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
    exit;
}

$ratingRepo->recalcMasterRating($masterId);
$master = $userRepo->getMasterProfile($masterId, true);

$ratings = $ratingRepo->getForMaster($masterId, 100, true);
$isOwnProfile = $user && (int)$user['id'] === $masterId;

$pageTitle = 'Отзывы — ' . $master['full_name'];
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/reviews.php';
require __DIR__ . '/../templates/layout/footer.php';
