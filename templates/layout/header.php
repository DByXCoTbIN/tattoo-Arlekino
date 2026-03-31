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
$seoCfg = $cfg['seo'] ?? [];
$pageDescription = $pageDescription ?? null;
$canonicalUrl = $canonicalUrl ?? null;
$pageRobots = $pageRobots ?? (($bodyClass ?? '') === 'admin-page' ? 'noindex, nofollow' : null);
$ogImage = $ogImage ?? null;
$structuredData = $structuredData ?? null;
$logoSize = (int)\App\Settings::get('logo_size', '96');
$logoSize = max(48, min(200, $logoSize));
$logoSizeHeader = max(20, min(82, (int)round($logoSize * 0.36)));
$logoCssVars = '--logo-size-banner:' . $logoSize . 'px;--logo-size-header:' . $logoSizeHeader . 'px;';

$logoPath = \App\Settings::get('site_logo', '');
$logoRemoveBg = \App\Settings::get('logo_remove_bg', '0') === '1';

$faviconColorAllowed = ['auto', 'gold', 'white', 'accent', 'silver', 'black', 'emerald', 'azure', 'violet'];
$faviconColorSetting = \App\Settings::get('favicon_color', 'auto');
if (!in_array($faviconColorSetting, $faviconColorAllowed, true)) {
    $faviconColorSetting = 'auto';
}
$faviconFilterCssMap = [
    'auto' => 'none',
    'gold' => 'brightness(0) saturate(100%) invert(72%) sepia(35%) saturate(400%) hue-rotate(8deg)',
    'white' => 'brightness(0) invert(1)',
    'accent' => 'brightness(0) saturate(100%) invert(12%) sepia(90%) saturate(600%) hue-rotate(315deg)',
    'silver' => 'brightness(0) saturate(100%) invert(88%) sepia(4%) saturate(200%) hue-rotate(170deg)',
    'black' => 'brightness(0) saturate(100%) invert(8%) sepia(8%) saturate(150%) hue-rotate(180deg)',
    'emerald' => 'brightness(0) saturate(100%) invert(37%) sepia(52%) saturate(880%) hue-rotate(118deg)',
    'azure' => 'brightness(0) saturate(100%) invert(50%) sepia(73%) saturate(620%) hue-rotate(171deg)',
    'violet' => 'brightness(0) saturate(100%) invert(29%) sepia(40%) saturate(1120%) hue-rotate(253deg)',
];
$faviconFilterCss = $faviconFilterCssMap[$faviconColorSetting] ?? 'none';
$faviconUseCanvas = ($logoPath !== '') && ($logoRemoveBg || $faviconColorSetting !== 'auto');

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
    <?php if ($logoPath !== ''):
        $faviconExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $faviconMime = match ($faviconExt) {
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
        $faviconHref = $root . ltrim($logoPath, '/');
        ?>
    <?php if (!$faviconUseCanvas): ?>
    <link rel="icon" type="<?= htmlspecialchars($faviconMime) ?>" href="<?= htmlspecialchars($faviconHref) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconHref) ?>">
    <?php else: ?>
    <script>
    (function () {
        var u = <?= json_encode($faviconHref, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
        var fallbackMime = <?= json_encode($faviconMime, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
        var removeBg = <?= $logoRemoveBg ? 'true' : 'false' ?>;
        var filterCss = <?= json_encode($faviconFilterCss, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
        function setFaviconLinks(href, mime) {
            [['icon', mime], ['apple-touch-icon', null]].forEach(function (pair) {
                var rel = pair[0], type = pair[1];
                var el = document.querySelector('link[rel="' + rel + '"]');
                if (!el) {
                    el = document.createElement('link');
                    el.rel = rel;
                    document.head.appendChild(el);
                }
                el.href = href;
                if (type) el.type = type; else el.removeAttribute('type');
            });
        }
        function removeLightBackground(ctx, w, h) {
            var imageData;
            try {
                imageData = ctx.getImageData(0, 0, w, h);
            } catch (e) {
                return false;
            }
            var data = imageData.data;
            for (var i = 0; i < data.length; i += 4) {
                var r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
                if (a === 0) continue;
                var max = Math.max(r, g, b), min = Math.min(r, g, b), sat = max - min, bright = (r + g + b) / 3;
                if (bright >= 244 && sat <= 18) {
                    data[i + 3] = 0;
                    continue;
                }
                if (bright >= 226 && sat <= 32) {
                    var fade = (bright - 226) / 28;
                    var alphaMul = 1 - Math.max(0, Math.min(1, fade));
                    data[i + 3] = Math.round(a * alphaMul);
                }
            }
            ctx.putImageData(imageData, 0, 0);
            return true;
        }
        function finish(canvas) {
            var w = canvas.width, h = canvas.height;
            if (filterCss === 'none') {
                setFaviconLinks(canvas.toDataURL('image/png'), 'image/png');
                return;
            }
            var out = document.createElement('canvas');
            out.width = w;
            out.height = h;
            var octx = out.getContext('2d');
            if (!octx || typeof octx.filter === 'undefined') {
                setFaviconLinks(canvas.toDataURL('image/png'), 'image/png');
                return;
            }
            octx.filter = filterCss;
            octx.drawImage(canvas, 0, 0);
            setFaviconLinks(out.toDataURL('image/png'), 'image/png');
        }
        var im = new Image();
        im.onload = function () {
            if (!im.naturalWidth || !im.naturalHeight) {
                setFaviconLinks(u, fallbackMime);
                return;
            }
            var c = document.createElement('canvas');
            c.width = im.naturalWidth;
            c.height = im.naturalHeight;
            var ctx = c.getContext('2d', { willReadFrequently: true });
            if (!ctx) {
                setFaviconLinks(u, fallbackMime);
                return;
            }
            ctx.drawImage(im, 0, 0);
            if (removeBg) {
                removeLightBackground(ctx, c.width, c.height);
            }
            finish(c);
        };
        im.onerror = function () { setFaviconLinks(u, fallbackMime); };
        im.src = u;
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($pageDescription)): ?>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($pageRobots)): ?>
    <meta name="robots" content="<?= htmlspecialchars($pageRobots) ?>">
    <?php endif; ?>
    <?php if (!empty($canonicalUrl)): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars(($pageTitle ?? 'Главная') . ' — ' . $siteName) ?>">
    <?php if (!empty($pageDescription)): ?>
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($canonicalUrl)): ?>
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <?php endif; ?>
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <?php if (!empty($seoCfg['yandex_verification'])): ?>
    <meta name="yandex-verification" content="<?= htmlspecialchars($seoCfg['yandex_verification']) ?>">
    <?php endif; ?>
    <?php if (!empty($seoCfg['google_verification'])): ?>
    <meta name="google-site-verification" content="<?= htmlspecialchars($seoCfg['google_verification']) ?>">
    <?php endif; ?>
    <?php if (is_array($structuredData) && $structuredData !== []): ?>
    <?php
    $ldJson = json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
    if ($ldJson !== false):
    ?>
    <script type="application/ld+json"><?= $ldJson ?></script>
    <?php endif; ?>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssPath) ?>">
    <?php if ($bodyClass === 'admin-page'): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCssPath) ?>">
    <?php endif; ?>
    <?php
    $ymId = (int) ($seoCfg['yandex_metrika_id'] ?? 0);
    if ($ymId > 0 && ($bodyClass ?? '') !== 'admin-page'):
    ?>
    <script>
    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();
    for (var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}
    k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
    (window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
    ym(<?= $ymId ?>, "init", { clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true });
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/<?= $ymId ?>" style="position:absolute;left:-9999px" alt=""></div></noscript>
    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>" style="<?= htmlspecialchars($logoCssVars) ?>">
<?php if ($bodyClass === 'admin-page' && empty($adminSidebarLayout)): ?>
<header class="admin-top">
    <h1 class="admin-top__title"><span class="arlequino-gothic"><?= htmlspecialchars($siteName) ?></span> <span>— Админка</span></h1>
    <a href="<?= htmlspecialchars($root) ?>">На сайт</a>
</header>
<?php endif; ?>
<?php if ($bodyClass !== 'admin-page'): ?>
<?php
$allowedLogoColors = ['auto', 'gold', 'white', 'accent', 'silver', 'black', 'emerald', 'azure', 'violet'];
$legacyLogoColor = \App\Settings::get('logo_color', 'gold');
$logoColorDark = \App\Settings::get('logo_color_dark', $legacyLogoColor ?: 'gold');
$logoColorLight = \App\Settings::get('logo_color_light', $legacyLogoColor ?: 'gold');
if (!in_array($logoColorDark, $allowedLogoColors, true)) $logoColorDark = 'gold';
if (!in_array($logoColorLight, $allowedLogoColors, true)) $logoColorLight = 'gold';
$logoClasses = 'site-logo-img';
if ($logoRemoveBg) $logoClasses .= ' logo-remove-bg';
$logoClasses .= ' logo-color-dark-' . $logoColorDark . ' logo-color-light-' . $logoColorLight;
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
                <span class="nav-user-name"><?= htmlspecialchars($user['full_name']) ?></span>
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
(function () {
    function processLogoImage(img) {
        if (!img || img.dataset.logoBgProcessed === '1') return;
        var apply = function () {
            if (!img.naturalWidth || !img.naturalHeight) return;
            var canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            var ctx = canvas.getContext('2d', { willReadFrequently: true });
            if (!ctx) return;
            ctx.drawImage(img, 0, 0);
            var imageData;
            try {
                imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            } catch (e) {
                return;
            }
            var data = imageData.data;
            for (var i = 0; i < data.length; i += 4) {
                var r = data[i];
                var g = data[i + 1];
                var b = data[i + 2];
                var a = data[i + 3];
                if (a === 0) continue;
                var max = Math.max(r, g, b);
                var min = Math.min(r, g, b);
                var sat = max - min;
                var bright = (r + g + b) / 3;
                if (bright >= 244 && sat <= 18) {
                    data[i + 3] = 0;
                    continue;
                }
                if (bright >= 226 && sat <= 32) {
                    var fade = (bright - 226) / 28;
                    var alphaMul = 1 - Math.max(0, Math.min(1, fade));
                    data[i + 3] = Math.round(a * alphaMul);
                }
            }
            ctx.putImageData(imageData, 0, 0);
            img.dataset.logoBgProcessed = '1';
            img.src = canvas.toDataURL('image/png');
        };
        if (img.complete) {
            apply();
        } else {
            img.addEventListener('load', apply, { once: true });
        }
    }
    function init() {
        var logos = document.querySelectorAll('img.logo-remove-bg');
        for (var i = 0; i < logos.length; i++) processLogoImage(logos[i]);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
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
