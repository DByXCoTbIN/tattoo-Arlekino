<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Settings;

header('Content-Type: application/json; charset=utf-8');

$config = require dirname(__DIR__, 2) . '/config/config.php';
$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/' : '/';

$siteName = Settings::get('site_name', $config['site']['name'] ?? 'АрлекинО');
$siteUrl = $config['site']['url'] ?? '';
$heroTitle = Settings::get('hero_title', 'Добро пожаловать на арену');
$heroTagline = Settings::get('hero_tagline', 'Смотрите работы мастеров, ставьте оценки и общайтесь в личных сообщениях.');
$sectionMastersTitle = Settings::get('section_masters_title', 'Наши мастера');
$sectionFeedTitle = Settings::get('section_feed_title', 'Лента');
$sectionServicesTitle = Settings::get('section_services_title', 'Услуги студии');

echo json_encode([
    'ok' => true,
    'settings' => [
        'site_name' => $siteName,
        'site_url' => $siteUrl,
        'api_base' => $siteUrl . $base . 'api/',
        'hero_title' => $heroTitle,
        'hero_tagline' => $heroTagline,
        'section_masters_title' => $sectionMastersTitle,
        'section_feed_title' => $sectionFeedTitle,
        'section_services_title' => $sectionServicesTitle,
    ],
]);
