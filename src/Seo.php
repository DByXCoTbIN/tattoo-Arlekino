<?php

declare(strict_types=1);

namespace App;

/**
 * Канонические абсолютные URL для sitemap, meta и редиректов.
 * Задайте в .env SITE_URL как схему + хост (+ порт), без пути, если приложение в подкаталоге — путь возьмётся из BASE_PATH.
 */
final class Seo
{
    public static function publicBase(?array $config = null): string
    {
        $config = $config ?? require __DIR__ . '/../config/config.php';
        $origin = rtrim((string) ($config['site']['url'] ?? ''), '/');
        $bp = defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/\\') : '';
        if ($bp === '.' || $bp === (string) DIRECTORY_SEPARATOR) {
            $bp = '';
        }
        return $origin . ($bp !== '' ? $bp : '');
    }

    /** Путь к скрипту относительно корня сайта, например masters.php или пусто для главной. */
    public static function absoluteUrl(string $scriptPath, array $query = [], ?array $config = null): string
    {
        $base = rtrim(self::publicBase($config), '/');
        $scriptPath = trim($scriptPath, '/');
        if ($scriptPath === '' || $scriptPath === '/') {
            $url = $base . '/';
        } else {
            $url = $base . '/' . $scriptPath;
        }
        if ($query !== []) {
            ksort($query);
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    public static function xmlEscape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Обрезка для meta description. */
    public static function metaSnippet(string $text, int $maxLen = 158): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $maxLen - 1)) . '…';
    }
}
