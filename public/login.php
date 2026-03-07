<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Repositories\UserRepo;

Auth::init();
if (Auth::user()) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
    exit;
}

$error = $_GET['error'] ?? '';
$oauthErrors = [
    'oauth_disabled' => 'Вход через соцсети отключён.',
    'oauth_not_ready' => 'OAuth-авторизация не готова. Выполните миграцию v12 (oauth-колонки).',
    'invalid_data' => 'Неверные данные от соцсети.',
    'invalid_hash' => 'Ошибка проверки данных Telegram.',
    'no_code' => 'Не получен код авторизации.',
    'invalid_state' => 'Проверка безопасности VK не пройдена (state).',
    'vk_failed' => 'Не удалось войти через ВКонтакте.',
];
if ($error && isset($oauthErrors[$error])) {
    $error = $oauthErrors[$error];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Заполните email и пароль.';
    } else {
        $repo = new UserRepo();
        $user = $repo->findByEmail($email);
        if (!$user || $user['is_banned']) {
            if ($user && $user['is_banned']) {
                Auth::login((int)$user['id']);
                header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/blocked.php');
                exit;
            }
            $error = 'Неверный email или пароль.';
        } elseif (\App\OAuthService::isOAuthUser($user)) {
            $error = 'Этот аккаунт привязан к соцсети. Войдите через ' . ($user['oauth_provider'] === 'telegram' ? 'Telegram' : 'ВКонтакте') . '.';
        } elseif (!Auth::verifyPassword($password, $user['password_hash'])) {
            $error = 'Неверный email или пароль.';
        } else {
            Auth::login((int)$user['id']);
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
            exit;
        }
    }
}

$config = require dirname(__DIR__) . '/config/config.php';
$oauthConfig = \App\OAuthService::getConfig();
$hasTelegram = !empty($oauthConfig['telegram']['bot_token']) && !empty($oauthConfig['telegram']['bot_username']);
$hasVk = !empty($oauthConfig['vk']['client_id']) && !empty($oauthConfig['vk']['client_secret']);
$siteName = \App\Settings::get('site_name', $config['site']['name']);
$pageTitle = 'Вход';
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/login.php';
require __DIR__ . '/../templates/layout/footer.php';
