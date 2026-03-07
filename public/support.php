<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\SupportChatService;

Auth::init();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';

if (!Auth::id()) {
    header('Location: ' . $root . 'login.php?redirect=' . urlencode($root . 'support.php'));
    exit;
}

$user = Auth::user();
if (!$user && Auth::isBanned()) {
    $user = Auth::bannedUser();
}
if (!$user) {
    header('Location: ' . $root . 'login.php');
    exit;
}

$supportService = new SupportChatService();

// Админ видит список всех чатов поддержки и может открыть любой
if (Auth::isAdmin()) {
    $threads = $supportService->getSupportThreadsForAdmin();
    $config = require dirname(__DIR__) . '/config/config.php';
    $siteName = \App\Settings::get('site_name', $config['site']['name']);
    $pageTitle = 'Чаты поддержки';
    $bodyClass = 'support-list-page';
    require __DIR__ . '/../templates/layout/header.php';
    require __DIR__ . '/../templates/support_list.php';
    require __DIR__ . '/../templates/layout/footer.php';
    exit;
}

// Обычный пользователь или заблокированный — свой чат (per-user) или общий (если миграция v16 не применена)
$groupId = $supportService->getOrCreateSupportChatForUser((int)$user['id']);
if ($groupId === null || $groupId <= 0) {
    $groupId = $supportService->getOrCreateLegacySupportChat();
    if ($groupId <= 0) {
        header('Location: ' . $root);
        exit;
    }
    $groupRepo = new \App\GroupChatRepository();
    if (!$groupRepo->isMember($groupId, (int)$user['id'])) {
        $groupRepo->addMember($groupId, (int)$user['id']);
    }
}

header('Location: ' . $root . 'messages.php?group=' . $groupId);
exit;
