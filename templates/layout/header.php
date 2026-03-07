<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
// Заблокированные пользователи видят страницу блокировки (кроме blocked.php, support.php и чата поддержки)
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$allowBanned = strpos($script, 'blocked.php') !== false || strpos($script, 'support.php') !== false;
if (!$allowBanned && \App\Auth::isBanned()) {
    if (strpos($script, 'messages.php') !== false) {
        $supportGroup = (int)($_GET['group'] ?? 0);
        if ($supportGroup > 0) {
            try {
                $svc = new \App\SupportChatService();
                if ($svc->isSupportChat($supportGroup)) $allowBanned = true;
            } catch (\Throwable $e) { }
        }
    }
    if (!$allowBanned) {
        header('Location: ' . ($base ? rtrim($base, '/') . '/' : '/') . 'blocked.php');
        exit;
    }
}
$user = $user ?? \App\Auth::user();
$bodyClass = $bodyClass ?? '';
$hideReviewRequest = $hideReviewRequest ?? false;
$pendingReviewRequest = null;
if (!$hideReviewRequest && $user && ($user['role'] ?? '') === 'client' && $bodyClass !== 'admin-page' && $bodyClass !== 'blocked-page') {
    try {
        $pendingReviewRequest = (new \App\BookingRepository())->getPendingReviewRequest((int)$user['id']);
    } catch (\Throwable $e) { }
}

// Все ссылки — только путь (относительно текущего хоста), без полного URL
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
$cssPath = $root . 'css/circus-gothic.css';
$adminCssPath = $root . 'css/admin.css';

$cfg = @require __DIR__ . '/../../config/config.php';
$siteName = $siteName ?? ($cfg['site']['name'] ?? 'АрлекинО');

$notificationCount = 0;
$notificationItems = [];
if ($user && isset($user['id'])) {
    try {
        $notifRepo = new \App\NotificationRepository();
        $notificationCount = $notifRepo->countUnreadForUser((int)$user['id'], \App\Auth::isAdmin());
        $notificationItems = $notifRepo->getForUser((int)$user['id'], \App\Auth::isAdmin(), 10);
    } catch (\Throwable $e) { }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle ?? 'Главная') ?> — <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssPath) ?>">
    <?php if ($bodyClass === 'admin-page'): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCssPath) ?>">
    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
<?php if ($bodyClass === 'admin-page' && empty($adminSidebarLayout)): ?>
<header class="admin-top">
    <h1 class="admin-top__title"><span class="arlequino-gothic"><?= htmlspecialchars($siteName) ?></span> <span>— Админка</span></h1>
    <a href="<?= htmlspecialchars($root) ?>">На сайт</a>
</header>
<?php endif; ?>
<?php if ($bodyClass !== 'admin-page'): ?>
<?php
$logoPath = \App\Settings::get('site_logo', '');
$logoRemoveBg = \App\Settings::get('logo_remove_bg', '0') === '1';
$logoColor = \App\Settings::get('logo_color', 'gold');
$logoClasses = 'site-logo-img';
if ($logoRemoveBg) $logoClasses .= ' logo-remove-bg';
if ($logoColor !== 'auto') $logoClasses .= ' logo-color-' . $logoColor;
$isBannedHeader = \App\Auth::isBanned();
try {
    $welcomePhrase = (new \App\WelcomePhraseRepository())->getPhraseForDate(null);
} catch (\Throwable $e) { $welcomePhrase = 'Добро пожаловать'; }
?>
<?php if (!$isBannedHeader && $bodyClass !== 'messages-page'): ?>
<div class="site-banner">
    <div class="site-banner-pattern"></div>
    <div class="site-banner-content wrapper">
        <div class="site-banner-logo">
            <?php if ($logoPath): ?>
                <span class="site-logo-wrap site-banner-logo-wrap">
                    <img src="<?= htmlspecialchars($root . ltrim($logoPath, '/')) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="<?= $logoClasses ?>">
                </span>
            <?php else: ?>
                <div class="site-banner-logo-placeholder"><span class="arlequino-gothic"><?= htmlspecialchars($siteName) ?></span></div>
            <?php endif; ?>
        </div>
        <div class="site-banner-text">
            <h1 class="site-banner-name arlequino-gothic"><?= htmlspecialchars($siteName) ?></h1>
            <p class="site-banner-phrase"><?= htmlspecialchars($welcomePhrase ?? 'Добро пожаловать') ?></p>
        </div>
    </div>
