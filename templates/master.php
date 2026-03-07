<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
$lastSeen = $master['last_seen_at'] ?? null;
$masterOnline = $lastSeen && (time() - strtotime($lastSeen)) < 120;
$masterStatusText = $masterOnline ? 'Онлайн' : (!empty($lastSeen) ? 'Был в сети: ' . date('d.m.Y H:i', strtotime($lastSeen)) : '—');
$formatTime = static function (?string $value): ?string {
    if (!$value) return null;
    $value = trim($value);
    if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) return substr($value, 0, 5);
    if (preg_match('/^\d{1,2}:\d{2}$/', $value)) return $value;
    return null;
};
$buildSocialUrl = static function (?string $value, string $network): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    $username = ltrim($value, '@/');
    if ($username === '') return null;
    if ($network === 'instagram') return 'https://instagram.com/' . $username;
    if ($network === 'vk') return 'https://vk.com/' . $username;
    if ($network === 'telegram') return 'https://t.me/' . $username;
    if ($network === 'youtube') return 'https://youtube.com/@' . ltrim($username, '@');
    if ($network === 'max') return 'https://max.ru/' . $username;
    return null;
};
$socialLinks = [
    'Instagram' => $buildSocialUrl($master['instagram'] ?? null, 'instagram'),
    'ВКонтакте' => $buildSocialUrl($master['vk'] ?? null, 'vk'),
    'Telegram' => $buildSocialUrl($master['telegram'] ?? null, 'telegram'),
    'YouTube' => $buildSocialUrl($master['youtube'] ?? null, 'youtube'),
    'MAX' => $buildSocialUrl($master['max_link'] ?? null, 'max'),
];
$workStart = $formatTime($schedule['work_start'] ?? null);
$workEnd = $formatTime($schedule['work_end'] ?? null);
?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="profile-layout">
    <aside class="profile-sidebar">
        <div class="profile-cover"<?= !empty($master['banner_path']) ? ' style="background-image: url(\'' . htmlspecialchars($root . ltrim($master['banner_path'] ?? '', '/')) . '\');"' : '' ?>></div>
        <div class="avatar-block">
            <?php if (!empty($master['avatar_path'])): ?>
                <img src="<?= htmlspecialchars($root . ltrim($master['avatar_path'] ?? '', '/')) ?>" alt="" class="avatar-img">
            <?php else: ?>
                <div class="avatar-placeholder"><?= mb_strtoupper(mb_substr($master['full_name'] ?? '?', 0, 1)) ?></div>
            <?php endif; ?>
            <h1 class="name"><?= htmlspecialchars($master['full_name']) ?><?= !empty($master['is_verified']) ? ' ✓' : '' ?></h1>
            <span class="role-badge"><?= htmlspecialchars($roleLabel) ?></span>
            <div class="info-line">Зарегистрирован: <?= !empty($master['created_at']) ? date('d.m.Y', strtotime($master['created_at'])) : '—' ?></div>
            <div class="info-line <?= $masterOnline ? 'info-line--online' : '' ?>"><?= $masterOnline ? 'Онлайн' : $masterStatusText ?></div>
            <div class="info-line">
                ★ Рейтинг: <?= htmlspecialchars($master['rating_avg'] ?? '0') ?> (<?= (int)($master['rating_count'] ?? 0) ?> отзывов)
                <a href="<?= htmlspecialchars($root . 'reviews.php?id=' . (int)$master['id']) ?>" class="btn-link-reviews"><?= $isOwner ? 'Мои отзывы' : 'Просмотреть отзывы' ?></a>
            </div>
            <?php if (!$isOwner && $workStart && $workEnd): ?>
                <div class="info-line" style="margin-top: 8px; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm);">
                    Рабочие часы: <?= htmlspecialchars($workStart) ?> - <?= htmlspecialchars($workEnd) ?>
                </div>
            <?php endif; ?>
            <?php if (array_filter($socialLinks)): ?>
                <div class="info-line" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($socialLinks as $label => $url): ?>
                        <?php if (!$url) continue; ?>
                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-small" style="text-decoration: none;"><?= htmlspecialchars($label) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($isOwner): ?>
                <a href="<?= htmlspecialchars($root . 'settings.php') ?>" class="btn btn-primary btn-edit">Настройки профиля</a>
                <a href="<?= htmlspecialchars($root . 'master_calendar.php') ?>" class="btn btn-secondary btn-edit" style="margin-top: 8px; display: inline-block;">Мой календарь</a>
                <p style="margin-top: 16px; font-size: 0.9rem; color: var(--text-muted);"><a href="<?= htmlspecialchars($root . 'settings.php') ?>">Услуги и всё остальное</a> — в настройках</p>
            <?php elseif ($user && $user['role'] === 'client'): ?>
                <a href="<?= htmlspecialchars($root . 'booking.php?master=' . (int)$master['id']) ?>" class="btn btn-primary btn-edit">Забронировать</a>
                <a href="<?= htmlspecialchars($root . 'messages.php?to=' . (int)$master['id']) ?>" class="btn btn-secondary btn-edit" style="margin-top: 8px; display: inline-block;">Написать сообщение</a>
            <?php elseif (!$isOwner): ?>
                <a href="<?= htmlspecialchars($root . 'booking.php?master=' . (int)$master['id']) ?>" class="btn btn-primary btn-edit">Забронировать</a>
            <?php endif; ?>
        </div>
    </aside>

    <div class="profile-wall">
        <div class="wall-header-row" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--border);">
            <span class="wall-title" style="margin: 0; padding: 0; border: none;">Стена</span>
            <div class="wall-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if ($isOwner): ?>
                    <a href="<?= htmlspecialchars($root . 'reviews.php?id=' . (int)$master['id']) ?>" class="btn-link-reviews">Все отзывы</a>
                    <?php if (!empty($pendingRatings)): ?>
                    <button type="button" class="btn btn-secondary btn-small" id="btnPendingRatings">
                        Ожидают подтверждения (<?= count($pendingRatings) ?>)
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary btn-small" id="btnNewPost">Новая запись</button>
                <?php elseif ($canRate): ?>
                    <button type="button" class="btn btn-primary btn-small" id="btnLeaveReview">Оставить отзыв</button>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($posts as $post): ?>
            <article class="post-card" style="margin: 0; border-radius: 0; border-left: none; border-right: none;">
                <div class="post-header" style="<?= $isOwner ? 'justify-content: space-between;' : '' ?>">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <?php if (!empty($master['avatar_path'])): ?>
                            <img src="<?= htmlspecialchars($root . ltrim($master['avatar_path'] ?? '', '/')) ?>" alt="" class="post-avatar">
                        <?php else: ?>
                            <div class="post-avatar avatar-initials-sm"><?= mb_strtoupper(mb_substr($master['full_name'] ?? '?', 0, 1)) ?></div>
                        <?php endif; ?>
                        <div>
                            <span class="post-author"><?= htmlspecialchars($master['full_name']) ?></span>
                            <div class="post-date"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php if ($isOwner): ?>
                        <form method="post" action="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>" style="margin: 0;" onsubmit="return confirm('Удалить запись?');">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                            <button type="submit" class="btn btn-small">Удалить</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (!empty(trim($post['content_text'] ?? ''))): ?>
                    <div class="post-text"><?= nl2br(htmlspecialchars($post['content_text'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($post['media'])): ?>
                    <div class="post-media">
                        <?php foreach ($post['media'] as $med): ?>
                            <?php if ($med['media_type'] === 'image'): ?>
                                <img src="<?= htmlspecialchars($root . ltrim($med['file_path'] ?? '', '/')) ?>" alt="">
                            <?php elseif ($med['media_type'] === 'video'): ?>
                                <video controls src="<?= htmlspecialchars($root . ltrim($med['file_path'] ?? '', '/')) ?>"></video>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?>
            <div style="padding: 48px 24px; text-align: center; color: var(--text-muted); font-size: 1rem;">
                <?= $isOwner ? 'Записей пока нет. Нажмите «Новая запись» выше.' : 'Пока нет публикаций.' ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canRate): ?>
<!-- Модальное окно: оставить отзыв -->
<div id="modalReview" class="modal-overlay" style="display: none;" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width: 420px;">
        <button type="button" class="modal-close" id="modalReviewClose" aria-label="Закрыть">×</button>
        <div class="modal-body">
            <h2 class="modal-name">Ваша оценка</h2>
            <form method="post" action="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>">
                <input type="hidden" name="action" value="rate">
                <div class="form-group">
                    <label>Оценка (1–5)</label>
                    <select name="rating" style="width: 100%; padding: 10px; background: var(--bg-dark); border: 1px solid var(--border); color: var(--text);">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= ($myRating && (int)$myRating['value'] === $i) ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Комментарий (необязательно)</label>
                    <textarea name="comment" rows="3"><?= $myRating ? htmlspecialchars($myRating['comment'] ?? '') : '' ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Сохранить оценку</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isOwner && !empty($pendingRatings)): ?>
