<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\OAuthService;
use App\Repositories\UserRepo;

Auth::init();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';

$cfg = OAuthService::getConfig()['telegram'] ?? null;
$botToken = $cfg['bot_token'] ?? '';
$botUsername = $cfg['bot_username'] ?? '';
if (!$botToken || !$botUsername) {
    header('Location: ' . $root . 'login.php?error=oauth_disabled');
    exit;
}

$data = $_GET;
if (empty($data['hash']) || empty($data['id'])) {
    header('Location: ' . $root . 'login.php?error=invalid_data');
    exit;
}

if (!OAuthService::verifyTelegramData($data, $botToken)) {
    header('Location: ' . $root . 'login.php?error=invalid_hash');
    exit;
}

$userData = OAuthService::getTelegramUserData($data);
$repo = new UserRepo();
$user = $repo->findByOAuth('telegram', $userData['oauth_id']);

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

$_SESSION['oauth_pending'] = [
    'provider' => 'telegram',
    'oauth_id' => $userData['oauth_id'],
    'full_name' => $userData['full_name'],
    'avatar_url' => $userData['avatar_url'],
];
header('Location: ' . $root . 'auth_complete.php');
exit;
