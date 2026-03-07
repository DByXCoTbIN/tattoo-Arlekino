<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
$displayName = ($master && isset($master['full_name'])) ? $master['full_name'] : ($user['full_name'] ?? '');
$roleLabel = $user['role'] === 'admin' ? 'Администратор' : ($isMaster ? 'Мастер' : 'Клиент');
$lastSeen = $user['last_seen_at'] ?? null;
$userOnline = $lastSeen && (time() - strtotime($lastSeen)) < 120;
$userStatusText = $userOnline ? 'Онлайн' : (!empty($lastSeen) ? 'Был в сети: ' . date('d.m.Y H:i', strtotime($lastSeen)) : '—');
?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="profile-layout">
    <aside class="profile-sidebar">
        <div class="profile-cover"<?php if ($isMaster && !empty($master['banner_path'])): ?> style="background-image: url('<?= htmlspecialchars($root . ltrim($master['banner_path'] ?? '', '/')) ?>');"<?php endif; ?>></div>
        <div class="avatar-block">
            <?php if (!empty($user['avatar_path'])): ?>
                <img src="<?= htmlspecialchars($root . ltrim($user['avatar_path'] ?? '', '/')) ?>" alt="" class="avatar-img">
            <?php else: ?>
                <div class="avatar-placeholder"><?= mb_strtoupper(mb_substr($user['full_name'] ?? '?', 0, 1)) ?></div>
            <?php endif; ?>
            <h1 class="name"><?= htmlspecialchars($displayName) ?></h1>
            <span class="role-badge"><?= htmlspecialchars($roleLabel) ?></span>
            <div class="info-line">Зарегистрирован: <?= !empty($user['created_at']) ? date('d.m.Y', strtotime($user['created_at'])) : '—' ?></div>
            <div class="info-line <?= $userOnline ? 'info-line--online' : '' ?>"><?= $userOnline ? 'Онлайн' : $userStatusText ?></div>
            <?php if ($isMaster && $master): ?>
                <div class="info-line">
                    ★ Рейтинг: <?= htmlspecialchars($master['rating_avg'] ?? '0') ?> (<?= (int)($master['rating_count'] ?? 0) ?> отзывов)
                    <a href="<?= htmlspecialchars($root . 'reviews.php?id=' . (int)$user['id']) ?>" class="btn-link-reviews">Мои отзывы</a>
                </div>
            <?php endif; ?>
            <?php if ($isMaster): ?>
                <a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$user['id']) ?>" class="btn btn-edit">Моя публичная страница</a>
                <a href="<?= htmlspecialchars($root . 'settings.php') ?>" class="btn btn-primary btn-edit">Настройки профиля</a>
                <p style="margin-top: 16px; font-size: 0.9rem;"><a href="<?= htmlspecialchars($root . 'settings.php') ?>">Услуги и всё остальное</a> — в настройках</p>
            <?php else: ?>
                <a href="<?= htmlspecialchars($root . 'messages.php') ?>" class="btn btn-primary btn-edit">Сообщения</a>
            <?php endif; ?>
        </div>
    </aside>

    <div class="profile-wall">
        <div class="wall-title"><?= $isMaster ? 'Стена' : 'Моя страница' ?></div>
        <?php if ($isMaster): ?>
            <div class="composer">
                <form method="post" action="<?= htmlspecialchars($root . 'cabinet.php') ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="post">
                    <textarea name="content_text" rows="3" placeholder="Что нового? Добавьте описание и медиа..."></textarea>
                    <div class="composer-actions">
                        <label class="btn btn-secondary file-input-label">
                            <input type="file" name="media[]" accept="image/*,video/*" multiple class="file-input-hidden">
                            Добавить медиа
                        </label>
                        <button type="submit" class="btn btn-primary">Опубликовать</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php
            $pendingRatings = $isMaster ? array_filter($ratings, function ($r) { return ($r['status'] ?? '') === 'pending'; }) : [];
            $approvedRatings = $isMaster ? array_filter($ratings, function ($r) { $s = $r['status'] ?? ''; return $s === 'approved' || !empty($r['admin_restored']); }) : [];
            ?>
        <?php if ($isMaster && !empty($pendingRatings)): ?>
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); background: rgba(212,175,55,0.08); border-radius: var(--radius-sm); margin: 0 20px 16px;">
                <strong style="font-size: 0.9rem;">Ожидают подтверждения</strong>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 4px 0 8px;">Подтвердите, что клиент был на сеансе — тогда отзыв появится на странице.</p>
                <?php foreach ($pendingRatings as $r): ?>
                    <div style="padding: 10px 0; font-size: 0.9rem; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <span><?= htmlspecialchars($r['full_name']) ?> ★ <?= (int)$r['value'] ?> — <?= htmlspecialchars(mb_substr($r['comment'] ?? '', 0, 80)) ?></span>
                        <form method="post" action="<?= htmlspecialchars($root . 'cabinet.php') ?>" style="display:inline;">
                            <input type="hidden" name="action" value="approve_rating">
                            <input type="hidden" name="rating_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-primary" style="padding: 4px 12px; font-size: 0.85rem;">Подтвердить</button>
                        </form>
                        <form method="post" action="<?= htmlspecialchars($root . 'cabinet.php') ?>" style="display:inline;">
                            <input type="hidden" name="action" value="reject_rating">
                            <input type="hidden" name="rating_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn" style="padding: 4px 12px; font-size: 0.85rem;">Отклонить</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($isMaster): ?>
            <p style="padding: 12px 20px; margin: 0; border-bottom: 1px solid var(--border);">
                <a href="<?= htmlspecialchars($root . 'reviews.php?id=' . (int)$user['id']) ?>" class="btn-link-reviews">Все отзывы на отдельной странице</a>
            </p>
        <?php endif; ?>

        <?php if ($isMaster): ?>
            <?php foreach ($posts as $post): ?>
                <article class="post-card" style="margin: 0; border-radius: 0; border-left: none; border-right: none;">
                    <div class="post-header" style="justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="post-date"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></span>
                        </div>
<form method="post" action="<?= htmlspecialchars($root . 'cabinet.php') ?>" style="margin: 0;" onsubmit="return confirm('Удалить запись?');">
                                <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                            <button type="submit" class="btn" style="padding: 6px 12px; font-size: 0.85rem;">Удалить</button>
                        </form>
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
                <div style="padding: 32px 20px; text-align: center; color: var(--text-muted);">Записей пока нет. Опубликуйте первую!</div>
            <?php endif; ?>
        <?php else: ?>
            <div style="padding: 32px 20px; text-align: center; color: var(--text-muted);">
                Вы зарегистрированы как клиент. Смотрите работы мастеров на <a href="<?= htmlspecialchars($root . 'masters.php') ?>">странице мастеров</a> и пишите им в <a href="<?= htmlspecialchars($root . 'messages.php') ?>">сообщениях</a>.
            </div>
        <?php endif; ?>
    </div>
</div>
