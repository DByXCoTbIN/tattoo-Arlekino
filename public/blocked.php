<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;

Auth::init();
if (!Auth::isBanned()) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
    exit;
}

$bannedUser = Auth::bannedUser();
$config = require dirname(__DIR__) . '/config/config.php';
$siteName = \App\Settings::get('site_name', $config['site']['name']);
$pageTitle = 'Аккаунт заблокирован';
$bodyClass = 'blocked-page';
$user = $bannedUser;
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/blocked.php';
require __DIR__ . '/../templates/layout/footer.php';
