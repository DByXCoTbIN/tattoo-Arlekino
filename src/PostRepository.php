<?php

declare(strict_types=1);

namespace App;

use PDO;

class PostRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    public function create(int $masterId, string $contentText = ''): int
    {
        $this->pdo->prepare("INSERT INTO posts (master_id, content_text) VALUES (?, ?)")->execute([$masterId, $contentText]);
        return (int) $this->pdo->lastInsertId();
    }

    public function addMedia(int $postId, string $type, string $filePath, int $order = 0): void
    {
        $this->pdo->prepare("INSERT INTO post_media (post_id, media_type, file_path, sort_order) VALUES (?, ?, ?, ?)")
            ->execute([$postId, $type, $filePath, $order]);
    }

    public function getByMaster(int $masterId, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare("
            SELECT * FROM posts WHERE master_id = ? AND is_published = 1
            ORDER BY created_at DESC LIMIT " . $limit . " OFFSET " . $offset . "
        ");
        $stmt->execute([$masterId]);
        return $stmt->fetchAll();
    }

    public function getWithMedia(int $masterId, int $limit = 20, int $offset = 0): array
    {
        $posts = $this->getByMaster($masterId, $limit, $offset);
        foreach ($posts as &$post) {
            $stmt = $this->pdo->prepare("SELECT * FROM post_media WHERE post_id = ? ORDER BY sort_order, id");
            $stmt->execute([$post['id']]);
            $post['media'] = $stmt->fetchAll();
        }
        return $posts;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function delete(int $postId, int $masterId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM posts WHERE id = ? AND master_id = ?");
        $stmt->execute([$postId, $masterId]);
        return $stmt->rowCount() > 0;
    }

    public function getFeed(int $limit = 30, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.full_name, u.avatar_path
            FROM posts p
            JOIN users u ON u.id = p.master_id
            WHERE p.is_published = 1 AND u.is_banned = 0
            ORDER BY p.created_at DESC LIMIT " . $limit . " OFFSET " . $offset . "
        ");
        $stmt->execute([]);
        $posts = $stmt->fetchAll();
        foreach ($posts as &$post) {
            $st = $this->pdo->prepare("SELECT * FROM post_media WHERE post_id = ? ORDER BY sort_order, id");
            $st->execute([$post['id']]);
            $post['media'] = $st->fetchAll();
        }
        return $posts;
    }
}
