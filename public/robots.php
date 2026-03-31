<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Seo;

header('Content-Type: text/plain; charset=UTF-8');

$config = require dirname(__DIR__) . '/config/config.php';
$sitemap = Seo::absoluteUrl('sitemap.php', [], $config);

$lines = [
    'User-agent: *',
    'Disallow: /admin/',
    'Disallow: /api/',
    'Disallow: /cart',
    'Disallow: /cart/',
    'Disallow: /messages.php',
    'Disallow: /messages_api.php',
    'Disallow: /notifications_api.php',
    'Disallow: /groups_api.php',
    'Disallow: /settings.php',
    'Disallow: /master_calendar.php',
    'Disallow: /booking.php',
    'Disallow: /support.php',
    'Disallow: /blocked.php',
    'Disallow: /logout.php',
    'Disallow: /review_dismiss.php',
    'Disallow: /auth_complete.php',
    'Disallow: /auth_telegram.php',
    'Disallow: /auth_vk.php',
    'Disallow: /auth_vk_callback.php',
    'Allow: /',
    '',
    'Sitemap: ' . $sitemap,
];

echo implode("\n", $lines) . "\n";