</div>
<?php endif; ?>
<header class="site-header">
    <div class="wrapper site-header__inner">
        <?php if ($isBannedHeader): ?>
        <span class="logo-wrap" style="pointer-events:none;">
            <?php if ($logoPath): ?><span class="site-logo-wrap header-logo-wrap"><img src="<?= htmlspecialchars($root . ltrim($logoPath, '/')) ?>" alt="" class="header-logo <?= $logoClasses ?>"></span><?php endif; ?>
            <span class="logo arlequino-gothic"><?= htmlspecialchars($siteName) ?></span>
        </span>
        <?php else: ?>
        <a href="<?= htmlspecialchars($root) ?>" class="logo-wrap">
            <?php if ($logoPath): ?><span class="site-logo-wrap header-logo-wrap"><img src="<?= htmlspecialchars($root . ltrim($logoPath, '/')) ?>" alt="" class="header-logo <?= $logoClasses ?>"></span><?php endif; ?>
            <span class="logo arlequino-gothic"><?= htmlspecialchars($siteName) ?></span>
        </a>
        <?php endif; ?>
        <?php if (!$isBannedHeader): ?>
        <input type="checkbox" id="navToggle" class="nav-toggle-input" aria-label="Меню">
        <label for="navToggle" class="nav-toggle-label" aria-label="Открыть меню"><span></span><span></span><span></span></label>
        <nav class="nav-main">
            <a href="<?= htmlspecialchars($root) ?>">Главная</a>
            <a href="<?= htmlspecialchars($root . 'masters.php') ?>">Мастера</a>
            <?php if ($user): ?>
                <?php if (\App\Auth::isMaster() && $user): ?>
                <a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$user['id']) ?>">Моя страница</a>
                <a href="<?= htmlspecialchars($root . 'master_calendar.php') ?>">Календарь</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($root . 'messages.php') ?>">Сообщения</a>
                <a href="<?= htmlspecialchars($root . 'support.php') ?>">Поддержка</a>
                <?php if (\App\Auth::isAdmin()): ?>
                    <a href="<?= htmlspecialchars($root . 'admin/') ?>">Админка</a>
                <?php endif; ?>
                <div class="notifications-wrap">
                    <button type="button" class="notifications-bell btn" id="notificationsBell" aria-label="Уведомления" title="Уведомления">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($notificationCount > 0): ?><span class="notifications-badge"><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span><?php endif; ?>
                    </button>
                    <div class="notifications-dropdown" id="notificationsDropdown" aria-hidden="true">
                        <div class="notifications-dropdown__header">Уведомления</div>
                        <div class="notifications-dropdown__list" id="notificationsList">
                            <?php
                            $formatNotif = function ($n) use ($root) {
                                $type = $n['type'] ?? '';
                                $refId = (int)($n['ref_id'] ?? 0);
                                $data = !empty($n['data']) ? (json_decode($n['data'], true) ?: []) : [];
                                $url = $root . 'messages.php';
                                $title = 'Уведомление';
                                $text = '';
                                switch ($type) {
                                    case 'message': $title = 'Новое сообщение'; $text = 'От ' . htmlspecialchars($n['from_name'] ?? 'Кто-то'); $url = $root . 'messages.php?conv=' . $refId; break;
                                    case 'master_request': $title = 'Заявка на роль мастера'; $text = htmlspecialchars(($data['full_name'] ?? '') . ' — ' . ($data['email'] ?? '')); $url = $root . 'admin/?page=requests'; break;
                                    case 'booking_request': $title = 'Запрос на запись'; $text = htmlspecialchars(($data['client_name'] ?? 'Клиент') . ' на ' . ($data['date'] ?? '')); $url = $root . 'master_calendar.php'; break;
                                    case 'booking_reminder': $title = 'Напоминание о записи'; $text = htmlspecialchars(($data['date'] ?? '') . ' ' . ($data['slot'] ?? '') . ' — ' . ($data['client_name'] ?? 'Клиент')); $url = $root . 'master_calendar.php?date=' . ($data['date'] ?? ''); break;
                                    case 'support_message': $title = 'Сообщение в поддержке'; $text = htmlspecialchars(mb_substr($data['preview'] ?? 'Новое сообщение', 0, 80)); $url = $root . 'messages.php?group=' . $refId; break;
                                    case 'rating_pending': $title = 'Новый отзыв на модерации'; $text = htmlspecialchars(mb_substr($data['comment_preview'] ?? '', 0, 60) . ' (★' . ($data['value'] ?? 0) . ')'); $url = $root . 'admin/?page=content'; break;
                                    default: $text = htmlspecialchars($n['data'] ?? '');
                                }
                                return ['id' => (int)$n['id'], 'title' => $title, 'text' => $text, 'url' => $url];
                            };
                            foreach ($notificationItems as $n):
                                $item = $formatNotif($n);
                            ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>" class="notification-item" data-id="<?= $item['id'] ?>">
                                <span class="notification-item__title"><?= $item['title'] ?></span>
                                <span class="notification-item__text"><?= $item['text'] ?></span>
                            </a>
                            <?php endforeach; ?>
                            <?php if (empty($notificationItems)): ?>
                            <div class="notification-item notification-item--empty">Нет новых уведомлений</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <span style="color: var(--text-muted);"><?= htmlspecialchars($user['full_name']) ?></span>
                <button type="button" class="theme-toggle btn" id="themeToggle" aria-label="Переключить тему" title="Тема">Свет</button>
                <a href="<?= htmlspecialchars($root . 'logout.php') ?>" class="btn">Выход</a>
            <?php else: ?>
                <button type="button" class="theme-toggle btn" id="themeToggle" aria-label="Переключить тему" title="Тема">Свет</button>
                <a href="<?= htmlspecialchars($root . 'login.php') ?>" class="btn">Вход</a>
                <a href="<?= htmlspecialchars($root . 'register.php') ?>" class="btn btn-primary">Регистрация</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</header>
