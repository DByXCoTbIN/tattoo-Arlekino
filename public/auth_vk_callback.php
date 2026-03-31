<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\OAuthService;
use App\Repositories\UserRepo;

Auth::init();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';

// Временно отключено по запросу.
header('Location: ' . $root . 'login.php?error=oauth_disabled');
exit;

$redirectUri = OAuthService::buildAbsoluteUrl($base . '/auth_vk_callback.php');

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: ' . $root . 'login.php?error=no_code');
    exit;
}
$state = $_GET['state'] ?? '';
$expectedState = $_SESSION['vk_oauth_state'] ?? '';
unset($_SESSION['vk_oauth_state']);
if ($state === '' || $expectedState === '' || !hash_equals((string)$expectedState, (string)$state)) {
    header('Location: ' . $root . 'login.php?error=invalid_state');
    exit;
}

$userData = OAuthService::getVkUserData($code, $redirectUri);
if (!$userData) {
    header('Location: ' . $root . 'login.php?error=vk_failed');
    exit;
}

$repo = new UserRepo();
$user = $repo->findByOAuth('vk', $userData['oauth_id']);

if ($user) {
    if ($user['is_banned']) {
        Auth::login((int)$user['id']);
        header('Location: ' . $root . 'blocked.php');
        exit;
    }
    Auth::login((int)$user['id']);
    header('Location: ' . $root);
    exit;
}

$phone = $userData['phone'] ?? null;
$phone = $phone ? preg_replace('/\D/', '', $phone) : '';
if (strlen($phone) >= 10) {
    $phoneFormatted = (strlen($phone) === 10 ? '+7' . $phone : (str_starts_with($phone, '7') ? '+' . $phone : '+7' . substr($phone, -10)));
    if (!$repo->findByPhone($phoneFormatted)) {
        try {
            $userId = $repo->createOAuth('vk', $userData['oauth_id'], $userData['full_name'], $userData['avatar_url'], $phoneFormatted);
        } catch (\Throwable $e) {
            header('Location: ' . $root . 'login.php?error=oauth_not_ready');
            exit;
        }
        Auth::login($userId);
        header('Location: ' . $root);
        exit;
    }
}

$_SESSION['oauth_pending'] = [
    'provider' => 'vk',
    'oauth_id' => $userData['oauth_id'],
    'full_name' => $userData['full_name'],
    'avatar_url' => $userData['avatar_url'],
];
header('Location: ' . $root . 'auth_complete.php');
exit;
