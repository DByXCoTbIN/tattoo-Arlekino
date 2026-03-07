<?php

declare(strict_types=1);

namespace App;

use PDO;

class MessageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    public function getOrCreateConversation(int $masterId, int $clientId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM conversations WHERE master_id = ? AND client_id = ?");
        $stmt->execute([$masterId, $clientId]);
        $row = $stmt->fetch();
        if ($row) return (int) $row['id'];
        $this->pdo->prepare("INSERT INTO conversations (master_id, client_id) VALUES (?, ?)")->execute([$masterId, $clientId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function send(int $conversationId, int $senderId, string $body, ?string $mediaPath = null, ?string $mediaType = null): void
    {
        try {
            $this->pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body, media_path, media_type) VALUES (?, ?, ?, ?, ?)")
                ->execute([$conversationId, $senderId, $body ?: '', $mediaPath, $mediaType]);
        } catch (\Throwable $e) {
            $this->pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)")
                ->execute([$conversationId, $senderId, $body ?: '']);
        }
        $this->pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);
        $stmt = $this->pdo->prepare("SELECT master_id, client_id FROM conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        $c = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($c) {
            $recipientId = (int)$c['master_id'] === $senderId ? (int)$c['client_id'] : (int)$c['master_id'];
            try {
                (new NotificationRepository())->add($recipientId, 'message', $conversationId, $senderId, null);
            } catch (\Throwable $e) { }
        }
    }

    public function getMessages(int $conversationId, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare("
            SELECT m.*, u.full_name, u.avatar_path FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.conversation_id = ? ORDER BY m.created_at ASC LIMIT " . $limit . " OFFSET " . $offset . "
        ");
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll();
    }

    public function markRead(int $conversationId, int $readerId): void
    {
        $stmt = $this->pdo->prepare("SELECT master_id, client_id FROM conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        $c = $stmt->fetch();
        if (!$c) return;
        $otherId = (int)$c['master_id'] === $readerId ? (int)$c['client_id'] : (int)$c['master_id'];
        $this->pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?")
            ->execute([$conversationId, $readerId]);
    }

    /**
     * Возвращает все диалоги пользователя (и где он мастер, и где клиент).
     */
    public function getConversationsForUser(int $userId, bool $isMaster): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.master_id, c.client_id, c.updated_at,
                   u.full_name, u.avatar_path,
                   (SELECT body FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message,
                   (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_at,
                   (SELECT COUNT(*) FROM messages m2 WHERE m2.conversation_id = c.id AND m2.sender_id != ? AND m2.is_read = 0) AS unread_count
            FROM conversations c
            JOIN users u ON u.id = CASE WHEN c.master_id = ? THEN c.client_id ELSE c.master_id END
            WHERE c.master_id = ? OR c.client_id = ?
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            WHERE (c.master_id = ? OR c.client_id = ?) AND m.sender_id != ? AND m.is_read = 0
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Удалить диалог (доступно мастерам и админам — участникам диалога).
     * Сообщения удаляются каскадно.
     */
    public function deleteConversation(int $conversationId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM conversations WHERE id = ? AND (master_id = ? OR client_id = ?)");
        $stmt->execute([$conversationId, $userId, $userId]);
        return $stmt->rowCount() > 0;
    }
}
