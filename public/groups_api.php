<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\GroupChatRepository;
use App\MessageRepository;
use App\Repositories\UserRepo;

header('Content-Type: application/json; charset=utf-8');

Auth::init();
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Войдите в аккаунт']);
    exit;
}
if (Auth::isBanned()) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

$groupRepo = new GroupChatRepository();
$userRepo = new UserRepo();
$uid = (int)$user['id'];
$isMaster = in_array($user['role'] ?? '', ['master', 'admin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    if ($action === 'create') {
        if (!$isMaster) {
            echo json_encode(['error' => 'Только мастера могут создавать группы']);
            exit;
        }
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 255) {
            echo json_encode(['error' => 'Введите название группы (1–255 символов)']);
            exit;
        }
        try {
            $groupId = $groupRepo->create($name, $uid);
            if ($groupId > 0) {
                echo json_encode(['ok' => true, 'group_id' => (int)$groupId]);
            } else {
                echo json_encode(['error' => 'Не удалось создать группу']);
            }
        } catch (\Throwable $e) {
            error_log('Group create error: ' . $e->getMessage());
            echo json_encode(['error' => 'Ошибка создания. Убедитесь, что миграция v5 выполнена: php install/migrate_v5.php']);
        }
        exit;
    }
    if ($action === 'join') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $groupName = trim($_POST['group_name'] ?? '');
        if (!$groupId && $groupName === '') {
            echo json_encode(['error' => 'Введите название или ID группы']);
            exit;
        }
        try {
            $group = $groupId ? $groupRepo->findById($groupId) : $groupRepo->findByName($groupName);
            if (!$group) {
                echo json_encode(['error' => 'Группа не найдена. Проверьте название или ID']);
                exit;
            }
            $groupId = (int)$group['id'];
            if ($groupRepo->isMember($groupId, $uid)) {
                echo json_encode(['ok' => true, 'group_id' => $groupId, 'already_member' => true]);
                exit;
            }
            $groupRepo->addMember($groupId, $uid);
            echo json_encode(['ok' => true, 'group_id' => $groupId]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка БД. Запустите: php install/migrate_v5.php']);
        }
        exit;
    }
    if ($action === 'leave') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        if (!$groupId) {
            http_response_code(400);
            echo json_encode(['error' => 'group_id required']);
            exit;
        }
        if (!$groupRepo->isMember($groupId, $uid)) {
            http_response_code(403);
            echo json_encode(['error' => 'Вы не в группе']);
            exit;
        }
        $group = $groupRepo->findById($groupId);
        $creatorId = (int)($group['creator_id'] ?? 0);
        if ($creatorId === $uid) {
            http_response_code(400);
            echo json_encode(['error' => 'Создатель не может выйти. Удалите группу или передайте права.']);
            exit;
        }
        $groupRepo->removeMember($groupId, $uid);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'send_link') {
        $targetGroupId = (int)($_POST['group_id'] ?? 0);
        $convIds = array_map('intval', (array)($_POST['conv_ids'] ?? []));
        $groupIds = array_map('intval', (array)($_POST['group_ids'] ?? []));
        if (!$targetGroupId) {
            echo json_encode(['error' => 'Не указана группа']);
            exit;
        }
        $group = $groupRepo->findById($targetGroupId);
        if (!$group || (int)($group['creator_id'] ?? 0) !== $uid) {
            echo json_encode(['error' => 'Вы не создатель этой группы']);
            exit;
        }
        $config = require dirname(__DIR__) . '/config/config.php';
        $siteUrl = rtrim($config['site']['url'] ?? '', '/');
        $base = defined('BASE_PATH') && BASE_PATH !== '' ? rtrim(BASE_PATH, '/') . '/' : '/';
        $inviteUrl = $siteUrl . $base . 'messages.php?group=' . $targetGroupId;
        $linkText = 'Присоединяйтесь к группе «' . ($group['name'] ?? '') . '»: ' . $inviteUrl;
        $msgRepo = new MessageRepository();
        $sent = 0;
        $pdo = \App\Database::get();
        foreach ($convIds as $cid) {
            if ($cid <= 0) continue;
            $stmt = $pdo->prepare("SELECT 1 FROM conversations WHERE id = ? AND (master_id = ? OR client_id = ?)");
            $stmt->execute([$cid, $uid, $uid]);
            if ($stmt->fetch()) {
                $msgRepo->send($cid, $uid, $linkText);
                $sent++;
            }
        }
        foreach ($groupIds as $gid) {
            if ($gid <= 0 || $gid === $targetGroupId) continue;
            if ($groupRepo->isMember($gid, $uid)) {
                $groupRepo->sendMessage($gid, $uid, $linkText);
                $sent++;
            }
        }
        echo json_encode(['ok' => true, 'sent' => $sent]);
        exit;
    }
}

// GET: список моих групп и диалогов (для модалки «отправить ссылку»)
if (isset($_GET['for_share']) && $_GET['for_share'] === '1') {
    $msgRepo = new MessageRepository();
    $conversations = $msgRepo->getConversationsForUser($uid, $isMaster);
    $groups = $groupRepo->getGroupsForUser($uid);
    echo json_encode(['conversations' => $conversations, 'groups' => $groups]);
    exit;
}

// GET: список моих групп
try {
    $groups = $groupRepo->getGroupsForUser($uid);
    echo json_encode(['groups' => $groups]);
} catch (\Throwable $e) {
    echo json_encode(['groups' => []]);
}
