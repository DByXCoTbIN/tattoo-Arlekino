<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\Repositories\UserRepo;
use App\NotificationRepository;
use App\ServiceRepository;
use App\RatingRepository;
use App\WelcomePhraseRepository;
use App\MapLocationRepository;

Auth::init();
Auth::requireAdmin();
$user = Auth::user();
$config = require dirname(__DIR__, 2) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);

$userRepo = new UserRepo();
$users = $userRepo->getAllForAdmin(200, 0, (int)$user['id']);
$masterRequests = [];
$services = [];
$allRatings = [];
$welcomePhrases = [];
try {
    $welcomePhrases = (new WelcomePhraseRepository())->getAll();
} catch (\Throwable $e) { }
try {
    $masterRequests = $userRepo->getUsersWithMasterRequest();
} catch (\Throwable $e) { }
try {
    $services = (new ServiceRepository())->listAll();
} catch (\Throwable $e) { }
try {
    $allRatings = (new RatingRepository())->getAllForAdmin(100);
} catch (\Throwable $e) { }
$mapLocations = [];
try {
    $mapLocations = (new MapLocationRepository())->getAll();
} catch (\Throwable $e) { }

$dashboardStats = ['users' => 0, 'masters' => 0, 'posts' => 0, 'services' => 0, 'ratings' => 0, 'pending_requests' => count($masterRequests)];
try {
    $pdo = \App\Database::get();
    $dashboardStats['users'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 0")->fetchColumn();
    $dashboardStats['masters'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('master', 'admin') AND is_banned = 0")->fetchColumn();
    $dashboardStats['posts'] = (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE is_published = 1")->fetchColumn();
    $dashboardStats['services'] = count($services);
    $dashboardStats['ratings'] = (int) $pdo->query("SELECT COUNT(*) FROM ratings")->fetchColumn();
} catch (\Throwable $e) { }

$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
$adminUrl = rtrim($root, '/') . '/admin/';

$adminNotificationCount = 0;
$adminNotificationItems = [];
try {
    $notifRepo = new NotificationRepository();
    $adminNotificationCount = $notifRepo->countUnreadForUser((int)$user['id'], true);
    $adminNotificationItems = $notifRepo->getForUser((int)$user['id'], true, 10);
} catch (\Throwable $e) { }

$validPages = ['dashboard', 'requests', 'site', 'content', 'users'];
$currentPage = $_GET['page'] ?? 'dashboard';
if (!in_array($currentPage, $validPages, true)) {
    $currentPage = 'dashboard';
}

$message = $_SESSION['admin_flash_message'] ?? '';
$error = $_SESSION['admin_flash_error'] ?? '';
unset($_SESSION['admin_flash_message'], $_SESSION['admin_flash_error']);

$redirectPage = $currentPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_master') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $userRepo->approveMasterRequest($uid);
            $message = 'Пользователю присвоена роль мастера.';
            $redirectPage = 'requests';
            $users = $userRepo->getAllForAdmin(200, 0, (int)$user['id']);
            $masterRequests = $userRepo->getUsersWithMasterRequest();
        }
    }
    if ($action === 'reject_master') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $userRepo->rejectMasterRequest($uid);
            $message = 'Заявка отклонена.';
            $redirectPage = 'requests';
            $masterRequests = $userRepo->getUsersWithMasterRequest();
        }
    }
    if ($action === 'set_role') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        if ($uid && in_array($role, ['client', 'master', 'admin'], true) && $uid !== (int)$user['id']) {
            $userRepo->setRole($uid, $role);
            $message = 'Роль обновлена.';
            $redirectPage = 'users';
            $users = $userRepo->getAllForAdmin(200, 0, (int)$user['id']);
        }
    }
    if ($action === 'ban') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid !== (int)$user['id']) {
            $reason = trim($_POST['ban_reason'] ?? '') ?: null;
            $userRepo->setBanned($uid, true, $reason);
            $message = 'Пользователь заблокирован.';
            $redirectPage = 'users';
            $users = $userRepo->getAllForAdmin(200, 0, (int)$user['id']);
        }
    }
    if ($action === 'unban') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $userRepo->setBanned($uid, false);
            $message = 'Пользователь разблокирован.';
            $redirectPage = 'users';
            $users = $userRepo->getAllForAdmin(200, 0, (int)$user['id']);
        }
    }
    if ($action === 'verify') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $userRepo->setVerified($uid, true);
            $message = 'Мастер отмечен как проверенный.';
            $redirectPage = 'users';
            $users = $userRepo->getAllForAdmin(200, 0, (int)$user['id']);
        }
    }
    if ($action === 'unverify') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $userRepo->setVerified($uid, false);
            $message = 'Снята отметка проверенного.';
            $redirectPage = 'users';
            $users = $userRepo->getAllForAdmin(200, 0, (int)$user['id']);
        }
    }
    if ($action === 'settings') {
        Settings::set('site_name', trim($_POST['site_name'] ?? ''));
        Settings::set('site_description', trim($_POST['site_description'] ?? ''));
        Settings::set('posts_per_page', (string)(int)($_POST['posts_per_page'] ?? 12));
        Settings::set('allow_registration', isset($_POST['allow_registration']) ? '1' : '0');
        $message = 'Настройки сохранены.';
        $redirectPage = 'site';
    }
    if ($action === 'upload_logo') {
        if (!empty($_FILES['site_logo']['name']) && ($_FILES['site_logo']['error'] ?? 0) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $dir = $config['site']['upload_path'] . '/logo';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'logo.' . $ext;
                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $dir . '/' . $fname)) {
                    Settings::set('site_logo', '/uploads/logo/' . $fname);
                    $message = 'Логотип загружен.';
                    $redirectPage = 'site';
                }
            }
        }
    }
    if ($action === 'logo_settings') {
        $allowedLogoColors = ['auto', 'gold', 'white', 'accent', 'silver', 'black', 'emerald', 'azure', 'violet'];
        Settings::set('logo_remove_bg', isset($_POST['logo_remove_bg']) ? '1' : '0');
        $logoColorDark = in_array($_POST['logo_color_dark'] ?? '', $allowedLogoColors, true) ? (string)$_POST['logo_color_dark'] : 'gold';
        $logoColorLight = in_array($_POST['logo_color_light'] ?? '', $allowedLogoColors, true) ? (string)$_POST['logo_color_light'] : 'gold';
        Settings::set('logo_color_dark', $logoColorDark);
        Settings::set('logo_color_light', $logoColorLight);
        Settings::set('logo_color', $logoColorDark); // Совместимость со старой настройкой.
        $logoSize = (int)($_POST['logo_size'] ?? 96);
        $logoSize = max(48, min(200, $logoSize));
        Settings::set('logo_size', (string)$logoSize);
        $message = 'Настройки логотипа сохранены.';
        $redirectPage = 'site';
    }
    if ($action === 'add_phrase') {
        $phrase = trim($_POST['phrase'] ?? '');
        if ($phrase !== '') {
            $dateFrom = trim($_POST['date_from'] ?? '') ?: null;
            $dateTo = trim($_POST['date_to'] ?? '') ?: null;
            (new WelcomePhraseRepository())->create($phrase, $dateFrom, $dateTo, 0);
            $message = 'Фраза добавлена.';
            $redirectPage = 'site';
            try { $welcomePhrases = (new WelcomePhraseRepository())->getAll(); } catch (\Throwable $e) { }
        }
    }
    if ($action === 'update_phrase') {
        $id = (int)($_POST['phrase_id'] ?? 0);
        $phrase = trim($_POST['phrase'] ?? '');
        if ($id && $phrase !== '') {
            $dateFrom = trim($_POST['date_from'] ?? '') ?: null;
            $dateTo = trim($_POST['date_to'] ?? '') ?: null;
            (new WelcomePhraseRepository())->update($id, $phrase, $dateFrom, $dateTo, (int)($_POST['sort_order'] ?? 0));
            $message = 'Фраза обновлена.';
            $redirectPage = 'site';
            try { $welcomePhrases = (new WelcomePhraseRepository())->getAll(); } catch (\Throwable $e) { }
        }
    }
    if ($action === 'delete_phrase') {
        $id = (int)($_POST['phrase_id'] ?? 0);
        if ($id) {
            (new WelcomePhraseRepository())->delete($id);
            $message = 'Фраза удалена.';
            $redirectPage = 'site';
            try { $welcomePhrases = (new WelcomePhraseRepository())->getAll(); } catch (\Throwable $e) { }
        }
    }
    if ($action === 'toggle_phrase') {
        $id = (int)($_POST['phrase_id'] ?? 0);
        if ($id) {
            (new WelcomePhraseRepository())->setVisible($id, empty($_POST['visible']));
            $message = empty($_POST['visible']) ? 'Фраза включена.' : 'Фраза отключена.';
            $redirectPage = 'site';
            try { $welcomePhrases = (new WelcomePhraseRepository())->getAll(); } catch (\Throwable $e) { }
        }
    }
    if ($action === 'main_page_texts') {
        Settings::set('hero_title', trim($_POST['hero_title'] ?? ''));
        Settings::set('hero_tagline', trim($_POST['hero_tagline'] ?? ''));
        Settings::set('section_masters_title', trim($_POST['section_masters_title'] ?? ''));
        Settings::set('section_feed_title', trim($_POST['section_feed_title'] ?? ''));
        Settings::set('section_services_title', trim($_POST['section_services_title'] ?? ''));
        Settings::set('section_map_title', trim($_POST['section_map_title'] ?? ''));
        $message = 'Тексты главной сохранены.';
        $redirectPage = 'site';
    }
    if ($action === 'create_service') {
        $name = trim($_POST['service_name'] ?? '');
        if ($name !== '') {
            try {
                (new ServiceRepository())->create($name, trim($_POST['service_description'] ?? ''));
                $message = 'Услуга добавлена.';
                $redirectPage = 'content';
                $services = (new ServiceRepository())->listAll();
            } catch (\Throwable $e) { $error = 'Ошибка: ' . $e->getMessage(); $redirectPage = 'content'; }
        }
    }
    if ($action === 'update_service') {
        $id = (int)($_POST['service_id'] ?? 0);
        $name = trim($_POST['service_name'] ?? '');
        if ($id && $name !== '') {
            try {
                (new ServiceRepository())->update($id, $name, trim($_POST['service_description'] ?? ''));
                $message = 'Услуга обновлена.';
                $redirectPage = 'content';
                $services = (new ServiceRepository())->listAll();
            } catch (\Throwable $e) { $error = 'Ошибка: ' . $e->getMessage(); $redirectPage = 'content'; }
        }
    }
    if ($action === 'delete_service') {
        $id = (int)($_POST['service_id'] ?? 0);
        if ($id) {
            try {
                (new ServiceRepository())->delete($id);
                $message = 'Услуга удалена.';
                $redirectPage = 'content';
                $services = (new ServiceRepository())->listAll();
            } catch (\Throwable $e) { $error = 'Ошибка: ' . $e->getMessage(); $redirectPage = 'content'; }
        }
    }
    if ($action === 'add_map_location') {
        $title = trim($_POST['title'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $mapUrl = trim($_POST['map_url'] ?? '');
        $coords = MapLocationRepository::parseCoordinatesFromUrl($mapUrl);
        if ($address !== '' && $coords) {
            (new MapLocationRepository())->create($title, $address, $mapUrl, $coords['lat'], $coords['lng'], (int)($_POST['sort_order'] ?? 0));
            $message = 'Адрес добавлен.';
            $redirectPage = 'site';
            try { $mapLocations = (new MapLocationRepository())->getAll(); } catch (\Throwable $e) { }
        } else {
            $error = $coords ? 'Заполните адрес.' : 'Не удалось извлечь координаты из ссылки. Вставьте ссылку Google Maps или Yandex Maps.';
            $redirectPage = 'site';
        }
    }
    if ($action === 'delete_map_location') {
        $id = (int)($_POST['location_id'] ?? 0);
        if ($id) {
            (new MapLocationRepository())->delete($id);
            $message = 'Адрес удалён.';
            $redirectPage = 'site';
            try { $mapLocations = (new MapLocationRepository())->getAll(); } catch (\Throwable $e) { }
        }
    }
    if ($action === 'restore_rating') {
        $id = (int)($_POST['rating_id'] ?? 0);
        if ($id && (new RatingRepository())->adminRestore($id)) {
            $message = 'Отзыв восстановлен и отображается.';
            $redirectPage = 'content';
            $allRatings = (new RatingRepository())->getAllForAdmin(100);
        }
    }

    if ($message !== '' || $error !== '') {
        if (!isset($_SESSION)) {
            Auth::init();
        }
        $_SESSION['admin_flash_message'] = $message;
        $_SESSION['admin_flash_error'] = $error;
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $redirectUrl = $protocol . '://' . $host . $adminUrl . '?page=' . $redirectPage;
        header('Location: ' . $redirectUrl, true, 303);
        exit;
    }
}

$pageTitle = 'Админ-панель';
$bodyClass = 'admin-page';
$adminSidebarLayout = true;
require dirname(__DIR__, 2) . '/templates/layout/header.php';
?>
<div class="admin-layout">
<aside class="admin-sidebar">
    <div class="admin-sidebar__top">
        <div class="admin-sidebar__brand">
            <span class="arlequino-gothic"><?= htmlspecialchars($siteName) ?></span>
            <span class="admin-sidebar__sub">Админка</span>
        </div>
        <input type="checkbox" id="adminNavToggle" class="admin-nav-toggle-input" aria-label="Меню">
        <label for="adminNavToggle" class="admin-nav-toggle-label" aria-label="Открыть меню"><span></span><span></span><span></span></label>
    </div>
    <nav class="admin-sidebar__nav">
        <a href="<?= htmlspecialchars($adminUrl) ?>" class="admin-sidebar__link <?= $currentPage === 'dashboard' ? 'is-active' : '' ?>">Обзор</a>
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=requests" class="admin-sidebar__link <?= $currentPage === 'requests' ? 'is-active' : '' ?>">Заявки<?= $dashboardStats['pending_requests'] > 0 ? ' <span class="admin-sidebar__badge">' . $dashboardStats['pending_requests'] . '</span>' : '' ?></a>
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=site" class="admin-sidebar__link <?= $currentPage === 'site' ? 'is-active' : '' ?>">Сайт</a>
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=content" class="admin-sidebar__link <?= $currentPage === 'content' ? 'is-active' : '' ?>">Контент</a>
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=users" class="admin-sidebar__link <?= $currentPage === 'users' ? 'is-active' : '' ?>">Пользователи</a>
        <div class="admin-notifications-wrap">
            <button type="button" class="admin-notifications-bell" id="adminNotificationsBell" aria-label="Уведомления" title="Уведомления">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if ($adminNotificationCount > 0): ?><span class="admin-notifications-badge"><?= $adminNotificationCount > 99 ? '99+' : $adminNotificationCount ?></span><?php endif; ?>
            </button>
            <div class="admin-notifications-dropdown" id="adminNotificationsDropdown" aria-hidden="true">
                <div class="admin-notifications-dropdown__header">Уведомления</div>
                <div class="admin-notifications-dropdown__list" id="adminNotificationsList">
                    <?php
                    $fmt = function ($n) use ($root, $adminUrl) {
                        $type = $n['type'] ?? '';
                        $refId = (int)($n['ref_id'] ?? 0);
                        $data = !empty($n['data']) ? (json_decode($n['data'], true) ?: []) : [];
                        $url = $root . 'messages.php';
                        $title = 'Уведомление';
                        $text = '';
                        switch ($type) {
                            case 'message': $title = 'Новое сообщение'; $text = 'От ' . htmlspecialchars($n['from_name'] ?? 'Кто-то'); $url = $root . 'messages.php?conv=' . $refId; break;
                            case 'master_request': $title = 'Заявка на роль мастера'; $text = htmlspecialchars(($data['full_name'] ?? '') . ' — ' . ($data['email'] ?? '')); $url = $adminUrl . '?page=requests'; break;
                            case 'booking_request': $title = 'Запрос на запись'; $text = htmlspecialchars(($data['client_name'] ?? 'Клиент') . ' на ' . ($data['date'] ?? '')); $url = $root . 'master_calendar.php'; break;
                            case 'support_message': $title = 'Сообщение в поддержке'; $text = htmlspecialchars(mb_substr($data['preview'] ?? 'Новое сообщение', 0, 80)); $url = $root . 'messages.php?group=' . $refId; break;
                            case 'rating_pending': $title = 'Новый отзыв на модерации'; $text = htmlspecialchars(mb_substr($data['comment_preview'] ?? '', 0, 60) . ' (★' . ($data['value'] ?? 0) . ')'); $url = $adminUrl . '?page=content'; break;
                            default: $text = htmlspecialchars($n['data'] ?? '');
                        }
                        return ['id' => (int)$n['id'], 'title' => $title, 'text' => $text, 'url' => $url];
                    };
                    foreach ($adminNotificationItems as $n):
                        $item = $fmt($n);
                    ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="admin-notification-item" data-id="<?= $item['id'] ?>">
                        <span class="admin-notification-item__title"><?= $item['title'] ?></span>
                        <span class="admin-notification-item__text"><?= $item['text'] ?></span>
                    </a>
                    <?php endforeach; ?>
                    <?php if (empty($adminNotificationItems)): ?>
                    <div class="admin-notification-item admin-notification-item--empty">Нет новых уведомлений</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <a href="<?= htmlspecialchars($root) ?>" class="admin-sidebar__out">На сайт</a>
</aside>
<main class="admin-content">
<?php if ($message): ?><div class="admin-msg admin-msg--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-msg admin-msg--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($currentPage === 'dashboard'): ?>
<section class="admin-dashboard">
    <div class="admin-dashboard__welcome">
        <h1 class="admin-dashboard__title">Добро пожаловать, <?= htmlspecialchars(explode(' ', $user['full_name'])[0] ?? 'Админ') ?></h1>
        <p class="admin-dashboard__intro">Управляйте контентом, пользователями и настройками студии <strong><?= htmlspecialchars($siteName) ?></strong>. Панель даёт полный контроль над сайтом.</p>
    </div>
    <div class="admin-dashboard__stats">
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=users" class="admin-stat-card admin-stat-card--users">
            <span class="admin-stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span class="admin-stat-card__value"><?= $dashboardStats['users'] ?></span>
            <span class="admin-stat-card__label">Пользователей</span>
        </a>
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=users" class="admin-stat-card admin-stat-card--masters">
            <span class="admin-stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15 8 22 9 17 14 18 21 12 18 6 21 7 14 2 9 9 8"/></svg></span>
            <span class="admin-stat-card__value"><?= $dashboardStats['masters'] ?></span>
            <span class="admin-stat-card__label">Мастеров</span>
        </a>
        <a href="<?= htmlspecialchars($root) ?>" class="admin-stat-card admin-stat-card--posts">
            <span class="admin-stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span>
            <span class="admin-stat-card__value"><?= $dashboardStats['posts'] ?></span>
            <span class="admin-stat-card__label">Записей</span>
        </a>
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=content" class="admin-stat-card admin-stat-card--services">
            <span class="admin-stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="0" y1="6" x2="21" y2="6"/><line x1="0" y1="12" x2="21" y2="12"/><line x1="0" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
            <span class="admin-stat-card__value"><?= $dashboardStats['services'] ?></span>
            <span class="admin-stat-card__label">Услуг</span>
        </a>
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=content" class="admin-stat-card admin-stat-card--ratings">
            <span class="admin-stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15 8 22 9 17 14 18 21 12 18 6 21 7 14 2 9 9 8"/></svg></span>
            <span class="admin-stat-card__value"><?= $dashboardStats['ratings'] ?></span>
            <span class="admin-stat-card__label">Отзывов</span>
        </a>
        <a href="<?= htmlspecialchars($adminUrl) ?>?page=site" class="admin-stat-card admin-stat-card--map">
            <span class="admin-stat-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
            <span class="admin-stat-card__value"><?= count($mapLocations) ?></span>
            <span class="admin-stat-card__label">Адресов</span>
        </a>
    </div>
    <?php if ($dashboardStats['pending_requests'] > 0): ?>
    <div class="admin-dashboard__alert">
        <div class="admin-dashboard__alert-content">
            <strong>Требуется внимание</strong>
            <p>Ожидают рассмотрения <strong><?= $dashboardStats['pending_requests'] ?></strong> <?= $dashboardStats['pending_requests'] === 1 ? 'заявка' : ($dashboardStats['pending_requests'] < 5 ? 'заявки' : 'заявок') ?> на роль мастера.</p>
            <a href="<?= htmlspecialchars($adminUrl) ?>?page=requests" class="admin-btn admin-btn--primary">Рассмотреть заявки</a>
        </div>
    </div>
    <?php endif; ?>
    <div class="admin-dashboard__quick">
        <h2 class="admin-dashboard__section-title">Быстрые действия</h2>
        <div class="admin-dashboard__quick-grid">
            <a href="<?= htmlspecialchars($adminUrl) ?>?page=site" class="admin-quick-link">
                <span class="admin-quick-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg></span>
                <span>Баннер и тексты</span>
            </a>
            <a href="<?= htmlspecialchars($adminUrl) ?>?page=site" class="admin-quick-link">
                <span class="admin-quick-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                <span>Адреса на карте</span>
            </a>
            <a href="<?= htmlspecialchars($adminUrl) ?>?page=content" class="admin-quick-link">
                <span class="admin-quick-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
                <span>Услуги</span>
            </a>
            <a href="<?= htmlspecialchars($adminUrl) ?>?page=content" class="admin-quick-link">
                <span class="admin-quick-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15 8 22 9 17 14 18 21 12 18 6 21 7 14 2 9 9 8"/></svg></span>
                <span>Модерация отзывов</span>
            </a>
            <a href="<?= htmlspecialchars($adminUrl) ?>?page=users" class="admin-quick-link">
                <span class="admin-quick-link__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span>Управление пользователями</span>
            </a>
        </div>
    </div>
</section>
<?php elseif ($currentPage === 'requests'): ?>
<section class="admin-theme" data-theme="requests">
<div class="admin-section" id="requests">
    <h2>Заявки на роль мастера</h2>
    <?php if (count($masterRequests) > 0): ?>
    <p>Пользователи запросили доступ в личный кабинет мастера. Одобрите или отклоните.</p>
    <div class="admin-table-wrap">
    <table class="admin-table">
        <thead><tr><th>Имя</th><th>Email</th><th>Дата заявки</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($masterRequests as $req): ?>
                <tr>
                    <td><?= htmlspecialchars($req['full_name']) ?></td>
                    <td><?= htmlspecialchars($req['email']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($req['role_requested_at'] ?? $req['created_at'])) ?></td>
                    <td><div class="admin-actions">
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="action" value="approve_master">
                            <input type="hidden" name="user_id" value="<?= (int)$req['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Одобрить</button>
                        </form>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="action" value="reject_master">
                            <input type="hidden" name="user_id" value="<?= (int)$req['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn--secondary admin-btn--sm">Отклонить</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <p>Заявок пока нет.</p>
    <?php endif; ?>
</div>
</section>
<?php elseif ($currentPage === 'site'): ?>
<section class="admin-theme" data-theme="site">
<div class="admin-section" id="banner">
    <h2>Баннер и приветственные фразы</h2>
    <p>Логотип отображается слева в баннере и в шапке. Фразы с датами показываются в выбранные даты; без дат — постоянно (кроме дат, выбранных другими фразами).</p>
    <?php
    $logoPath = Settings::get('site_logo', '');
    $logoSizeValue = max(48, min(200, (int)Settings::get('logo_size', '96')));
    $logoHeaderSize = max(20, min(82, (int)round($logoSizeValue * 0.36)));
    $logoColorOptions = [
        'auto' => 'Как в файле (без изменений)',
        'gold' => 'Золотой',
        'white' => 'Белый',
        'accent' => 'Бордовый (акцентный)',
        'silver' => 'Серебряный',
        'black' => 'Чёрный',
        'emerald' => 'Изумрудный',
        'azure' => 'Лазурный',
        'violet' => 'Фиолетовый',
    ];
    $legacyLogoColor = Settings::get('logo_color', 'gold');
    $currentLogoColorDark = Settings::get('logo_color_dark', $legacyLogoColor ?: 'gold');
    $currentLogoColorLight = Settings::get('logo_color_light', $legacyLogoColor ?: 'gold');
    if (!isset($logoColorOptions[$currentLogoColorDark])) $currentLogoColorDark = 'gold';
    if (!isset($logoColorOptions[$currentLogoColorLight])) $currentLogoColorLight = 'gold';
    $logoPreviewClassDark = 'site-logo-img logo-preview-color logo-color-' . $currentLogoColorDark;
    $logoPreviewClassLight = 'site-logo-img logo-preview-color logo-color-' . $currentLogoColorLight;
    if (Settings::get('logo_remove_bg', '0') === '1') {
        $logoPreviewClassDark .= ' logo-remove-bg';
        $logoPreviewClassLight .= ' logo-remove-bg';
    }
    ?>
    <form method="post" action="?page=<?= htmlspecialchars($currentPage) ?>" enctype="multipart/form-data" style="margin-bottom: 24px;">
        <input type="hidden" name="action" value="upload_logo">
        <div class="admin-field">
            <label>Логотип студии</label>
            <?php if ($logoPath): ?>
            <div class="admin-logo-preview" style="margin: 12px 0; padding: 20px; background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: 8px; display: inline-flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="background: #1a1a1e; padding: 16px; border-radius: 6px;"><span style="font-size: 0.8rem; color: var(--adm-muted); display: block; margin-bottom: 8px;">Тёмный фон</span><img src="<?= htmlspecialchars($root . ltrim($logoPath, '/')) ?>" alt="" style="max-height: 48px; max-width: 120px; object-fit: contain; display: block;"></div>
                <div style="background: #f5f4f2; padding: 16px; border-radius: 6px;"><span style="font-size: 0.8rem; color: #6b6b75; display: block; margin-bottom: 8px;">Светлый фон</span><img src="<?= htmlspecialchars($root . ltrim($logoPath, '/')) ?>" alt="" style="max-height: 48px; max-width: 120px; object-fit: contain; display: block;"></div>
            </div>
            <?php endif; ?>
            <input type="file" name="site_logo" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Загрузить логотип</button>
    </form>
    <form method="post" action="?page=<?= htmlspecialchars($currentPage) ?>" class="admin-logo-settings" style="margin-bottom: 24px;">
        <input type="hidden" name="action" value="logo_settings">
        <h3 style="margin: 0 0 16px;">Оформление логотипа</h3>
        <div class="admin-field" style="margin-bottom: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="logo_remove_bg" value="1" <?= Settings::get('logo_remove_bg', '0') === '1' ? 'checked' : '' ?>>
                Убрать светлый фон (для логотипов на белом/светлом фоне)
            </label>
            <small style="color: var(--adm-muted); display: block; margin-top: 4px;">Делает белые и светлые области прозрачными на тёмном фоне сайта.</small>
        </div>
        <div class="admin-field" style="margin-bottom: 12px;">
            <label>Заливка логотипа для тёмной темы</label>
            <select id="logoColorDark" name="logo_color_dark" style="padding: 8px 12px; border-radius: 6px; background: var(--adm-surface); border: 1px solid var(--adm-border); color: var(--adm-text); min-width: 240px;">
                <?php foreach ($logoColorOptions as $colorKey => $colorLabel): ?>
                    <option value="<?= htmlspecialchars($colorKey) ?>" <?= $currentLogoColorDark === $colorKey ? 'selected' : '' ?>><?= htmlspecialchars($colorLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-field" style="margin-bottom: 12px;">
            <label>Заливка логотипа для светлой темы</label>
            <select id="logoColorLight" name="logo_color_light" style="padding: 8px 12px; border-radius: 6px; background: var(--adm-surface); border: 1px solid var(--adm-border); color: var(--adm-text); min-width: 240px;">
                <?php foreach ($logoColorOptions as $colorKey => $colorLabel): ?>
                    <option value="<?= htmlspecialchars($colorKey) ?>" <?= $currentLogoColorLight === $colorKey ? 'selected' : '' ?>><?= htmlspecialchars($colorLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-field" style="margin-bottom: 12px;">
            <label for="logoSizeRange">Размер логотипа</label>
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <input type="range" id="logoSizeRange" name="logo_size" min="48" max="200" step="1" value="<?= $logoSizeValue ?>" style="width: 220px;">
                <input type="number" id="logoSizeNumber" min="48" max="200" step="1" value="<?= $logoSizeValue ?>" style="width: 82px; padding: 6px 8px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-surface); color: var(--adm-text);">
                <span style="color: var(--adm-muted); font-size: 0.9rem;">px</span>
            </div>
        </div>
        <?php if ($logoPath): ?>
        <div id="logoSizePreview" style="--logo-size-banner: <?= $logoSizeValue ?>px; --logo-size-header: <?= $logoHeaderSize ?>px; margin: 12px 0 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
            <div style="background:#101014; border:1px solid var(--adm-border); border-radius:8px; padding:12px;">
                <div style="font-size: 0.8rem; color: var(--adm-muted); margin-bottom:8px;">Превью в баннере</div>
                <div style="height:110px; display:flex; align-items:center; justify-content:center; border-radius:6px;">
                    <img id="logoPreviewBanner" src="<?= htmlspecialchars($root . ltrim($logoPath, '/')) ?>" alt="" class="<?= htmlspecialchars($logoPreviewClassDark) ?>" style="max-height: var(--logo-size-banner); max-width: calc(var(--logo-size-banner) * 1.8); object-fit: contain; display:block;">
                </div>
            </div>
            <div style="background:#101014; border:1px solid var(--adm-border); border-radius:8px; padding:12px;">
                <div style="font-size: 0.8rem; color: var(--adm-muted); margin-bottom:8px;">Превью в шапке</div>
                <div style="height:40px; width:120px; display:flex; align-items:center; justify-content:center; border-radius:6px;">
                    <img id="logoPreviewHeader" src="<?= htmlspecialchars($root . ltrim($logoPath, '/')) ?>" alt="" class="header-logo <?= htmlspecialchars($logoPreviewClassLight) ?>" style="max-height: var(--logo-size-header); max-width: 100%; object-fit: contain; display:block;">
                </div>
            </div>
        </div>
        <?php endif; ?>
        <button type="submit" class="admin-btn admin-btn--primary">Сохранить оформление</button>
    </form>
    <script>
    (function () {
        var range = document.getElementById('logoSizeRange');
        var number = document.getElementById('logoSizeNumber');
        var preview = document.getElementById('logoSizePreview');
        var colorDark = document.getElementById('logoColorDark');
        var colorLight = document.getElementById('logoColorLight');
        var previewBanner = document.getElementById('logoPreviewBanner');
        var previewHeader = document.getElementById('logoPreviewHeader');
        if (!range || !number) return;
        var colorClasses = ['logo-color-auto', 'logo-color-gold', 'logo-color-white', 'logo-color-accent', 'logo-color-silver', 'logo-color-black', 'logo-color-emerald', 'logo-color-azure', 'logo-color-violet'];
        function applyPreviewColor(img, value) {
            if (!img) return;
            colorClasses.forEach(function (c) { img.classList.remove(c); });
            img.classList.add('logo-color-' + value);
        }
        function processLogoImage(img) {
            if (!img || !img.classList.contains('logo-remove-bg') || img.dataset.logoBgProcessed === '1') return;
            var apply = function () {
                if (!img.naturalWidth || !img.naturalHeight) return;
                var canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                var ctx = canvas.getContext('2d', { willReadFrequently: true });
                if (!ctx) return;
                ctx.drawImage(img, 0, 0);
                var imageData;
                try { imageData = ctx.getImageData(0, 0, canvas.width, canvas.height); } catch (e) { return; }
                var data = imageData.data;
                for (var i = 0; i < data.length; i += 4) {
                    var r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
                    if (a === 0) continue;
                    var max = Math.max(r, g, b), min = Math.min(r, g, b);
                    var sat = max - min, bright = (r + g + b) / 3;
                    if (bright >= 244 && sat <= 18) { data[i + 3] = 0; continue; }
                    if (bright >= 226 && sat <= 32) {
                        var fade = (bright - 226) / 28;
                        data[i + 3] = Math.round(a * (1 - Math.max(0, Math.min(1, fade))));
                    }
                }
                ctx.putImageData(imageData, 0, 0);
                img.dataset.logoBgProcessed = '1';
                img.src = canvas.toDataURL('image/png');
            };
            if (img.complete) apply(); else img.addEventListener('load', apply, { once: true });
        }
        function clamp(v) {
            v = parseInt(v, 10);
            if (Number.isNaN(v)) v = 96;
            return Math.max(48, Math.min(200, v));
        }
        function update(value, source) {
            var size = clamp(value);
            range.value = String(size);
            number.value = String(size);
            if (preview) {
                var headerSize = Math.max(20, Math.min(82, Math.round(size * 0.36)));
                preview.style.setProperty('--logo-size-banner', size + 'px');
                preview.style.setProperty('--logo-size-header', headerSize + 'px');
            }
            if (source === 'number') {
                var hidden = document.querySelector('input[name="logo_size"]');
                if (hidden && hidden !== range) hidden.value = String(size);
            }
        }
        range.addEventListener('input', function () { update(range.value, 'range'); });
        number.addEventListener('input', function () { update(number.value, 'number'); });
        number.addEventListener('blur', function () { update(number.value, 'number'); });
        if (colorDark) {
            colorDark.addEventListener('change', function () {
                applyPreviewColor(previewBanner, colorDark.value || 'gold');
            });
        }
        if (colorLight) {
            colorLight.addEventListener('change', function () {
                applyPreviewColor(previewHeader, colorLight.value || 'gold');
            });
        }
        document.querySelectorAll('#logoSizePreview img.logo-remove-bg').forEach(processLogoImage);
        if (colorDark) applyPreviewColor(previewBanner, colorDark.value || 'gold');
        if (colorLight) applyPreviewColor(previewHeader, colorLight.value || 'gold');
        update(range.value, 'range');
    })();
    </script>
    <h3>Приветственные фразы</h3>
    <form method="post" action="">
        <input type="hidden" name="action" value="add_phrase">
        <div class="admin-field">
            <label>Новая фраза</label>
            <input type="text" name="phrase" placeholder="Добро пожаловать в студию" required>
        </div>
        <div class="admin-field" style="display: flex; gap: 12px; flex-wrap: wrap;">
            <label>С даты (оставьте пусто для постоянной): <input type="date" name="date_from"></label>
            <label>По дату: <input type="date" name="date_to"></label>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Добавить фразу</button>
    </form>
    <?php if (!empty($welcomePhrases)): ?>
    <div class="admin-table-wrap" style="margin-top: 20px;">
        <table class="admin-table">
            <thead><tr><th>Фраза</th><th>С даты</th><th>По дату</th><th>Показ</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($welcomePhrases as $wp): ?>
                <?php $visible = (int)($wp['is_visible'] ?? 1) === 1; ?>
                <tr class="phrase-row <?= $visible ? '' : 'phrase-row--hidden' ?>" data-id="<?= (int)$wp['id'] ?>">
                    <td class="phrase-cell"><?= htmlspecialchars($wp['phrase']) ?></td>
                    <td><?= $wp['date_from'] ? date('d.m.Y', strtotime($wp['date_from'])) : 'постоянно' ?></td>
                    <td><?= $wp['date_to'] ? date('d.m.Y', strtotime($wp['date_to'])) : '—' ?></td>
                    <td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_phrase">
                            <input type="hidden" name="phrase_id" value="<?= (int)$wp['id'] ?>">
                            <input type="hidden" name="visible" value="<?= $visible ? '1' : '0' ?>">
                            <button type="submit" class="admin-btn admin-btn--sm <?= $visible ? 'admin-btn--secondary' : 'admin-btn--primary' ?>"><?= $visible ? 'Скрыть' : 'Показать' ?></button>
                        </form>
                    </td>
                    <td>
                        <button type="button" class="admin-btn admin-btn--secondary admin-btn--sm phrase-edit-btn" data-phrase="<?= htmlspecialchars($wp['phrase']) ?>" data-date-from="<?= htmlspecialchars($wp['date_from'] ?? '') ?>" data-date-to="<?= htmlspecialchars($wp['date_to'] ?? '') ?>" data-sort="<?= (int)($wp['sort_order'] ?? 0) ?>">Редактировать</button>
                        <form method="post" action="" style="display:inline;" onsubmit="return confirm('Удалить?');">
                            <input type="hidden" name="action" value="delete_phrase">
                            <input type="hidden" name="phrase_id" value="<?= (int)$wp['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Удалить</button>
                        </form>
                    </td>
                </tr>
                <tr class="phrase-edit-row" id="phrase-edit-<?= (int)$wp['id'] ?>" style="display:none;">
                    <td colspan="5" class="phrase-edit-cell" style="padding: 16px;">
                        <form method="post" action="" class="phrase-edit-form">
                            <input type="hidden" name="action" value="update_phrase">
                            <input type="hidden" name="phrase_id" value="<?= (int)$wp['id'] ?>">
                            <div class="admin-field">
                                <label>Фраза</label>
                                <input type="text" name="phrase" value="<?= htmlspecialchars($wp['phrase']) ?>" required style="width: 100%; max-width: 400px;">
                            </div>
                            <div class="admin-field" style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px;">
                                <label>С даты (пусто = постоянно): <input type="date" name="date_from" value="<?= htmlspecialchars($wp['date_from'] ?? '') ?>"></label>
                                <label>По дату: <input type="date" name="date_to" value="<?= htmlspecialchars($wp['date_to'] ?? '') ?>"></label>
                                <label>Порядок: <input type="number" name="sort_order" value="<?= (int)($wp['sort_order'] ?? 0) ?>" style="width: 60px;"></label>
                            </div>
                            <div style="margin-top: 12px;">
                                <button type="submit" class="admin-btn admin-btn--primary">Сохранить</button>
                                <button type="button" class="admin-btn admin-btn--secondary phrase-edit-cancel">Отмена</button>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    (function(){
        document.querySelectorAll('.phrase-edit-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                var tr = btn.closest('.phrase-row');
                var id = tr.dataset.id;
                var editRow = document.getElementById('phrase-edit-' + id);
                document.querySelectorAll('.phrase-edit-row').forEach(function(r){ r.style.display = 'none'; });
                if (editRow) { editRow.style.display = 'table-row'; }
            });
        });
        document.querySelectorAll('.phrase-edit-cancel').forEach(function(btn){
            btn.addEventListener('click', function(){
                btn.closest('.phrase-edit-row').style.display = 'none';
            });
        });
    })();
    </script>
    <?php endif; ?>
