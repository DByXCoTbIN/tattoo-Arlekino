<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Auth;
use App\Repositories\UserRepo;

header('Content-Type: application/json; charset=utf-8');

Auth::init();
if (Auth::user()) {
    $u = Auth::user();
    echo json_encode(['ok' => true, 'user' => [
        'id' => (int)$u['id'], 'full_name' => $u['full_name'] ?? '', 'email' => $u['email'] ?? '', 'role' => $u['role'] ?? 'client', 'avatar_path' => $u['avatar_path'] ?? null,
    ]]);
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
$full_name = trim($input['full_name'] ?? $_POST['full_name'] ?? '');
$phone = trim($input['phone'] ?? $_POST['phone'] ?? '');

if (!$email || $password === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Заполните email и пароль.']);
    exit;
}
if (mb_strlen($full_name) < 2) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Укажите имя (не менее 2 символов).']);
    exit;
}

$repo = new UserRepo();
if ($repo->findByEmail($email)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Этот email уже зарегистрирован.']);
    exit;
}

$passwordHash = Auth::hashPassword($password);
$userId = $repo->create($email, $passwordHash, 'client', $full_name, $phone ?: null);
if ($userId <= 0) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка регистрации.']);
    exit;
}

Auth::login($userId);
$user = Auth::user();
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
