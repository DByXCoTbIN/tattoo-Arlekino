<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\Repositories\UserRepo;
use App\ServiceRepository;
use App\BookingRepository;

Auth::init();
Auth::requireAuth();
$user = Auth::user();
Auth::requireMaster();
$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);

$userRepo = new UserRepo();
$master = $userRepo->getMasterProfile((int)$user['id'], false);
$services = [];
$masterServiceIds = [];
try {
    $services = (new ServiceRepository())->listAll();
    $masterServiceIds = (new ServiceRepository())->getMasterServiceIds((int)$user['id']);
} catch (\Throwable $e) { }
$bookingRepo = new BookingRepository();
$schedule = $bookingRepo->getSchedule((int)$user['id']) ?? [
    'work_start' => '10:00:00',
    'work_end' => '18:00:00',
    'slot_duration' => 60,
    'off_weekdays' => [],
];
if (!isset($schedule['off_weekdays']) || !is_array($schedule['off_weekdays'])) {
    $schedule['off_weekdays'] = [];
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $vk = trim($_POST['vk'] ?? '');
        $telegram = trim($_POST['telegram'] ?? '');
        $youtube = trim($_POST['youtube'] ?? '');
        $maxLink = trim($_POST['max_link'] ?? '');
        if ($fullName) {
            $userRepo->update((int)$user['id'], ['full_name' => $fullName]);
            $userRepo->updateMasterProfile((int)$user['id'], $bio, '', $phone, $instagram, $vk, $telegram, $youtube, $maxLink);
            $message = 'Профиль сохранён.';
            $master = $userRepo->getMasterProfile((int)$user['id'], false);
        }
    }
    if ($action === 'upload_avatar') {
        $avatarDir = $config['site']['upload_path'] . '/avatars';
        if (!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);
        $f = $_FILES['avatar'] ?? null;
        if ($f && ($f['error'] ?? 0) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $relPath = '/uploads/avatars/' . (int)$user['id'] . '.' . $ext;
                $fullPath = $config['site']['upload_path'] . '/avatars/' . (int)$user['id'] . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $fullPath)) {
                    $userRepo->update((int)$user['id'], ['avatar_path' => $relPath]);
                    $message = 'Аватар обновлён.';
                    $user = Auth::user(); // перезагрузка
                    $user = array_merge($user, ['avatar_path' => $relPath]);
                } else {
                    $error = 'Не удалось сохранить файл.';
                }
            } else {
                $error = 'Допустимы только изображения (jpg, png, gif, webp).';
            }
        } else {
            $error = 'Выберите изображение.';
        }
    }
    if ($action === 'remove_avatar') {
        $oldPath = $user['avatar_path'] ?? null;
        $userRepo->update((int)$user['id'], ['avatar_path' => null]);
        if ($oldPath) {
            $fullPath = dirname($config['site']['upload_path']) . $oldPath;
            if (file_exists($fullPath)) @unlink($fullPath);
        }
        $message = 'Аватар удалён.';
        $user = array_merge($user, ['avatar_path' => null]);
    }
    if ($action === 'upload_banner') {
        $bannerDir = $config['site']['upload_path'] . '/banners';
        if (!is_dir($bannerDir)) mkdir($bannerDir, 0755, true);
        $f = $_FILES['banner'] ?? null;
        if ($f && ($f['error'] ?? 0) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $relPath = '/uploads/banners/' . (int)$user['id'] . '.' . $ext;
                $fullPath = $config['site']['upload_path'] . '/banners/' . (int)$user['id'] . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $fullPath)) {
                    $userRepo->setMasterBanner((int)$user['id'], $relPath);
                    $message = 'Баннер сохранён.';
                    $master = $userRepo->getMasterProfile((int)$user['id'], false);
                } else {
                    $error = 'Не удалось сохранить файл.';
                }
            } else {
                $error = 'Допустимы только изображения (jpg, png, gif, webp).';
            }
        } else {
            $error = 'Выберите изображение.';
        }
    }
    if ($action === 'remove_banner') {
        $oldPath = $master['banner_path'] ?? null;
        $userRepo->setMasterBanner((int)$user['id'], null);
        if ($oldPath) {
            $fullPath = dirname($config['site']['upload_path']) . $oldPath;
            if (file_exists($fullPath)) @unlink($fullPath);
        }
        $message = 'Баннер удалён.';
        $master = $userRepo->getMasterProfile((int)$user['id'], false);
    }
    if ($action === 'save_services') {
        $ids = array_map('intval', $_POST['service_ids'] ?? []);
        try {
            (new ServiceRepository())->setMasterServices((int)$user['id'], $ids);
            $message = 'Услуги сохранены.';
            $masterServiceIds = (new ServiceRepository())->getMasterServiceIds((int)$user['id']);
        } catch (\Throwable $e) { $error = 'Ошибка сохранения.'; }
    }
    if ($action === 'save_schedule') {
        $workStart = trim($_POST['work_start'] ?? '10:00');
        $workEnd = trim($_POST['work_end'] ?? '18:00');
        $workStart = preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $workStart) ? (strlen($workStart) === 5 ? $workStart . ':00' : $workStart) : '10:00:00';
        $workEnd = preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $workEnd) ? (strlen($workEnd) === 5 ? $workEnd . ':00' : $workEnd) : '18:00:00';
        $maxHours = $bookingRepo->getWorkDurationHours($workStart, $workEnd);
        $slotDuration = max(1, min($maxHours, 1));
        $offDays = array_map('intval', $_POST['off_weekday'] ?? []);
        $offDays = array_values(array_unique(array_filter($offDays, static fn (int $n): bool => $n >= 1 && $n <= 7)));
        sort($offDays);
        $bookingRepo->saveSchedule((int)$user['id'], $workStart, $workEnd, $slotDuration, json_encode($offDays));
        $message = 'Расписание сохранено.';
        $schedule = $bookingRepo->getSchedule((int)$user['id']) ?? $schedule;
    }
    if ($action === 'add_day_off') {
        $d = trim($_POST['off_date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $bookingRepo->addDayOff((int)$user['id'], $d)) {
            $message = 'Дата добавлена в выходные.';
        } else {
            $error = 'Укажите корректную дату.';
        }
    }
    if ($action === 'remove_day_off') {
        $d = trim($_POST['off_date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $bookingRepo->removeDayOff((int)$user['id'], $d);
            $message = 'Дата убрана из выходных.';
        }
    }
    if ($action === 'save_profile_visibility') {
        $userRepo->setProfileHiddenByMaster((int)$user['id'], !empty($_POST['profile_hidden']));
        $message = 'Настройки видимости профиля сохранены.';
        $master = $userRepo->getMasterProfile((int)$user['id'], false);
    }
}

$dayOffDates = [];
try {
    $dayOffDates = $bookingRepo->listDayOffsFrom((int)$user['id'], date('Y-m-d'));
} catch (\Throwable $e) {
}

$pageTitle = 'Настройки профиля';
$bodyClass = 'profile-page';
$pageRobots = 'noindex, nofollow';
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/settings.php';
require __DIR__ . '/../templates/layout/footer.php';
