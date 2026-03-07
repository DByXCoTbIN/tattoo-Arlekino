<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
$group = $currentGroup ?? [];
$messages = $guestGroupMessages ?? [];
function linkifyText($t) {
    $t = htmlspecialchars($t ?? '');
    return preg_replace_callback('/(https?:\/\/[^\s<>"\']+|www\.[^\s<>"\']+)/i', function($m) {
        $url = $m[1];
        if (stripos($url, 'http') !== 0) $url = 'https://' . $url;
        return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($m[1]) . '</a>';
    }, $t);
}
?>
<div class="group-guest-view">
    <h1 class="card-title"><?= htmlspecialchars($group['name'] ?? 'Группа') ?></h1>
    <p class="group-guest-notice">
        Вы просматриваете чат. <a href="<?= htmlspecialchars($root . 'login.php') ?>">Войдите</a> или <a href="<?= htmlspecialchars($root . 'register.php') ?>">зарегистрируйтесь</a>, чтобы писать сообщения.
    </p>
    <div class="card messages-chat messages-chat--guest">
        <div class="messages-chat-header">
            <h3 class="card-title">Сообщения</h3>
        </div>
        <div class="messages-area messages-area--fixed">
            <?php foreach ($messages as $m): ?>
            <div class="message-row<?= ((int)($m['sender_id'] ?? 0) === (int)($group['creator_id'] ?? 0) ? ' message-master' : '') ?>">
                <?php if (!empty($m['media_path']) && !empty($m['media_type'])): ?>
                    <div class="message-media">
                        <?php if ($m['media_type'] === 'image'): ?>
                            <img src="<?= htmlspecialchars($root . ltrim($m['media_path'], '/')) ?>" alt="">
                        <?php elseif ($m['media_type'] === 'video'): ?>
                            <video controls src="<?= htmlspecialchars($root . ltrim($m['media_path'], '/')) ?>"></video>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($m['body'])): ?>
                    <div class="message-bubble"><?= nl2br(linkifyText($m['body'])) ?></div>
                <?php endif; ?>
                <div class="message-time"><?= htmlspecialchars($m['full_name'] ?? '') ?> · <?= date('d.m.Y H:i', strtotime($m['created_at'] ?? 'now')) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($messages)): ?>
                <p style="color: var(--text-muted);">Пока нет сообщений.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
