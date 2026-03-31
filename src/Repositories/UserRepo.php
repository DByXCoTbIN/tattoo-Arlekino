<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

class UserRepo
{
    private PDO $pdo;

    private static ?bool $masterProfileVisibilityColumnsOk = null;

    public static function masterProfileVisibilityColumnsExist(): bool
    {
        if (self::$masterProfileVisibilityColumnsOk === true) {
            return true;
        }
        $pdo = Database::get();
        try {
            if (Database::isSqlite()) {
                $stmt = $pdo->query('PRAGMA table_info(master_profiles)');
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $names = array_column($rows, 'name');
                $ok = in_array('profile_hidden_by_master', $names, true)
                    && in_array('admin_profile_visibility', $names, true);
                if ($ok) {
                    self::$masterProfileVisibilityColumnsOk = true;
                }
                return $ok;
            }
            $pdo->query('SELECT profile_hidden_by_master, admin_profile_visibility FROM master_profiles LIMIT 0');
            self::$masterProfileVisibilityColumnsOk = true;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Пытается дозаполнить недостающие колонки видимости (если миграция применена частично). */
    private function ensureMasterProfileVisibilityColumns(): void
    {
        if (self::masterProfileVisibilityColumnsExist()) {
            return;
        }
        try {
            if (Database::isSqlite()) {
                try {
                    $this->pdo->exec("ALTER TABLE master_profiles ADD COLUMN profile_hidden_by_master INTEGER NOT NULL DEFAULT 0");
                } catch (\Throwable $e) { }
                try {
                    $this->pdo->exec("ALTER TABLE master_profiles ADD COLUMN admin_profile_visibility TEXT");
                } catch (\Throwable $e) { }
            } else {
                try {
                    $this->pdo->exec("ALTER TABLE master_profiles ADD COLUMN profile_hidden_by_master TINYINT(1) NOT NULL DEFAULT 0");
                } catch (\Throwable $e) { }
                try {
                    $this->pdo->exec("ALTER TABLE master_profiles ADD COLUMN admin_profile_visibility VARCHAR(20) NULL DEFAULT NULL");
                } catch (\Throwable $e) { }
            }
        } catch (\Throwable $e) { }
        if (self::masterProfileVisibilityColumnsExist()) {
            self::$masterProfileVisibilityColumnsOk = true;
        }
    }

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

    /**
     * Условие SQL: профиль виден на сайте (списки, запись).
     * ВАЖНО: если мастер скрыл профиль, он не показывается нигде публично
     * до тех пор, пока сам мастер не включит видимость обратно.
     */
    public static function sqlMasterVisibleOnSite(): string
    {
        if (!self::masterProfileVisibilityColumnsExist()) {
            return '1=1';
        }
        $eff = "(CASE WHEN COALESCE(mp.profile_hidden_by_master, 0) = 1 THEN 0
             WHEN COALESCE(mp.admin_profile_visibility, '') = 'force_hide' THEN 0
             WHEN COALESCE(mp.admin_profile_visibility, '') = 'force_show' THEN 1
             ELSE 1 END)";
        return "(" . $eff . " = 1)";
    }

    /** Эффективная публичная видимость профиля по строке (после JOIN master_profiles). */
    public static function isEffectiveProfilePublic(array $masterRow): bool
    {
        if (((int) ($masterRow['profile_hidden_by_master'] ?? 0)) === 1) {
            return false;
        }
        $admin = trim((string) ($masterRow['admin_profile_visibility'] ?? ''));
        if ($admin === 'force_hide') {
            return false;
        }
        if ($admin === 'force_show') {
            return true;
        }
        return ((int) ($masterRow['profile_hidden_by_master'] ?? 0)) === 0;
    }

    /** Список мастеров для публичного отображения: верифицированные мастера и все админы (админы показываются всегда). */
    public function getMasters(int $limit = 50, int $offset = 0, string $sort = 'reviews'): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $ratingAvgExpr = "CASE WHEN COALESCE(mp.rating_count, 0) > 0 THEN (COALESCE(mp.rating_sum, 0) * 1.0 / mp.rating_count) ELSE 0 END";
        $orderMap = [
            'reviews' => $ratingAvgExpr . " DESC, COALESCE(mp.rating_count, 0) DESC, u.full_name ASC",
            'days' => "u.created_at ASC, u.full_name ASC",
            'reviews_count' => "COALESCE(mp.rating_count, 0) DESC, " . $ratingAvgExpr . " DESC, u.full_name ASC",
            'alphabet' => "u.full_name ASC",
        ];
        $orderBy = $orderMap[$sort] ?? $orderMap['reviews'];
        $stmt = $this->pdo->prepare("
            SELECT u.*, mp.bio, mp.specialization, mp.rating_sum, mp.rating_count, mp.is_verified,
                   CASE WHEN mp.rating_count > 0 THEN ROUND(mp.rating_sum / mp.rating_count, 1) ELSE 0 END AS rating_avg
            FROM users u
            LEFT JOIN master_profiles mp ON mp.user_id = u.id
            WHERE u.role IN ('master', 'admin') AND u.is_banned = 0
              AND (COALESCE(mp.is_verified, 0) = 1 OR u.role = 'admin')
              AND (" . self::sqlMasterVisibleOnSite() . ")
            ORDER BY " . $orderBy . "
            LIMIT " . $limit . " OFFSET " . $offset . "
        ");
        $stmt->execute([]);
        return $stmt->fetchAll();
    }

    /** ID публичных мастеров (те же условия, что у getMasters) — для sitemap. */
    public function getPublicMasterIds(): array
    {
        $stmt = $this->pdo->query("
            SELECT u.id
            FROM users u
            LEFT JOIN master_profiles mp ON mp.user_id = u.id
            WHERE u.role IN ('master', 'admin') AND u.is_banned = 0
              AND (COALESCE(mp.is_verified, 0) = 1 OR u.role = 'admin')
              AND (" . self::sqlMasterVisibleOnSite() . ")
            ORDER BY u.id
        ");
        if (!$stmt) {
            return [];
        }
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    /**
     * Профиль мастера.
     * @param bool $verifiedOnly при true — только верифицированные и админы (админы показываются всегда)
     * @param bool $publicProfileOnly при true — только с публично видимым профилем (для записи и публичных API)
     */
    public function getMasterProfile(int $userId, bool $verifiedOnly = false, bool $publicProfileOnly = false): ?array
    {
        $verifiedCond = $verifiedOnly ? " AND (COALESCE(mp.is_verified, 0) = 1 OR u.role = 'admin')" : "";
        $publicCond = $publicProfileOnly ? " AND (" . self::sqlMasterVisibleOnSite() . ")" : "";
        $mpVis = self::masterProfileVisibilityColumnsExist()
            ? ', mp.profile_hidden_by_master, mp.admin_profile_visibility'
            : '';
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, mp.bio, mp.specialization, mp.phone, mp.instagram, mp.vk, mp.telegram, mp.youtube, mp.max_link, mp.rating_sum, mp.rating_count, mp.is_verified, mp.banner_path
                       " . $mpVis . ",
                       CASE WHEN mp.rating_count > 0 THEN ROUND(mp.rating_sum / mp.rating_count, 1) ELSE 0 END AS rating_avg
                FROM users u
                LEFT JOIN master_profiles mp ON mp.user_id = u.id
                WHERE u.id = ? AND u.role IN ('master', 'admin') AND u.is_banned = 0" . $verifiedCond . $publicCond . "
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch() ?: null;
            if ($row !== null && $mpVis === '') {
                $row['profile_hidden_by_master'] = 0;
                $row['admin_profile_visibility'] = null;
            }
            return $row;
        } catch (\Throwable $e) {
            $stmt = $this->pdo->prepare("
                SELECT u.*, mp.bio, mp.specialization, mp.rating_sum, mp.rating_count, mp.is_verified,
                       CASE WHEN mp.rating_count > 0 THEN ROUND(mp.rating_sum / mp.rating_count, 1) ELSE 0 END AS rating_avg
                FROM users u
                LEFT JOIN master_profiles mp ON mp.user_id = u.id
                WHERE u.id = ? AND u.role IN ('master', 'admin') AND u.is_banned = 0" . $verifiedCond . $publicCond . "
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
                $row['profile_hidden_by_master'] = 0;
                $row['admin_profile_visibility'] = null;
                // Даже если основной SELECT упал на старых колонках, дочитываем флаги видимости отдельно.
                if (self::masterProfileVisibilityColumnsExist()) {
                    try {
                        $vStmt = $this->pdo->prepare("SELECT COALESCE(profile_hidden_by_master, 0) AS profile_hidden_by_master, admin_profile_visibility FROM master_profiles WHERE user_id = ?");
                        $vStmt->execute([$userId]);
                        $vRow = $vStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                        if ($vRow) {
                            $row['profile_hidden_by_master'] = (int)($vRow['profile_hidden_by_master'] ?? 0);
                            $row['admin_profile_visibility'] = $vRow['admin_profile_visibility'] ?? null;
                        }
                    } catch (\Throwable $e2) { }
                }
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
        $mpVis = self::masterProfileVisibilityColumnsExist()
            ? ', mp.profile_hidden_by_master, mp.admin_profile_visibility'
            : '';
        if ($ensureFirstUserId !== null && $ensureFirstUserId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT u.*, mp.rating_count, mp.is_verified" . $mpVis . " FROM users u
                LEFT JOIN master_profiles mp ON mp.user_id = u.id
                ORDER BY (u.id = ?) DESC, u.created_at DESC LIMIT " . $limit . " OFFSET " . $offset . "
            ");
            $stmt->execute([$ensureFirstUserId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT u.*, mp.rating_count, mp.is_verified" . $mpVis . " FROM users u
                LEFT JOIN master_profiles mp ON mp.user_id = u.id
                ORDER BY u.created_at DESC LIMIT " . $limit . " OFFSET " . $offset . "
            ");
            $stmt->execute([]);
        }
        $rows = $stmt->fetchAll();
        if ($mpVis === '') {
            foreach ($rows as &$r) {
                $r['profile_hidden_by_master'] = 0;
                $r['admin_profile_visibility'] = null;
            }
            unset($r);
        }
        return $rows;
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

    public function setProfileHiddenByMaster(int $userId, bool $hidden): void
    {
        $h = $hidden ? 1 : 0;
        $this->ensureMasterProfileVisibilityColumns();
        try {
            if (Database::isSqlite()) {
                $this->pdo->prepare("INSERT INTO master_profiles (user_id, profile_hidden_by_master) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET profile_hidden_by_master = excluded.profile_hidden_by_master")->execute([$userId, $h]);
            } else {
                $this->pdo->prepare("INSERT INTO master_profiles (user_id, profile_hidden_by_master) VALUES (?, ?) ON DUPLICATE KEY UPDATE profile_hidden_by_master = VALUES(profile_hidden_by_master)")->execute([$userId, $h]);
            }
        } catch (\Throwable $e) { }
    }

    /** null — снять override; force_show / force_hide */
    public function setAdminProfileVisibilityOverride(int $userId, ?string $mode): void
    {
        $mode = $mode === null || $mode === '' || $mode === 'default' ? null : $mode;
        if ($mode !== null && !in_array($mode, ['force_show', 'force_hide'], true)) {
            return;
        }
        $this->ensureMasterProfileVisibilityColumns();
        try {
            if (Database::isSqlite()) {
                if ($mode === null) {
                    $this->pdo->prepare("UPDATE master_profiles SET admin_profile_visibility = NULL WHERE user_id = ?")->execute([$userId]);
                } else {
                    $this->pdo->prepare("INSERT INTO master_profiles (user_id, admin_profile_visibility) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET admin_profile_visibility = excluded.admin_profile_visibility")->execute([$userId, $mode]);
                }
            } else {
                if ($mode === null) {
                    $this->pdo->prepare("UPDATE master_profiles SET admin_profile_visibility = NULL WHERE user_id = ?")->execute([$userId]);
                } else {
                    $this->pdo->prepare("INSERT INTO master_profiles (user_id, admin_profile_visibility) VALUES (?, ?) ON DUPLICATE KEY UPDATE admin_profile_visibility = VALUES(admin_profile_visibility)")->execute([$userId, $mode]);
                }
            }
        } catch (\Throwable $e) { }
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

    /**
     * Полное удаление пользователя и связанных данных (через CASCADE и явные DELETE).
     * Нельзя удалить последнего администратора.
     */
    public function deleteUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        if (($row['role'] ?? '') === 'admin') {
            $cnt = (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($cnt <= 1) {
                return false;
            }
        }

        $this->pdo->beginTransaction();
        try {
            foreach (
                [
                    'DELETE FROM notifications WHERE from_user_id = ? OR user_id = ?',
                    'DELETE FROM complaints WHERE from_user_id = ? OR about_user_id = ?',
                ] as $sql
            ) {
                try {
                    $this->pdo->prepare($sql)->execute([$userId, $userId]);
                } catch (\Throwable $e) {
                    // таблица может отсутствовать в старых схемах
                }
            }
            $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }
}