</div>

<div class="admin-section" id="map" data-subtheme="location">
    <h2>Адреса на карте</h2>
    <p>Добавьте адреса студии. Вставьте ссылку на точку в Google Maps или Yandex Maps — координаты извлекутся автоматически. Блок карты отображается на главной перед лентой.</p>
    <form method="post" action="" style="margin-bottom: 20px;">
        <input type="hidden" name="action" value="add_map_location">
        <div class="admin-field">
            <label>Название (опционально)</label>
            <input type="text" name="title" placeholder="Студия на Арбате">
        </div>
        <div class="admin-field">
            <label>Адрес для отображения</label>
            <input type="text" name="address" placeholder="ул. Арбат, 12" required>
        </div>
        <div class="admin-field">
            <label>Ссылка на объект (Google Maps / Yandex Maps)</label>
            <input type="url" name="map_url" placeholder="https://www.google.com/maps/place/..." required style="width: 100%; max-width: 500px;">
        </div>
        <div class="admin-field">
            <label>Порядок</label>
            <input type="number" name="sort_order" value="0" style="width: 80px;">
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Добавить адрес</button>
    </form>
    <?php if (!empty($mapLocations)): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Название</th><th>Адрес</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($mapLocations as $ml): ?>
                <tr>
                    <td><?= htmlspecialchars($ml['title'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($ml['address']) ?></td>
                    <td>
                        <form method="post" action="" style="display:inline;" onsubmit="return confirm('Удалить?');">
                            <input type="hidden" name="action" value="delete_map_location">
                            <input type="hidden" name="location_id" value="<?= (int)$ml['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="admin-section" id="maintexts" data-subtheme="texts">
    <h2>Тексты главной страницы</h2>
    <form method="post" action="">
        <input type="hidden" name="action" value="main_page_texts">
        <div class="admin-field">
            <label>Заголовок блока о студии</label>
            <input type="text" name="hero_title" value="<?= htmlspecialchars(Settings::get('hero_title', 'Добро пожаловать на арену')) ?>">
        </div>
        <div class="admin-field">
            <label>Подзаголовок (слоган)</label>
            <input type="text" name="hero_tagline" value="<?= htmlspecialchars(Settings::get('hero_tagline', 'Смотрите работы мастеров, ставьте оценки и общайтесь в личных сообщениях.')) ?>">
        </div>
        <div class="admin-field">
            <label>Заголовок блока «Мастера»</label>
            <input type="text" name="section_masters_title" value="<?= htmlspecialchars(Settings::get('section_masters_title', 'Наши мастера')) ?>">
        </div>
        <div class="admin-field">
            <label>Заголовок блока «Лента»</label>
            <input type="text" name="section_feed_title" value="<?= htmlspecialchars(Settings::get('section_feed_title', 'Лента')) ?>">
        </div>
        <div class="admin-field">
            <label>Заголовок блока «Услуги»</label>
            <input type="text" name="section_services_title" value="<?= htmlspecialchars(Settings::get('section_services_title', 'Услуги студии')) ?>">
        </div>
        <div class="admin-field">
            <label>Заголовок блока «Карта»</label>
            <input type="text" name="section_map_title" value="<?= htmlspecialchars(Settings::get('section_map_title', 'Как нас найти')) ?>">
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Сохранить</button>
    </form>
</div>

<div class="admin-section" id="settings" data-subtheme="settings">
    <h2>Настройки сайта</h2>
    <form method="post" action="">
        <input type="hidden" name="action" value="settings">
        <div class="admin-field">
            <label>Название сайта</label>
            <input type="text" name="site_name" value="<?= htmlspecialchars(Settings::get('site_name', 'АрлекинО')) ?>">
        </div>
        <div class="admin-field">
            <label>Описание</label>
            <input type="text" name="site_description" value="<?= htmlspecialchars(Settings::get('site_description', '')) ?>">
        </div>
        <div class="admin-field">
            <label>Записей на странице</label>
            <input type="number" name="posts_per_page" value="<?= htmlspecialchars(Settings::get('posts_per_page', '12')) ?>" min="1" max="50">
        </div>
        <div class="admin-field">
            <label><input type="checkbox" name="allow_registration" value="1" <?= Settings::get('allow_registration', '1') === '1' ? 'checked' : '' ?>> Разрешить регистрацию</label>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Сохранить</button>
    </form>
</div>
</section>
<?php elseif ($currentPage === 'content'): ?>
<section class="admin-theme" data-theme="content">
<div class="admin-section" id="services">
    <h2>Услуги студии</h2>
    <p>Мастера привязывают услуги к себе в личном кабинете.</p>
    <form method="post" action="" style="margin-bottom: 20px;">
        <input type="hidden" name="action" value="create_service">
        <div class="admin-field">
            <label>Название</label>
            <input type="text" name="service_name" required>
        </div>
        <div class="admin-field">
            <label>Описание</label>
            <textarea name="service_description" rows="2"></textarea>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary">Добавить</button>
    </form>
    <div class="admin-table-wrap">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Название</th><th>Описание</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($services as $sv): ?>
                <tr>
                    <td><?= (int)$sv['id'] ?></td>
                    <td><?= htmlspecialchars($sv['name']) ?></td>
                    <td><?= htmlspecialchars(mb_substr($sv['description'] ?? '', 0, 80)) ?></td>
                    <td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="action" value="delete_service">
                            <input type="hidden" name="service_id" value="<?= (int)$sv['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn--secondary admin-btn--sm" onclick="return confirm('Удалить?');">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="admin-section" id="ratings" data-subtheme="ratings">
    <h2>Модерация отзывов</h2>
    <p>Восстановите отзыв, если мастер отозвал его ошибочно.</p>
    <div class="admin-table-wrap">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Мастер</th><th>Клиент</th><th>★</th><th>Комментарий</th><th>Статус</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($allRatings as $r): ?>
                <?php $status = $r['status'] ?? 'approved'; $canRestore = in_array($status, ['rejected', 'pending', 'hidden'], true); ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= htmlspecialchars($r['master_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['client_name'] ?? '') ?></td>
                    <td><?= (int)($r['value'] ?? 0) ?></td>
                    <td><?= htmlspecialchars(mb_substr($r['comment'] ?? '', 0, 40)) ?></td>
                    <td><?= htmlspecialchars($status) ?></td>
                    <td>
                        <?php if ($canRestore): ?>
                            <form method="post" action="" style="display:inline;">
                                <input type="hidden" name="action" value="restore_rating">
                                <input type="hidden" name="rating_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Восстановить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</section>
<?php elseif ($currentPage === 'users'): ?>
<section class="admin-theme" data-theme="users">
<div class="admin-section" id="users">
    <h2>Пользователи</h2>
    <p class="admin-hint" style="margin: 0 0 12px; font-size: 0.9rem; color: var(--text-muted);">Верификация: мастера и админы в панели отображаются с правами мастера, но без верификации — до неё они не показываются на сайте. Можно снять роль (перевести в клиента) или верифицировать: тогда пользователь попадает в список мастеров и получает полные права мастера на сайте.</p>
    <div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Имя</th>
                <th>Email</th>
                <th>Роль</th>
                <th>Рейт.</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php if ((int)$u['id'] === (int)$user['id']): ?>
                            <?= htmlspecialchars($u['role']) ?>
                        <?php else: ?>
                            <form method="post" action="" style="display:inline;">
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <select name="role" onchange="this.form.submit()">
                                    <option value="client" <?= $u['role'] === 'client' ? 'selected' : '' ?>>Клиент</option>
                                    <option value="master" <?= $u['role'] === 'master' ? 'selected' : '' ?>>Мастер</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)($u['rating_count'] ?? 0) ?></td>
                    <td><?= !empty($u['is_banned']) ? 'Заблокирован' : 'Активен' ?></td>
                    <td>
                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                            <div class="admin-actions">
                            <?php if (empty($u['is_banned'])): ?>
                                <form method="post" action="" style="display:inline-flex; align-items:center; gap:6px;">
                                    <input type="hidden" name="action" value="ban">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="text" name="ban_reason" placeholder="Причина блокировки" style="min-width:140px; padding:4px 8px;" maxlength="500">
                                    <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Бан</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn--secondary admin-btn--sm">Разбан</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($u['role'], ['master', 'admin'], true)): ?>
                                <?php
                                $stmt = \App\Database::get()->prepare("SELECT is_verified FROM master_profiles WHERE user_id = ?");
                                $stmt->execute([$u['id']]);
                                $mp = $stmt->fetch();
                                $verified = !empty($mp['is_verified']);
                                ?>
                                <?php if (!$verified): ?>
                                    <form method="post" action="" style="display:inline;" title="Проверенный мастер: у имени будет отображаться отметка на сайте">
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Верифицировать</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="" style="display:inline;">
                                        <input type="hidden" name="action" value="unverify">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="admin-btn admin-btn--secondary admin-btn--sm">Снять верификацию</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</section>
<?php endif; ?>

</main>
</div>
<script>
(function(){
    var bell = document.getElementById('adminNotificationsBell');
    var dropdown = document.getElementById('adminNotificationsDropdown');
    var list = document.getElementById('adminNotificationsList');
    var root = '<?= addslashes($root) ?>';
    if (bell && dropdown) {
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('is-open');
            dropdown.setAttribute('aria-hidden', dropdown.classList.contains('is-open') ? 'false' : 'true');
        });
        document.addEventListener('click', function() {
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
        });
        dropdown.addEventListener('click', function(e) { e.stopPropagation(); });
        if (list) {
            list.addEventListener('click', function(e) {
                var a = e.target.closest('a.admin-notification-item');
                if (a && a.dataset.id) {
                    var form = new FormData();
                    form.append('action', 'mark_read');
                    form.append('id', a.dataset.id);
                    fetch(root + 'notifications_api.php', { method: 'POST', body: form }).catch(function(){});
                }
            });
        }
    }
})();
</script>
<?php
require dirname(__DIR__, 2) . '/templates/layout/footer.php';
