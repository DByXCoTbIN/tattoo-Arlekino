<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Auth;
use App\BookingRepository;
use App\Repositories\UserRepo;
use App\NotificationRepository;

header('Content-Type: application/json; charset=utf-8');

Auth::init();
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Войдите в аккаунт']);
    exit;
}
if (($user['role'] ?? '') !== 'client') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Только клиенты могут создавать запросы на запись']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$masterId = (int)($input['master_id'] ?? $_POST['master_id'] ?? 0);
$date = trim($input['date'] ?? $_POST['date'] ?? '');

if ($masterId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Укажите мастера и дату (YYYY-MM-DD)']);
    exit;
}

$userRepo = new UserRepo();
$master = $userRepo->getMasterProfile($masterId, true);
if (!$master) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Мастер не найден']);
    exit;
}

$bookingRepo = new BookingRepository();
$schedule = $bookingRepo->getSchedule($masterId);
if (!$schedule) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Мастер ещё не настроил расписание для онлайн-записи.']);
    exit;
}

$id = $bookingRepo->createBookingRequest($masterId, (int)$user['id'], $date);
if (!$id) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Не удалось отправить запрос.']);
    exit;
}

try {
    $clientName = $user['full_name'] ?? 'Клиент';
    (new NotificationRepository())->add($masterId, 'booking_request', $id, (int)$user['id'], json_encode(['client_name' => $clientName, 'date' => $date]));
} catch (\Throwable $e) { }

echo json_encode([
    'ok' => true,
    'message' => 'Запрос отправлен. Мастер подтвердит запись и укажет время. Ожидайте ответа.',
    'booking_id' => $id,
]);
