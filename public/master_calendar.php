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
Auth::requireMaster();

$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);
$root = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';
$masterId = (int)$user['id'];

$userRepo = new UserRepo();
$master = $userRepo->getMasterProfile($masterId, false);
$bookingRepo = new BookingRepository();

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$selectedDate = trim($_GET['date'] ?? $_POST['date'] ?? '');
$bookingsOfDay = [];
$redirectParams = 'year=' . $year . '&month=' . $month;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $date = trim($_POST['date'] ?? $selectedDate);

    if ($action === 'cancel' && $bookingId && $bookingRepo->cancelBooking($bookingId, $masterId)) {
        header('Location: ' . $root . 'master_calendar.php?' . $redirectParams . ($date ? '&date=' . urlencode($date) : ''));
        exit;
    }
    if ($action === 'reject' && $bookingId && $bookingRepo->rejectBooking($bookingId, $masterId)) {
        header('Location: ' . $root . 'master_calendar.php?' . $redirectParams . ($date ? '&date=' . urlencode($date) : ''));
        exit;
    }
    if ($action === 'confirm' && $bookingId) {
        $slotStart = trim($_POST['slot_start'] ?? '');
        $slotEnd = trim($_POST['slot_end'] ?? '');
        if ($slotStart && $slotEnd) {
            if ($bookingRepo->confirmBooking($bookingId, $masterId, $slotStart, $slotEnd)) {
                header('Location: ' . $root . 'master_calendar.php?' . $redirectParams . ($date ? '&date=' . urlencode($date) : '') . '&success=1');
                exit;
            }
            $error = 'Этот интервал пересекается с уже подтверждённой записью. Выберите другое время.';
        }
    }
}

if ($selectedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $bookingsOfDay = $bookingRepo->getBookingsByDate($masterId, $selectedDate);
}

$pendingRequests = $bookingRepo->getPendingRequests($masterId);
$notifRepo = new \App\NotificationRepository();
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
foreach ($bookingRepo->getUpcomingConfirmed($masterId, $today, $tomorrow) as $b) {
    if (!$notifRepo->hasReminderForBooking($masterId, (int)$b['id'])) {
        $slot = ($b['slot_time'] ?? '') ? date('H:i', strtotime($b['slot_time'])) : '';
        $notifRepo->add($masterId, 'booking_reminder', (int)$b['id'], (int)$b['client_id'], json_encode([
            'client_name' => $b['client_name'] ?? 'Клиент',
            'date' => $b['booking_date'],
            'slot' => $slot,
        ]));
    }
}
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-t', strtotime($monthStart));
$datesWithBookings = $bookingRepo->getDatesWithBookings($masterId, $monthStart, $monthEnd);
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthNames = ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
if (isset($_GET['success'])) {
    $success = 'Запись подтверждена.';
}

$pageTitle = 'Мой календарь';
$bodyClass = 'profile-page';
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/master_calendar.php';
require __DIR__ . '/../templates/layout/footer.php';
