<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\PostRepository;
use App\RatingRepository;
use App\BookingRepository;
use App\Repositories\UserRepo;
use App\Seo;

Auth::init();
$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);
$user = Auth::user();

$masterId = (int)($_GET['id'] ?? 0);
if (!$masterId) {
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    if ($user && Auth::isMaster() && (int)$user['id'] > 0) {
        header('Location: ' . ($base !== '' ? rtrim($base, '/') . '/' : '/') . 'master.php?id=' . (int)$user['id']);
        exit;
    }
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
    exit;
}

$userRepo = new UserRepo();
$postRepo = new PostRepository();
$ratingRepo = new RatingRepository();
$bookingRepo = new BookingRepository();

$master = $userRepo->getMasterProfile($masterId, false);
if (!$master) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
    exit;
}

$schedule = $bookingRepo->getSchedule($masterId);

$isOwner = $user && (int)$user['id'] === $masterId;
// Неверифицированный мастер не виден публике; админы и владелец всегда видны
if (!$isOwner && ($master['role'] ?? '') === 'master' && empty($master['is_verified'])) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
    exit;
}
if (!$isOwner && !Auth::isAdmin() && !UserRepo::isEffectiveProfilePublic($master)) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/masters.php');
    exit;
}

$ratingRepo->recalcMasterRating($masterId);
$master = $userRepo->getMasterProfile($masterId, false);

$posts = $postRepo->getWithMedia($masterId, 50, 0);
$canRate = $user && $user['role'] === 'client' && !$isOwner;
$myRating = $canRate ? $ratingRepo->getByClient($masterId, (int)$user['id']) : null;
$ratings = $isOwner ? $ratingRepo->getForMaster($masterId, 30, false) : [];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'post' && $isOwner) {
        $contentText = trim($_POST['content_text'] ?? '');
        $postId = $postRepo->create($masterId, $contentText);
        $uploadDir = $config['site']['upload_path'] . '/posts/' . $postId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $order = 0;
        foreach ($_FILES['media']['name'] ?? [] as $i => $name) {
            if (empty($name) || ($_FILES['media']['error'][$i] ?? 0) !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'image' : (in_array($ext, ['mp4', 'webm', 'mov'], true) ? 'video' : null);
            if ($type) {
                $filename = bin2hex(random_bytes(8)) . '.' . $ext;
                $path = $uploadDir . '/' . $filename;
                if (move_uploaded_file($_FILES['media']['tmp_name'][$i], $path)) {
                    $relPath = '/uploads/posts/' . $postId . '/' . $filename;
                    $postRepo->addMedia($postId, $type, $relPath, $order++);
                }
            }
        }
        $success = 'Запись опубликована.';
        $posts = $postRepo->getWithMedia($masterId, 50, 0);
    }

    if ($action === 'delete_post' && $isOwner) {
        $postId = (int)($_POST['post_id'] ?? 0);
        if ($postId && $postRepo->delete($postId, $masterId)) {
            $success = 'Запись удалена.';
            $posts = $postRepo->getWithMedia($masterId, 50, 0);
        }
    }

    if ($action === 'approve_rating' && $isOwner) {
        $ratingId = (int)($_POST['rating_id'] ?? 0);
        if ($ratingId && $ratingRepo->approveRating($ratingId, $masterId)) {
            $success = 'Отзыв подтверждён.';
            $ratings = $ratingRepo->getForMaster($masterId, 30, false);
            $master = $userRepo->getMasterProfile($masterId, false);
        }
    }

    if ($action === 'reject_rating' && $isOwner) {
        $ratingId = (int)($_POST['rating_id'] ?? 0);
        if ($ratingId && $ratingRepo->rejectRating($ratingId, $masterId)) {
            $success = 'Отзыв отклонён.';
            $ratings = $ratingRepo->getForMaster($masterId, 30, false);
            $master = $userRepo->getMasterProfile($masterId, false);
        }
    }

    if ($action === 'rate' && $canRate) {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating >= 1 && $rating <= 5) {
            $ratingRepo->setRating($masterId, (int)$user['id'], $rating, $comment);
            $success = 'Оценка сохранена.';
            $master = $userRepo->getMasterProfile($masterId, false);
            $myRating = $ratingRepo->getByClient($masterId, (int)$user['id']);
        } else {
            $error = 'Выберите оценку от 1 до 5.';
        }
    }
}

$pendingRatings = $isOwner ? array_filter($ratings, fn($r) => ($r['status'] ?? '') === 'pending') : [];

$pageTitle = $master['full_name'] . ' — тату-мастер';
$bodyClass = 'profile-page';
$roleLabel = ($master['role'] ?? '') === 'admin' ? 'Администратор' : 'Мастер';
$hideReviewRequest = ($canRate && !empty($_GET['review']));
$pageDescription = Seo::metaSnippet(trim((string) (($master['bio'] ?? '') ?: ($master['specialization'] ?? '') ?: 'Страница мастера студии ' . $siteName)));
$canonicalUrl = Seo::absoluteUrl('master.php', ['id' => $masterId], $config);
if (!empty($master['avatar_path'])) {
    $ogImage = rtrim(Seo::publicBase($config), '/') . '/' . ltrim((string) $master['avatar_path'], '/');
}
$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => 'ProfilePage',
    'mainEntity' => [
        '@type' => 'Person',
        'name' => $master['full_name'],
        'description' => Seo::metaSnippet((string) ($master['bio'] ?? ''), 320),
        'url' => $canonicalUrl,
        'jobTitle' => 'Тату-мастер',
    ],
];
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/master.php';
require __DIR__ . '/../templates/layout/footer.php';
