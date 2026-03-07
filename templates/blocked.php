<?php
$root = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';
$banReason = trim($bannedUser['ban_reason'] ?? '');
$supportUrl = $root . 'support.php';
?>
<div class="blocked-card">
    <div class="blocked-card__icon" aria-hidden="true"></div>
    <h1 class="blocked-card__title">Аккаунт заблокирован</h1>
    <p class="blocked-card__text">Ваш аккаунт был заблокирован администратором.</p>
    <div class="blocked-card__reason">
        <span class="blocked-card__reason-label">Причина:</span>
        <span class="blocked-card__reason-text"><?= $banReason !== '' ? nl2br(htmlspecialchars($banReason)) : 'не указана' ?></span>
    </div>
    <p class="blocked-card__hint">По вопросам разблокировки обратитесь к администрации студии.</p>
    <a href="<?= htmlspecialchars($supportUrl) ?>" class="blocked-card__btn">Обратиться в поддержку</a>
</div>
