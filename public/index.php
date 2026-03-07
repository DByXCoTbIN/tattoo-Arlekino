<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\PostRepository;
use App\Repositories\UserRepo;

Auth::init();
$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);
$user = Auth::user();

$postRepo = new PostRepository();
$userRepo = new UserRepo();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)Settings::get('posts_per_page', '12');
$offset = ($page - 1) * $perPage;
$feed = $postRepo->getFeed($perPage, $offset);
$masters = $userRepo->getMasters(12, 0);
$services = [];
$mapLocations = [];
try {
    $services = (new \App\ServiceRepository())->listAll();
} catch (\Throwable $e) { }
try {
    $mapLocations = (new \App\MapLocationRepository())->getAll();
} catch (\Throwable $e) { }

$pageTitle = 'Главная';
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/home.php';
require __DIR__ . '/../templates/layout/footer.php';
