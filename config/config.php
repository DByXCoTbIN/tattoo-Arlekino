<?php

declare(strict_types=1);

return [
    'db' => [
        'driver'      => $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'mysql',
        'host'        => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
        'dbname'      => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'circus_social',
        'charset'     => 'utf8mb4',
        'user'        => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root',
        'password'    => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '',
        'sqlite_path' => __DIR__ . '/../data/circus.db',
    ],
    'site' => [
        'name'        => 'АрлекинО',
//        'name'        => 'ArlekinO',
        'url'         => $_ENV['SITE_URL'] ?? getenv('SITE_URL') ?: 'http://localhost',
        'timezone'    => 'Europe/Moscow',
        'upload_path' => __DIR__ . '/../public/uploads',
        'upload_url'  => '/uploads',
    ],
    'session' => [
        'name' => 'CIRCUS_SESSION',
        'lifetime' => 86400 * 7,
    ],
    'oauth' => [
        'telegram' => [
            'bot_username' => $_ENV['TELEGRAM_BOT_USERNAME'] ?? getenv('TELEGRAM_BOT_USERNAME') ?: '',
            'bot_token'    => $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '',
        ],
        'vk' => [
            'client_id'     => $_ENV['VK_CLIENT_ID'] ?? getenv('VK_CLIENT_ID') ?: '',
            'client_secret' => $_ENV['VK_CLIENT_SECRET'] ?? getenv('VK_CLIENT_SECRET') ?: '',
        ],
    ],
    'seo' => [
        'yandex_metrika_id'   => trim((string) ($_ENV['YANDEX_METRIKA_ID'] ?? getenv('YANDEX_METRIKA_ID') ?: '')),
        'yandex_verification' => trim((string) ($_ENV['YANDEX_VERIFICATION'] ?? getenv('YANDEX_VERIFICATION') ?: '')),
        'google_verification' => trim((string) ($_ENV['GOOGLE_VERIFICATION'] ?? getenv('GOOGLE_VERIFICATION') ?: '')),
    ],
];
