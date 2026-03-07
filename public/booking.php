<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\Repositories\UserRepo;
use App\BookingRepository;

Auth::init();
Auth::requireAuth();
$user = Auth::user();
if (($user['role'] ?? '') !== 'client') {
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
    header('Location: ' . $root . 'masters.php');
    exit;
}

$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);
$root = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';

$masterId = (int)($_GET['master'] ?? 0);
$userRepo = new UserRepo();
$master = $masterId ? $userRepo->getMasterProfile($masterId, true) : null;
if (!$master) {
    header('Location: ' . $root . 'masters.php');
    exit;
}

$bookingRepo = new BookingRepository();
$schedule = $bookingRepo->getSchedule($masterId);

$error = '';
$success = '';
$selectedDate = trim($_GET['date'] ?? '');

if ($schedule && $_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'request')) {
    $date = trim($_POST['booking_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = 'Выберите дату.';
    } else {
        $id = $bookingRepo->createBookingRequest($masterId, (int)$user['id'], $date);
        if ($id) {
            try {
                $clientName = $user['full_name'] ?? 'Клиент';
                (new \App\NotificationRepository())->add($masterId, 'booking_request', $id, (int)$user['id'], json_encode(['client_name' => $clientName, 'date' => $date]));
            } catch (\Throwable $e) { }
            $success = 'Запрос отправлен. Мастер подтвердит запись и укажет время. Ожидайте ответа.';
            $selectedDate = '';
        } else {
            $error = 'Не удалось отправить запрос. Попробуйте снова.';
        }
    }
}

$pageTitle = 'Запись к мастеру';
$bodyClass = 'profile-page';
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/booking.php';
require __DIR__ . '/../templates/layout/footer.php';
