<?php

declare(strict_types=1);

namespace App;

/**
 * OAuth: Telegram Login Widget, VK
 * Конфиг: config['oauth']['telegram']['bot_token'], config['oauth']['vk']['client_id'/'client_secret']
 */
class OAuthService
{
    private const OAUTH_PASSWORD_PLACEHOLDER = 'oauth:';

    public static function isOAuthUser(array $user): bool
    {
        $hash = $user['password_hash'] ?? '';
        return $hash === '' || str_starts_with($hash, self::OAUTH_PASSWORD_PLACEHOLDER);
    }

    public static function getConfig(): array
    {
        $config = require dirname(__DIR__) . '/config/config.php';
        return $config['oauth'] ?? [];
    }

    /** Текущий origin приложения (предпочтительно из запроса, fallback — site.url). */
    public static function getRuntimeOrigin(): string
    {
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
        $scheme = $isHttps ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            return $scheme . '://' . $host;
        }
        $config = require dirname(__DIR__) . '/config/config.php';
        return rtrim((string)($config['site']['url'] ?? 'http://localhost'), '/');
    }

    /** Построить абсолютный URL на текущем хосте. */
    public static function buildAbsoluteUrl(string $path): string
    {
        return rtrim(self::getRuntimeOrigin(), '/') . '/' . ltrim($path, '/');
    }

    /** Telegram Login Widget: проверка данных от виджета */
    public static function verifyTelegramData(array $data, string $botToken): bool
    {
        $hash = $data['hash'] ?? '';
        if (!$hash) return false;

        $check = [];
        foreach ($data as $k => $v) {
            if ($k === 'hash' || $v === null || $v === '') continue;
            $check[] = $k . '=' . $v;
        }
        sort($check);
        $dataCheckString = implode("\n", $check);
        $secretKey = hash('sha256', $botToken, true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($calculatedHash, $hash)) return false;

        $authDate = (int)($data['auth_date'] ?? 0);
        if ($authDate < time() - 86400) return false; // не старше 24 ч

        return true;
    }

    /** Данные пользователя из Telegram Widget */
    public static function getTelegramUserData(array $data): array
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $fullName = trim($firstName . ' ' . $lastName) ?: 'Пользователь';
        $photoUrl = $data['photo_url'] ?? null;
        return [
            'oauth_id' => (string)($data['id'] ?? ''),
            'full_name' => $fullName,
            'avatar_url' => $photoUrl,
            'phone' => null,
        ];
    }

    /** URL для VK OAuth */
    public static function getVkAuthUrl(string $redirectUri, string $state = ''): ?string
    {
        $cfg = self::getConfig()['vk'] ?? null;
        if (!$cfg || empty($cfg['client_id'])) return null;

        $params = [
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => '2,4,4194304', // offline, photos, email
            'v' => '5.199',
        ];
        if ($state !== '') $params['state'] = $state;

        return 'https://oauth.vk.com/authorize?' . http_build_query($params);
    }

    /** Обмен code на token и получение данных пользователя VK */
    public static function getVkUserData(string $code, string $redirectUri): ?array
    {
        $cfg = self::getConfig()['vk'] ?? null;
        if (!$cfg || empty($cfg['client_id']) || empty($cfg['client_secret'])) return null;

        $url = 'https://oauth.vk.com/access_token?' . http_build_query([
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) return null;

        $json = json_decode($resp, true);
        if (!$json || isset($json['error'])) return null;

        $accessToken = $json['access_token'] ?? null;
        $userId = $json['user_id'] ?? null;
        $email = $json['email'] ?? null;
        $phone = null; // VK редко отдаёт телефон

        if (!$accessToken || !$userId) return null;

        $apiUrl = 'https://api.vk.com/method/users.get?' . http_build_query([
            'user_ids' => $userId,
            'fields' => 'photo_max_orig',
            'access_token' => $accessToken,
            'v' => '5.199',
        ]);

        $apiResp = @file_get_contents($apiUrl, false, $ctx);
        $apiData = $apiResp ? json_decode($apiResp, true) : null;
        $user = $apiData['response'][0] ?? null;

        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: 'Пользователь';
        $photoUrl = $user['photo_max_orig'] ?? null;

        return [
            'oauth_id' => (string)$userId,
            'full_name' => $fullName,
            'avatar_url' => $photoUrl,
            'phone' => $phone,
            'email' => $email,
        ];
    }

    public static function getPlaceholderEmail(string $provider, string $oauthId): string
    {
        return 'oauth_' . $provider . '_' . $oauthId . '@placeholder.local';
    }

    public static function getOAuthPasswordPlaceholder(): string
    {
        return self::OAUTH_PASSWORD_PLACEHOLDER;
    }
}
