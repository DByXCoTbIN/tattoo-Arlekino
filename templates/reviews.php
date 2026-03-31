<?php $base = defined('BASE_PATH') ? BASE_PATH : ''; $root = ($base !== '') ? rtrim($base, '/') . '/' : '/'; ?>
<div class="reviews-page">
    <p style="margin-bottom: 20px;"><a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>" class="reviews-back">← <?= htmlspecialchars($master['full_name']) ?></a></p>
    <div class="card reviews-header-card">
        <h1 class="card-title">Отзывы о <?= htmlspecialchars($master['full_name']) ?></h1>
        <div class="reviews-master-info">
            <?php if (!empty($master['avatar_path'])): ?>
                <img src="<?= htmlspecialchars($root . ltrim($master['avatar_path'], '/')) ?>" alt="" class="reviews-avatar">
            <?php else: ?>
                <div class="reviews-avatar avatar-initials"><?= mb_strtoupper(mb_substr($master['full_name'] ?? '?', 0, 1)) ?></div>
            <?php endif; ?>
            <div>
                <h2 style="margin: 0 0 4px 0; font-size: 1.25rem;"><?= htmlspecialchars($master['full_name']) ?><?= !empty($master['is_verified']) ? ' ✓' : '' ?></h2>
                <div class="reviews-rating">★ <?= htmlspecialchars($master['rating_avg'] ?? '0') ?> (<?= (int)($master['rating_count'] ?? 0) ?> оценок)</div>
            </div>
        </div>
    </div>

    <div class="card reviews-list">
        <?php foreach ($ratings as $r): ?>
            <article class="review-item">
                <div class="review-header">
                    <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                    <span class="review-stars"><?= str_repeat('★', (int)$r['value']) ?><span class="review-stars-dim"><?= str_repeat('★', 5 - (int)$r['value']) ?></span></span>
                    <span class="review-date"><?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
                </div>
                <?php if (!empty(trim($r['comment'] ?? ''))): ?>
                    <p class="review-comment"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (empty($ratings)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 32px;">Пока нет отзывов.</p>
        <?php endif; ?>
    </div>
</div>
