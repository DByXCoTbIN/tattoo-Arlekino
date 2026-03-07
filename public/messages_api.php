<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\MessageRepository;
use App\Repositories\UserRepo;

header('Content-Type: application/json; charset=utf-8');

Auth::init();
$user = Auth::user();
$groupId = (int)($_GET['group'] ?? $_POST['group'] ?? 0);
$supportService = new \App\SupportChatService();
$isSupportChat = $groupId > 0 && $supportService->isSupportChat($groupId);
if (!$user && Auth::isBanned() && $isSupportChat) {
    $user = Auth::bannedUser();
}
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$config = require dirname(__DIR__) . '/config/config.php';
$msgRepo = new MessageRepository();
$userRepo = new UserRepo();
$pdo = \App\Database::get();
$uid = (int)$user['id'];

$convId = (int)($_GET['conv'] ?? $_POST['conv'] ?? 0);
$groupId = (int)($_GET['group'] ?? $_POST['group'] ?? 0);
$action = trim($_POST['action'] ?? '');

$isMasterOrAdmin = in_array($user['role'] ?? '', ['master', 'admin'], true);

// Удаление диалога (только мастер/админ)
if ($action === 'delete_conv' && $_SERVER['REQUEST_METHOD'] === 'POST' && $isMasterOrAdmin) {
    $delConvId = (int)($_POST['conv'] ?? $_POST['conv_id'] ?? 0);
    if ($delConvId > 0) {
        $stmt = $pdo->prepare("SELECT 1 FROM conversations WHERE id = ? AND (master_id = ? OR client_id = ?)");
        $stmt->execute([$delConvId, $uid, $uid]);
        if ($stmt->fetch() && $msgRepo->deleteConversation($delConvId, $uid)) {
            echo json_encode(['ok' => true, 'redirect' => (defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/' : '/') . 'messages.php']);
            exit;
        }
    }
    echo json_encode(['ok' => false, 'error' => 'Forbidden or not found']);
    exit;
}

// Удаление группы (админ — любую, мастер — только созданную им)
if ($action === 'delete_group' && $_SERVER['REQUEST_METHOD'] === 'POST' && $isMasterOrAdmin) {
    $delGroupId = (int)($_POST['group'] ?? $_POST['group_id'] ?? 0);
    if ($delGroupId > 0) {
        $groupRepo = new \App\GroupChatRepository();
        if ($groupRepo->deleteGroup($delGroupId, $uid, Auth::isAdmin())) {
            echo json_encode(['ok' => true, 'redirect' => (defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/' : '/') . 'messages.php']);
            exit;
        }
    }
    echo json_encode(['ok' => false, 'error' => 'Forbidden or not found']);
    exit;
}

// Обработка typing (для 1-1 или группы)
if ($action === 'typing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($convId) {
            $stmt = $pdo->prepare("SELECT 1 FROM conversations WHERE id = ? AND (master_id = ? OR client_id = ?)");
            $stmt->execute([$convId, $uid, $uid]);
            if ($stmt->fetch()) {
                if (\App\Database::isSqlite()) {
                    $pdo->prepare("INSERT OR REPLACE INTO typing_status (user_id, conversation_id, group_id, updated_at) VALUES (?, ?, 0, datetime('now'))")
                        ->execute([$uid, $convId]);
                } else {
                    $pdo->prepare("INSERT INTO typing_status (user_id, conversation_id, group_id) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP")
                        ->execute([$uid, $convId]);
                }
            }
        } elseif ($groupId) {
            $stmt = $pdo->prepare("SELECT 1 FROM group_chat_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$groupId, $uid]);
            if ($stmt->fetch()) {
                if (\App\Database::isSqlite()) {
                    $pdo->prepare("INSERT OR REPLACE INTO typing_status (user_id, conversation_id, group_id, updated_at) VALUES (?, 0, ?, datetime('now'))")
                        ->execute([$uid, $groupId]);
                } else {
                    $pdo->prepare("INSERT INTO typing_status (user_id, conversation_id, group_id) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP")
                        ->execute([$uid, $groupId]);
                }
            }
        }
    } catch (\Throwable $e) { }
    echo json_encode(['ok' => true]);
    exit;
}

// 1-1 чат
if ($convId) {
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND (master_id = ? OR client_id = ?)");
    $stmt->execute([$convId, $uid, $uid]);
    $conv = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$conv) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $otherId = (int)$conv['master_id'] === $uid ? (int)$conv['client_id'] : (int)$conv['master_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'typing') {
        $body = trim($_POST['body'] ?? '');
        $mediaPath = null;
        $mediaType = null;
        if (!empty($_FILES['media']['name']) && ($_FILES['media']['error'] ?? 0) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
            $allowedImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedVid = ['mp4', 'webm', 'mov'];
            if (in_array($ext, $allowedImg, true)) {
                $mediaType = 'image';
            } elseif (in_array($ext, $allowedVid, true)) {
                $mediaType = 'video';
            }
            if ($mediaType) {
                $dir = $config['site']['upload_path'] . '/messages/' . $convId;
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['media']['tmp_name'], $dir . '/' . $fname)) {
                    $mediaPath = '/uploads/messages/' . $convId . '/' . $fname;
                }
            }
        }
        if ($body !== '' || $mediaPath) {
            $msgRepo->send($convId, $uid, $body, $mediaPath, $mediaType);
        }
    }

    $messages = $msgRepo->getMessages($convId);
    $msgRepo->markRead($convId, $uid);
    $otherUser = $userRepo->findById($otherId) ?? $userRepo->findByIdForDisplay($otherId);

    // Онлайн: last_seen_at за последние 2 минуты
    $onlineIds = [];
    if ($otherUser) {
        $lastSeen = $otherUser['last_seen_at'] ?? null;
        if ($lastSeen && (time() - strtotime($lastSeen)) < 120) {
            $onlineIds[] = (int)$otherUser['id'];
        }
    }

    // Печатает
    $typingNames = [];
    try {
        if (\App\Database::isSqlite()) {
            $stmt = $pdo->prepare("SELECT u.full_name FROM typing_status t JOIN users u ON u.id = t.user_id WHERE t.conversation_id = ? AND t.user_id != ? AND t.updated_at > datetime('now', '-5 seconds')");
        } else {
            $stmt = $pdo->prepare("SELECT u.full_name FROM typing_status t JOIN users u ON u.id = t.user_id WHERE t.conversation_id = ? AND t.user_id != ? AND t.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        }
        $stmt->execute([$convId, $uid]);
        $typingNames = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'full_name');
    } catch (\Throwable $e) { }

    $out = [
        'type' => 'conv',
        'conv_id' => $convId,
        'other' => $otherUser ? ['id' => (int)$otherUser['id'], 'full_name' => $otherUser['full_name'] ?? '', 'online' => in_array((int)$otherUser['id'], $onlineIds, true), 'last_seen_at' => $otherUser['last_seen_at'] ?? null] : null,
        'typing' => $typingNames,
        'messages' => array_map(function ($m) use ($uid) {
            return [
                'id' => (int)$m['id'],
                'sender_id' => (int)$m['sender_id'],
                'sender_name' => $m['full_name'] ?? '',
                'body' => $m['body'] ?? '',
                'media_path' => $m['media_path'] ?? null,
                'media_type' => $m['media_type'] ?? null,
                'created_at' => $m['created_at'] ?? '',
                'is_mine' => (int)$m['sender_id'] === $uid,
            ];
        }, $messages),
    ];
    echo json_encode($out);
    exit;
}

// Групповой чат
if ($groupId) {
    $groupRepo = new \App\GroupChatRepository();
    $supportService = new \App\SupportChatService();
    $isSupportChat = $supportService->isSupportChat($groupId);
    if (!$groupRepo->isMember($groupId, $uid)) {
        if (Auth::isAdmin() && $isSupportChat) {
            $supportService->ensureAdminInSupportChat($groupId, $uid);
        }
        if (!$groupRepo->isMember($groupId, $uid)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }
    $group = $groupRepo->findById($groupId);
    if (!$group) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'typing') {
        $body = trim($_POST['body'] ?? '');
        $mediaPath = null;
        $mediaType = null;
        if (!empty($_FILES['media']['name']) && ($_FILES['media']['error'] ?? 0) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
            $allowedImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedVid = ['mp4', 'webm', 'mov'];
            if (in_array($ext, $allowedImg, true)) $mediaType = 'image';
            elseif (in_array($ext, $allowedVid, true)) $mediaType = 'video';
            if ($mediaType) {
                $dir = $config['site']['upload_path'] . '/groups/' . $groupId;
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['media']['tmp_name'], $dir . '/' . $fname)) {
                    $mediaPath = '/uploads/groups/' . $groupId . '/' . $fname;
                }
            }
        }
        if ($body !== '' || $mediaPath) {
            $groupRepo->sendMessage($groupId, $uid, $body, $mediaPath, $mediaType);
            if ($isSupportChat) {
                try {
                    $notifRepo = new \App\NotificationRepository();
                    $stmt = $pdo->query("SELECT role FROM users WHERE id = " . (int)$uid);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && ($row['role'] ?? '') === 'admin') {
                        $targetUserId = $supportService->getUserIdBySupportGroupId($groupId);
                        if ($targetUserId) {
                            $notifRepo->add($targetUserId, 'support_message', $groupId, $uid, json_encode(['preview' => mb_substr($body ?: '[медиа]', 0, 100)]));
                        }
                    } else {
                        $notifRepo->add(null, 'support_message', $groupId, $uid, json_encode(['preview' => mb_substr($body ?: '[медиа]', 0, 100)]));
                    }
                } catch (\Throwable $e) { }
            }
        }
    }

    $messages = $groupRepo->getMessages($groupId);

    // Участники группы онлайн (last_seen_at может отсутствовать до миграции v2)
    $online = [];
    try {
        $stmt = $pdo->prepare("SELECT m.user_id, u.full_name, u.last_seen_at FROM group_chat_members m JOIN users u ON u.id = m.user_id WHERE m.group_id = ?");
        $stmt->execute([$groupId]);
        $members = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($members as $m) {
            if ((int)$m['user_id'] !== $uid) {
                $lastSeen = $m['last_seen_at'] ?? null;
                if ($lastSeen && (time() - strtotime($lastSeen)) < 120) {
                    $online[] = $m['full_name'] ?? 'Пользователь';
                }
            }
        }
    } catch (\Throwable $e) { }

    // Печатает в группе
    $typingNames = [];
    try {
        if (\App\Database::isSqlite()) {
            $stmt = $pdo->prepare("SELECT u.full_name FROM typing_status t JOIN users u ON u.id = t.user_id WHERE t.group_id = ? AND t.user_id != ? AND t.updated_at > datetime('now', '-5 seconds')");
        } else {
            $stmt = $pdo->prepare("SELECT u.full_name FROM typing_status t JOIN users u ON u.id = t.user_id WHERE t.group_id = ? AND t.user_id != ? AND t.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        }
        $stmt->execute([$groupId, $uid]);
        $typingNames = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'full_name');
    } catch (\Throwable $e) { }

    $out = [
        'type' => 'group',
        'group_id' => $groupId,
        'group_name' => $group['name'] ?? 'Чат',
        'creator_id' => (int)($group['creator_id'] ?? 0),
        'online' => $online,
        'typing' => $typingNames,
        'messages' => array_map(function ($m) use ($uid) {
            return [
                'id' => (int)$m['id'],
                'sender_id' => (int)$m['sender_id'],
                'sender_name' => $m['full_name'] ?? '',
                'body' => $m['body'] ?? '',
                'media_path' => $m['media_path'] ?? null,
                'media_type' => $m['media_type'] ?? null,
                'created_at' => $m['created_at'] ?? '',
                'is_mine' => (int)$m['sender_id'] === $uid,
            ];
        }, $messages),
    ];
    echo json_encode($out);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Missing conv or group']);
