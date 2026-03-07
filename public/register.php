<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\Repositories\UserRepo;

Auth::init();
if (Auth::user()) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
    exit;
}

$allowReg = (int)Settings::get('allow_registration', '1');
if (!$allowReg) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/login.php');
    exit;
}

$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    $role = $_POST['role'] ?? 'client';
    if (!in_array($role, ['client', 'master'], true)) $role = 'client';

    if (!$email || !$password || !$fullName) {
        $error = 'Заполните все поля.';
    } elseif ($role === 'client' && strlen($phone) < 10) {
        $error = 'Введите номер телефона для связи (не менее 10 цифр).';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль не менее 6 символов.';
    } else {
        $repo = new UserRepo();
        if ($repo->findByEmail($email)) {
            $error = 'Такой email уже зарегистрирован.';
        } else {
            $phoneFormatted = $role === 'client' && strlen($phone) >= 10
                ? (strlen($phone) === 10 ? '+7' . $phone : (str_starts_with($phone, '7') ? '+' . $phone : '+7' . substr($phone, -10)))
                : null;
            if ($phoneFormatted && $repo->findByPhone($phoneFormatted)) {
                $error = 'Этот номер телефона уже привязан к другому аккаунту.';
            } else {
                $userId = $repo->create($email, Auth::hashPassword($password), $role, $fullName, $phoneFormatted);
                Auth::login($userId);
                header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
                exit;
            }
        }
    }
}

$config = require dirname(__DIR__) . '/config/config.php';
$oauthConfig = \App\OAuthService::getConfig();
$hasTelegram = !empty($oauthConfig['telegram']['bot_token']) && !empty($oauthConfig['telegram']['bot_username']);
$hasVk = !empty($oauthConfig['vk']['client_id']) && !empty($oauthConfig['vk']['client_secret']);
$siteName = Settings::get('site_name', $config['site']['name']);
$pageTitle = 'Регистрация';
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/register.php';
require __DIR__ . '/../templates/layout/footer.php';