<!-- Модальное окно: ожидающие отзывы -->
<div id="modalPending" class="modal-overlay" style="display: none;" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width: 520px;">
        <button type="button" class="modal-close" id="modalPendingClose" aria-label="Закрыть">×</button>
        <div class="modal-body">
            <h2 class="modal-name">Ожидают подтверждения</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0 0 16px;">Подтвердите, что клиент был на сеансе — тогда отзыв появится на странице.</p>
            <?php foreach ($pendingRatings as $r): ?>
                <div style="padding: 12px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <span style="flex: 1; font-size: 0.95rem;"><?= htmlspecialchars($r['full_name']) ?> ★ <?= (int)$r['value'] ?> — <?= htmlspecialchars(mb_substr($r['comment'] ?? '', 0, 80)) ?></span>
                    <form method="post" action="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>" style="display:inline;">
                        <input type="hidden" name="action" value="approve_rating">
                        <input type="hidden" name="rating_id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-small">Подтвердить</button>
                    </form>
                    <form method="post" action="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>" style="display:inline;">
                        <input type="hidden" name="action" value="reject_rating">
                        <input type="hidden" name="rating_id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn btn-small">Отклонить</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isOwner): ?>
<!-- Модальное окно: новая запись -->
<div id="modalPost" class="modal-overlay" style="display: none;" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width: 480px;">
        <button type="button" class="modal-close" id="modalPostClose" aria-label="Закрыть">×</button>
        <div class="modal-body">
            <h2 class="modal-name">Новая запись</h2>
            <form method="post" action="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="post">
                <div class="form-group">
                    <label>Текст</label>
                    <textarea name="content_text" rows="4" placeholder="Что нового? Добавьте описание и медиа..."></textarea>
                </div>
                <div class="form-group">
                    <label class="btn btn-secondary file-input-label">
                        <input type="file" name="media[]" accept="image/*,video/*" multiple class="file-input-hidden">
                        Добавить медиа
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Опубликовать</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    function openModal(id) {
        var m = document.getElementById(id);
        if (m) { m.style.display = 'flex'; m.classList.add('is-open'); }
    }
    function closeModal(id) {
        var m = document.getElementById(id);
        if (m) { m.style.display = 'none'; m.classList.remove('is-open'); }
    }
    document.getElementById('btnLeaveReview') && document.getElementById('btnLeaveReview').addEventListener('click', function() { openModal('modalReview'); });
    <?php if ($canRate && !empty($_GET['review'])): ?>openModal('modalReview');<?php endif; ?>
    document.getElementById('modalReviewClose') && document.getElementById('modalReviewClose').addEventListener('click', function() { closeModal('modalReview'); });
    document.getElementById('modalReview') && document.getElementById('modalReview').addEventListener('click', function(e) { if (e.target === this) closeModal('modalReview'); });
    document.getElementById('btnPendingRatings') && document.getElementById('btnPendingRatings').addEventListener('click', function() { openModal('modalPending'); });
    document.getElementById('modalPendingClose') && document.getElementById('modalPendingClose').addEventListener('click', function() { closeModal('modalPending'); });
    document.getElementById('modalPending') && document.getElementById('modalPending').addEventListener('click', function(e) { if (e.target === this) closeModal('modalPending'); });
    document.getElementById('btnNewPost') && document.getElementById('btnNewPost').addEventListener('click', function() { openModal('modalPost'); });
    document.getElementById('modalPostClose') && document.getElementById('modalPostClose').addEventListener('click', function() { closeModal('modalPost'); });
    document.getElementById('modalPost') && document.getElementById('modalPost').addEventListener('click', function(e) { if (e.target === this) closeModal('modalPost'); });
})();
</script>
