<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\NotificationRepository;

header('Content-Type: application/json; charset=utf-8');

Auth::init();
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$repo = new NotificationRepository();
$userId = (int)$user['id'];
$isAdmin = Auth::isAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? $_GET['action'] ?? '');
    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id && $repo->markReadForUser($id, $userId, $isAdmin)) {
            echo json_encode(['ok' => true]);
            exit;
        }
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        exit;
    }
}

$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$list = $repo->getForUser($userId, $isAdmin, $limit);
$count = $repo->countUnreadForUser($userId, $isAdmin);

$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';

$items = array_map(function ($n) use ($root) {
    $type = $n['type'] ?? '';
    $refId = (int)($n['ref_id'] ?? 0);
    $data = [];
    if (!empty($n['data'])) {
        try {
            $data = json_decode($n['data'], true) ?: [];
        } catch (\Throwable $e) { }
    }
    $url = $root . 'messages.php';
    $title = 'Уведомление';
    $text = '';
    switch ($type) {
        case 'message':
            $title = 'Новое сообщение';
            $from = $n['from_name'] ?? 'Кто-то';
            $text = 'От ' . $from;
            $url = $root . 'messages.php?conv=' . $refId;
            break;
        case 'master_request':
            $title = 'Заявка на роль мастера';
            $text = ($data['full_name'] ?? 'Пользователь') . ' — ' . ($data['email'] ?? '');
            $url = $root . 'admin/?page=requests';
            break;
        case 'booking_request':
            $title = 'Запрос на запись';
            $text = ($data['client_name'] ?? 'Клиент') . ' на ' . ($data['date'] ?? '');
            $url = $root . 'master_calendar.php';
            break;
        case 'booking_reminder':
            $title = 'Напоминание о записи';
            $text = ($data['date'] ?? '') . ' ' . ($data['slot'] ?? '') . ' — ' . ($data['client_name'] ?? 'Клиент');
            $url = $root . 'master_calendar.php?date=' . ($data['date'] ?? '');
            break;
        case 'support_message':
            $title = 'Сообщение в поддержке';
            $text = mb_substr($data['preview'] ?? 'Новое сообщение', 0, 80);
            $url = $root . 'messages.php?group=' . $refId;
            break;
        case 'rating_pending':
            $title = 'Новый отзыв на модерации';
            $text = ($data['comment_preview'] ?? 'Отзыв') . ' (★' . ($data['value'] ?? 0) . ')';
            $url = $root . 'admin/?page=content';
            break;
        default:
            $text = $n['data'] ?? '';
    }
    return [
        'id' => (int)$n['id'],
        'type' => $type,
        'title' => $title,
        'text' => $text,
        'url' => $url,
        'created_at' => $n['created_at'] ?? '',
    ];
}, $list);

echo json_encode([
    'count' => $count,
    'items' => $items,
]);
