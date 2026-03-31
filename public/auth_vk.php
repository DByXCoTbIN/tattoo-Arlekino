<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\OAuthService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';

// Временно отключено по запросу.
header('Location: ' . $root . 'login.php?error=oauth_disabled');
exit;

$redirectUri = OAuthService::buildAbsoluteUrl($base . '/auth_vk_callback.php');
$state = bin2hex(random_bytes(16));
$_SESSION['vk_oauth_state'] = $state;

$url = OAuthService::getVkAuthUrl($redirectUri, $state);
if (!$url) {
    header('Location: ' . $root . 'login.php?error=oauth_disabled');
    exit;
}
header('Location: ' . $url);
exit;
