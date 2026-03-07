<?php

declare(strict_types=1);

namespace App;

use PDO;

class Auth
{
    private const SESSION_KEY = 'user_id';
    private const ROLE_ADMIN = 'admin';
    private const ROLE_MASTER = 'master';
    private const ROLE_CLIENT = 'client';

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $config = require __DIR__ . '/../config/config.php';
            session_name($config['session']['name'] ?? 'CIRCUS_SESSION');
            session_start();
        }
    }

    public static function login(int $userId): void
    {
        self::init();
        $_SESSION[self::SESSION_KEY] = $userId;
    }

    public static function logout(): void
    {
        self::init();
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function id(): ?int
    {
        self::init();
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        return $id ? (int) $id : null;
    }

    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) return null;
        $pdo = Database::get();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_banned = 0");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            try {
                $pdo->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?")->execute([$id]);
            } catch (\Throwable $e) { /* last_seen_at может отсутствовать до миграции */ }
        }
        return $row ?: null;
    }

    /** Проверяет, заблокирован ли пользователь с текущей сессией */
    public static function isBanned(): bool
    {
        $id = self::id();
        if ($id === null) return false;
        $pdo = Database::get();
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND is_banned = 1");
        $stmt->execute([$id]);
        return (bool) $stmt->fetch();
    }

    /** Возвращает данные заблокированного пользователя (для экрана блокировки) */
    public static function bannedUser(): ?array
    {
        $id = self::id();
        if ($id === null) return null;
        $pdo = Database::get();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_banned = 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : '';
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && $u['role'] === self::ROLE_ADMIN;
    }

    public static function isMaster(): bool
    {
        $u = self::user();
        return $u && ($u['role'] === self::ROLE_MASTER || $u['role'] === self::ROLE_ADMIN);
    }

    public static function isClient(): bool
    {
        $u = self::user();
        return $u && $u['role'] === self::ROLE_CLIENT;
    }

    public static function requireAuth(): array
    {
        $u = self::user();
        if (!$u) {
            if (self::isBanned()) {
                header('Location: ' . self::basePath() . '/blocked.php');
                exit;
            }
            header('Location: ' . self::basePath() . '/login.php');
            exit;
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::requireAuth();
        if ($u['role'] !== self::ROLE_ADMIN) {
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
            exit;
        }
        return $u;
    }

    public static function requireMaster(): void
    {
        if (!self::isMaster()) {
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/');
            exit;
        }
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
