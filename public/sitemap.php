<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Seo;
use App\Repositories\UserRepo;
use App\ServiceRepository;

header('Content-Type: application/xml; charset=UTF-8');

$config = require dirname(__DIR__) . '/config/config.php';

$urls = [];
$add = static function (string $loc, ?string $changefreq = 'weekly', ?float $priority = 0.8) use (&$urls): void {
    $urls[] = ['loc' => $loc, 'changefreq' => $changefreq, 'priority' => $priority];
};

$add(Seo::absoluteUrl('', [], $config), 'daily', 1.0);
$add(Seo::absoluteUrl('masters.php', [], $config), 'daily', 0.9);
$add(Seo::absoluteUrl('login.php', [], $config), 'monthly', 0.3);
$add(Seo::absoluteUrl('register.php', [], $config), 'monthly', 0.4);

try {
    $svc = new ServiceRepository();
    foreach ($svc->listAll() as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $add(Seo::absoluteUrl('masters.php', ['service' => $id], $config), 'weekly', 0.7);
        }
    }
} catch (\Throwable $e) {
}

try {
    $repo = new UserRepo();
    foreach ($repo->getPublicMasterIds() as $mid) {
        $add(Seo::absoluteUrl('master.php', ['id' => $mid], $config), 'weekly', 0.8);
        $add(Seo::absoluteUrl('reviews.php', ['id' => $mid], $config), 'weekly', 0.65);
    }
} catch (\Throwable $e) {
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . Seo::xmlEscape($u['loc']) . '</loc>' . "\n";
    if (!empty($u['changefreq'])) {
        echo '    <changefreq>' . Seo::xmlEscape((string) $u['changefreq']) . '</changefreq>' . "\n";
    }
    if (isset($u['priority'])) {
        echo '    <priority>' . Seo::xmlEscape((string) $u['priority']) . '</priority>' . "\n";
    }
    echo '  </url>' . "\n";
}
echo '</urlset>';
