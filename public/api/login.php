<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Auth;
use App\Repositories\UserRepo;
use App\OAuthService;

header('Content-Type: application/json; charset=utf-8');

Auth::init();
if (Auth::user()) {
    $u = Auth::user();
    echo json_encode([
        'ok' => true,
        'user' => [
            'id' => (int)$u['id'],
            'full_name' => $u['full_name'] ?? '',
            'email' => $u['email'] ?? '',
            'role' => $u['role'] ?? 'client',
            'avatar_path' => $u['avatar_path'] ?? null,
        ],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim($input['email'] ?? $_POST['email'] ?? '');
$password = $input['password'] ?? $_POST['password'] ?? '';

if (!$email || $password === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Заполните email и пароль.']);
    exit;
}

$repo = new UserRepo();
$user = $repo->findByEmail($email);
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Неверный email или пароль.']);
    exit;
}
if ($user['is_banned']) {
    Auth::login((int)$user['id']);
    echo json_encode(['ok' => true, 'banned' => true, 'user' => ['id' => (int)$user['id']]]);
    exit;
}
if (OAuthService::isOAuthUser($user)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Этот аккаунт привязан к соцсети. Войдите через приложение.']);
    exit;
}
if (!Auth::verifyPassword($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Неверный email или пароль.']);
    exit;
}

Auth::login((int)$user['id']);
echo json_encode([
    'ok' => true,
    'user' => [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'] ?? '',
        'email' => $user['email'] ?? '',
        'role' => $user['role'] ?? 'client',
        'avatar_path' => $user['avatar_path'] ?? null,
    ],
]);
