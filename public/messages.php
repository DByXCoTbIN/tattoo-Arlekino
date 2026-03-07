<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Settings;
use App\MessageRepository;
use App\GroupChatRepository;
use App\Repositories\UserRepo;

Auth::init();
$user = Auth::user();
$config = require dirname(__DIR__) . '/config/config.php';
$siteName = Settings::get('site_name', $config['site']['name']);

$toId = (int)($_GET['to'] ?? 0);
$convId = (int)($_GET['conv'] ?? 0);
$groupId = (int)($_GET['group'] ?? 0);

$groupRepo = new GroupChatRepository();
$supportService = new \App\SupportChatService();
$isSupportChat = $groupId > 0 && $supportService->isSupportChat($groupId);

// Заблокированный пользователь может зайти только в чат поддержки
if (Auth::isBanned() && !$isSupportChat) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/blocked.php');
    exit;
}
if (Auth::isBanned() && $isSupportChat) {
    $user = Auth::bannedUser();
}

// Гостевой просмотр группы: ?group=X без авторизации — читать можно, писать нельзя
$guestGroupView = false;
if ($groupId && !$user) {
    $group = $groupRepo->findById($groupId);
    if ($group) {
        $guestGroupView = true;
        $currentGroup = $group;
        $guestGroupMessages = $groupRepo->getMessages($groupId);
        $pageTitle = 'Группа: ' . ($group['name'] ?? 'Чат');
        require __DIR__ . '/../templates/layout/header.php';
        require __DIR__ . '/../templates/group_guest.php';
        require __DIR__ . '/../templates/layout/footer.php';
        exit;
    }
}

if (!$user) {
    Auth::requireAuth();
}
$user = $user ?? Auth::user();
$msgRepo = new MessageRepository();
$userRepo = new UserRepo();
$isMaster = in_array($user['role'], ['master', 'admin'], true);

$conversations = $msgRepo->getConversationsForUser((int)$user['id'], $isMaster);
$myGroups = [];
try {
    $myGroups = $groupRepo->getGroupsForUser((int)$user['id']);
} catch (\Throwable $e) { }
$unreadCount = $msgRepo->countUnread((int)$user['id']);

$currentConv = null;
$currentGroup = null;
$messages = [];
$otherUser = null;

if ($groupId) {
    $uid = (int)$user['id'];
    if ($groupRepo->isMember($groupId, $uid)) {
        $currentGroup = $groupRepo->findById($groupId);
    } elseif (Auth::isAdmin() && $supportService->isSupportChat($groupId)) {
        $supportService->ensureAdminInSupportChat($groupId, $uid);
        $currentGroup = $groupRepo->findById($groupId);
        if ($currentGroup) {
            $myGroups = $groupRepo->getGroupsForUser($uid);
        }
    }
}

if ($convId) {
    $uid = (int)$user['id'];
    $stmt = \App\Database::get()->prepare("SELECT * FROM conversations WHERE id = ? AND (master_id = ? OR client_id = ?)");
    $stmt->execute([$convId, $uid, $uid]);
    $currentConv = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($currentConv) {
        $otherId = (int)$currentConv['master_id'] === (int)$user['id'] ? (int)$currentConv['client_id'] : (int)$currentConv['master_id'];
        $otherUser = $userRepo->findById($otherId) ?? $userRepo->findByIdForDisplay($otherId);
        $messages = $msgRepo->getMessages($convId);
        $msgRepo->markRead($convId, (int)$user['id']);
    }
} elseif ($toId) {
    $otherUser = $userRepo->getMasterProfile($toId, true);
    if (!$otherUser) {
        $u = $userRepo->findById($toId);
        if ($u && in_array($u['role'] ?? '', ['master', 'admin'], true)) {
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/messages.php');
            exit;
        }
        $otherUser = $u;
    }
    if ($otherUser && (int)$otherUser['id'] !== (int)$user['id']) {
        $masterId = in_array($user['role'], ['master', 'admin'], true) ? (int)$user['id'] : (int)$otherUser['id'];
        $clientId = in_array($user['role'], ['master', 'admin'], true) ? (int)$otherUser['id'] : (int)$user['id'];
        if (in_array($user['role'], ['master', 'admin'], true) || in_array($otherUser['role'], ['master', 'admin'], true)) {
            $convId = $msgRepo->getOrCreateConversation($masterId, $clientId);
            $currentConv = ['id' => $convId, 'master_id' => $masterId, 'client_id' => $clientId];
            $messages = $msgRepo->getMessages($convId);
            $msgRepo->markRead($convId, (int)$user['id']);
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            $loc = ($base ? rtrim($base, '/') . '/' : '/') . 'messages.php?conv=' . $convId;
            header('Location: ' . $loc);
            exit;
        }
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentConv) {
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        $msgRepo->send($currentConv['id'], (int)$user['id'], $body);
        $messages = $msgRepo->getMessages($currentConv['id']);
    }
}

$pageTitle = 'Сообщения';
$bodyClass = 'messages-page';
$isBanned = Auth::isBanned();
require __DIR__ . '/../templates/layout/header.php';
require __DIR__ . '/../templates/messages.php';
require __DIR__ . '/../templates/layout/footer.php';
