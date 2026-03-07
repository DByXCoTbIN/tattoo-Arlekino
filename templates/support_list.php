<?php
$root = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';
?>
<div class="support-list">
    <h1 class="support-list__title">Чаты поддержки</h1>
    <p class="support-list__intro">Выберите чат с пользователем, чтобы ответить. Каждый пользователь видит только свой чат с поддержкой.</p>
    <?php if (empty($threads)): ?>
    <p class="support-list__empty">Пока нет обращений. Когда пользователь нажмёт «Поддержка», здесь появится его чат.</p>
    <?php else: ?>
    <ul class="support-list__threads">
        <?php foreach ($threads as $t): ?>
        <li class="support-list__item">
            <a href="<?= htmlspecialchars($root . 'messages.php?group=' . (int)$t['group_id']) ?>" class="support-list__link">
                <span class="support-list__name"><?= htmlspecialchars($t['user_name'] ?? 'Пользователь #' . (int)$t['user_id']) ?></span>
                <?php if (!empty($t['last_at'])): ?>
                <span class="support-list__date"><?= date('d.m.Y H:i', strtotime($t['last_at'])) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
