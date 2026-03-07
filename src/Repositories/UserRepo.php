<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

class UserRepo
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND is_banned = 0");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findByOAuth(string $provider, string $oauthId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ?");
            $stmt->execute([$provider, $oauthId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function findByPhone(string $phone): ?array
    {
        $phone = trim($phone);
        if ($phone === '') return null;
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Создание клиента через OAuth (имя, аватар, телефон обязателен) */
    public function createOAuth(string $provider, string $oauthId, string $fullName, ?string $avatarUrl, string $phone): int
    {
        $email = \App\OAuthService::getPlaceholderEmail($provider, $oauthId);
        $passwordHash = \App\OAuthService::getOAuthPasswordPlaceholder();

        $stmt = $this->pdo->prepare("INSERT INTO users (email, password_hash, role, full_name, avatar_path, phone, oauth_provider, oauth_id) VALUES (?, ?, 'client', ?, NULL, ?, ?, ?)");
        $stmt->execute([$email, $passwordHash, $fullName, $phone, $provider, $oauthId]);
        $id = (int) $this->pdo->lastInsertId();
        if ($avatarUrl && $id > 0) {
            $avatarPath = $this->downloadAvatar($avatarUrl, $id);
            if ($avatarPath) $this->update($id, ['avatar_path' => $avatarPath]);
        }
        return $id;
    }

    public function updatePhone(int $userId, string $phone): void
    {
        try {
            $this->pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$phone, $userId]);
        } catch (\Throwable $e) { }
    }

    private function downloadAvatar(string $url, int $userId): ?string
    {
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'follow_redirect' => true]]);
        $data = @file_get_contents($url, false, $ctx);
        if (!$data || strlen($data) > 5 * 1024 * 1024) return null;

        $ext = 'jpg';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($data);
        if ($mime === 'image/png') $ext = 'png';
        elseif ($mime === 'image/gif') $ext = 'gif';
        elseif ($mime === 'image/webp') $ext = 'webp';

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $uploadPath = $config['site']['upload_path'] . '/avatars';
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0755, true);

        $filename = $userId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $fullPath = $uploadPath . '/' . $filename;
        if (file_put_contents($fullPath, $data) === false) return null;

        return '/uploads/avatars/' . $filename;
    }

    public function create(string $email, string $passwordHash, string $role, string $fullName, ?string $phone = null): int
    {
        $requestMaster = ($role === 'master');
        $lastError = null;
        $attempts = [
            'with_phone_role' => $requestMaster
                ? ["INSERT INTO users (email, password_hash, role, full_name, phone, role_requested_at) VALUES (?, ?, 'client', ?, ?, NOW())", [$email, $passwordHash, $fullName, $phone]]
                : ["INSERT INTO users (email, password_hash, role, full_name, phone) VALUES (?, ?, ?, ?, ?)", [$email, $passwordHash, $role, $fullName, $phone]],
            'no_phone' => $requestMaster
                ? ["INSERT INTO users (email, password_hash, role, full_name, role_requested_at) VALUES (?, ?, 'client', ?, NOW())", [$email, $passwordHash, $fullName]]
                : ["INSERT INTO users (email, password_hash, role, full_name) VALUES (?, ?, ?, ?)", [$email, $passwordHash, $role, $fullName]],
            'no_role_requested' => $requestMaster
                ? ["INSERT INTO users (email, password_hash, role, full_name) VALUES (?, ?, 'client', ?)", [$email, $passwordHash, $fullName]]
                : null,
        ];
        foreach ($attempts as $attempt) {
            if ($attempt === null) continue;
            try {
                $this->pdo->prepare($attempt[0])->execute($attempt[1]);
                $lastError = null;
                break;
            } catch (\Throwable $e) {
                $lastError = $e;
                $msg = $e->getMessage();
                if (strpos($msg, 'phone') === false && strpos($msg, 'column') === false && strpos($msg, 'role_requested') === false && strpos($msg, 'Unknown') === false) {
                    throw $e;
                }
            }
        }
        if ($lastError !== null) {
            throw $lastError;
        }
        $id = (int) $this->pdo->lastInsertId();
        if ($requestMaster) {
            try {
                (new \App\NotificationRepository())->add(null, 'master_request', $id, $id, json_encode(['full_name' => $fullName, 'email' => $email]));
            } catch (\Throwable $e) { /* таблица уведомлений может отсутствовать до миграции */ }
        } elseif (in_array($role, ['master', 'admin'], true)) {
            try {
                $this->pdo->prepare("INSERT INTO master_profiles (user_id) VALUES (?)")->execute([$id]);
            } catch (\Throwable $e) { }
        }
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['full_name', 'avatar_path', 'password_hash'];
        $set = [];
        $vals = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $set[] = "`$k` = ?";
                $vals[] = $v;
            }
        }
        if ($set === []) return;
        $vals[] = $id;
        $this->pdo->prepare("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?")->execute($vals);
    }

    /** Список мастеров для публичного отображения: верифицированные мастера и все админы (админы показываются всегда). */
    public function getMasters(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare("
            SELECT u.*, mp.bio, mp.specialization, mp.rating_sum, mp.rating_count, mp.is_verified,
                   CASE WHEN mp.rating_count > 0 THEN ROUND(mp.rating_sum / mp.rating_count, 1) ELSE 0 END AS rating_avg
            FROM users u
            LEFT JOIN master_profiles mp ON mp.user_id = u.id
            WHERE u.role IN ('master', 'admin') AND u.is_banned = 0
              AND (COALESCE(mp.is_verified, 0) = 1 OR u.role = 'admin')
            ORDER BY mp.rating_count DESC, u.created_at DESC
            LIMIT " . $limit . " OFFSET " . $offset . "
        ");
        $stmt->execute([]);
        return $stmt->fetchAll();
    }

    /**
     * Профиль мастера.
     * @param bool $verifiedOnly при true — только верифицированные и админы (админы показываются всегда)
     */
    public function getMasterProfile(int $userId, bool $verifiedOnly = false): ?array
    {
        $verifiedCond = $verifiedOnly ? " AND (COALESCE(mp.is_verified, 0) = 1 OR u.role = 'admin')" : "";
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, mp.bio, mp.specialization, mp.phone, mp.instagram, mp.vk, mp.telegram, mp.youtube, mp.max_link, mp.rating_sum, mp.rating_count, mp.is_verified, mp.banner_path,
                       CASE WHEN mp.rating_count > 0 THEN ROUND(mp.rating_sum / mp.rating_count, 1) ELSE 0 END AS rating_avg
                FROM users u
                LEFT JOIN master_profiles mp ON mp.user_id = u.id
                WHERE u.id = ? AND u.role IN ('master', 'admin') AND u.is_banned = 0" . $verifiedCond . "
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            $stmt = $this->pdo->prepare("
                SELECT u.*, mp.bio, mp.specialization, mp.rating_sum, mp.rating_count, mp.is_verified,
                       CASE WHEN mp.rating_count > 0 THEN ROUND(mp.rating_sum / mp.rating_count, 1) ELSE 0 END AS rating_avg
                FROM users u
                LEFT JOIN master_profiles mp ON mp.user_id = u.id
                WHERE u.id = ? AND u.role IN ('master', 'admin') AND u.is_banned = 0" . $verifiedCond . "
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch() ?: null;
            if ($row !== null) {
                $row['banner_path'] = $row['banner_path'] ?? null;
                $row['phone'] = $row['phone'] ?? null;
                $row['instagram'] = $row['instagram'] ?? null;
                $row['vk'] = $row['vk'] ?? null;
                $row['telegram'] = $row['telegram'] ?? null;
                $row['youtube'] = $row['youtube'] ?? null;
                $row['max_link'] = $row['max_link'] ?? null;
            }
            return $row;
        }
    }

    public function setMasterBanner(int $userId, ?string $path): void
    {
        try {
            $this->pdo->prepare("UPDATE master_profiles SET banner_path = ? WHERE user_id = ?")->execute([$path, $userId]);
        } catch (\Throwable $e) { }
    }

    public function updateMasterProfile(
        int $userId,
        string $bio = '',
        string $specialization = '',
        string $phone = '',
        string $instagram = '',
        string $vk = '',
        string $telegram = '',
        string $youtube = '',
        string $maxLink = ''
    ): void
    {
        if (Database::isSqlite()) {
            $this->pdo->prepare("INSERT INTO master_profiles (user_id, bio, specialization) VALUES (?, ?, ?) ON CONFLICT(user_id) DO UPDATE SET bio = excluded.bio, specialization = excluded.specialization")->execute([$userId, $bio, $specialization]);
        } else {
            $this->pdo->prepare("INSERT INTO master_profiles (user_id, bio, specialization) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE bio = VALUES(bio), specialization = VALUES(specialization)")->execute([$userId, $bio, $specialization]);
        }
        try {
            $updates = ['phone = ?', 'instagram = ?', 'vk = ?', 'telegram = ?', 'youtube = ?', 'max_link = ?'];
            $this->pdo->prepare("UPDATE master_profiles SET " . implode(', ', $updates) . " WHERE user_id = ?")->execute([$phone, $instagram, $vk, $telegram, $youtube, $maxLink, $userId]);
        } catch (\Throwable $e) { /* контакты могут отсутствовать до миграций */ }
    }

    /** Получить пользователя по ID для отображения (в т.ч. забаненных — в диалогах) */
    public function findByIdForDisplay(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, full_name, avatar_path, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Список пользователей для админки.
     * @param int $ensureFirstUserId Если передан, этот пользователь всегда будет первым в списке (основной админ не теряется).
     */
    public function getAllForAdmin(int $limit = 100, int $offset = 0, ?int $ensureFirstUserId = null): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        if ($ensureFirstUserId !== null && $ensureFirstUserId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT u.*, mp.rating_count FROM users u
                LEFT JOIN master_profiles mp ON mp.user_id = u.id
                ORDER BY (u.id = ?) DESC, u.created_at DESC LIMIT " . $limit . " OFFSET " . $offset . "
            ");
            $stmt->execute([$ensureFirstUserId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT u.*, mp.rating_count FROM users u
                LEFT JOIN master_profiles mp ON mp.user_id = u.id
                ORDER BY u.created_at DESC LIMIT " . $limit . " OFFSET " . $offset . "
            ");
            $stmt->execute([]);
        }
        return $stmt->fetchAll();
    }

    public function setBanned(int $userId, bool $banned, ?string $reason = null): void
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET is_banned = ?, ban_reason = ? WHERE id = ?");
            $stmt->execute([$banned ? 1 : 0, $banned ? ($reason ?? null) : null, $userId]);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'ban_reason') !== false || strpos($e->getMessage(), 'column') !== false) {
                $this->pdo->prepare("UPDATE users SET is_banned = ? WHERE id = ?")->execute([$banned ? 1 : 0, $userId]);
            } else {
                throw $e;
            }
        }
    }

    public function setVerified(int $userId, bool $verified): void
    {
        $v = $verified ? 1 : 0;
        if (Database::isSqlite()) {
            $this->pdo->prepare("INSERT INTO master_profiles (user_id, is_verified) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET is_verified = excluded.is_verified")->execute([$userId, $v]);
        } else {
            $this->pdo->prepare("INSERT INTO master_profiles (user_id, is_verified) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_verified = VALUES(is_verified)")->execute([$userId, $v]);
        }
    }

    public function setRole(int $userId, string $role): void
    {
        if (!in_array($role, ['client', 'master', 'admin'], true)) return;
        try {
            $this->pdo->prepare("UPDATE users SET role = ?, role_requested_at = NULL WHERE id = ?")->execute([$role, $userId]);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'role_requested_at') !== false || strpos($e->getMessage(), 'column') !== false) {
                $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $userId]);
            } else {
                throw $e;
            }
        }
        if (in_array($role, ['master', 'admin'], true)) {
            try {
                $this->pdo->prepare("INSERT IGNORE INTO master_profiles (user_id) VALUES (?)")->execute([$userId]);
            } catch (\Throwable $e) { }
        }
    }

    public function approveMasterRequest(int $userId): void
    {
        try {
            $this->pdo->prepare("UPDATE users SET role = 'master', role_requested_at = NULL WHERE id = ?")->execute([$userId]);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'role_requested_at') !== false || strpos($e->getMessage(), 'column') !== false) {
                $this->pdo->prepare("UPDATE users SET role = 'master' WHERE id = ?")->execute([$userId]);
            } else {
                throw $e;
            }
        }
        try {
            $this->pdo->prepare("INSERT IGNORE INTO master_profiles (user_id) VALUES (?)")->execute([$userId]);
        } catch (\Throwable $e) { }
    }

    public function rejectMasterRequest(int $userId): void
    {
        try {
            $this->pdo->prepare("UPDATE users SET role_requested_at = NULL WHERE id = ?")->execute([$userId]);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'role_requested_at') !== false || strpos($e->getMessage(), 'column') !== false) {
                return;
            }
            throw $e;
        }
    }

    public function getUsersWithMasterRequest(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM users WHERE role_requested_at IS NOT NULL ORDER BY role_requested_at DESC");
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