<script>
(function(){ var k='circus_theme'; var L='light'; var D='dark'; function set(t){ document.documentElement.setAttribute('data-theme', t||D); var b=document.getElementById('themeToggle'); if(b) b.textContent=(t===L)?'Тёмная':'Свет'; } var s=localStorage.getItem(k); set(s===L||s===D?s:D); var b=document.getElementById('themeToggle'); if(b) b.addEventListener('click',function(){ var n=document.documentElement.getAttribute('data-theme')===L?D:L; localStorage.setItem(k,n); set(n); }); })();
</script>
<script>
(function(){
    var bell = document.getElementById('notificationsBell');
    var dropdown = document.getElementById('notificationsDropdown');
    var list = document.getElementById('notificationsList');
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
                var a = e.target.closest('a.notification-item');
                if (a && a.dataset.id) {
                    var form = new FormData();
                    form.append('action', 'mark_read');
                    form.append('id', a.dataset.id);
                    fetch('<?= $root ?>notifications_api.php', { method: 'POST', body: form }).catch(function(){});
                }
            });
        }
    }
})();
</script>
<?php endif; ?>
<?php if ($bodyClass !== 'admin-page' || empty($adminSidebarLayout)): ?>
<main class="<?= $bodyClass === 'admin-page' ? 'admin-main' : ($bodyClass === 'messages-page' ? 'messages-main' : 'wrapper') ?>"<?= $bodyClass === 'admin-page' ? '' : ($bodyClass === 'messages-page' ? '' : ' style="padding-top: 28px; padding-bottom: 28px;"') ?>>
<?php endif; ?>
