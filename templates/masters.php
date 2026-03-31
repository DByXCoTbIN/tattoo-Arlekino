<?php $base = defined('BASE_PATH') ? BASE_PATH : ''; $root = ($base !== '') ? rtrim($base, '/') . '/' : '/'; ?>
<h1 class="card-title"><?= !empty($currentService['name']) ? 'Мастера: ' . htmlspecialchars($currentService['name']) : 'Мастера тату-студии' ?></h1>
<?php if (!empty($currentService)): ?>
    <p style="margin-bottom: 16px; color: var(--text-muted);">Услуга: <strong style="color: var(--accent-gold);"><?= htmlspecialchars($currentService['name']) ?></strong>. <a href="<?= htmlspecialchars($root . 'masters.php') ?>">Показать всех мастеров</a></p>
<?php endif; ?>
<form method="get" class="masters-toolbar">
    <?php if (!empty($currentService['id'])): ?>
        <input type="hidden" name="service" value="<?= (int)$currentService['id'] ?>">
    <?php endif; ?>
    <label for="mastersSort">Сортировка:</label>
    <select id="mastersSort" name="sort" onchange="this.form.submit()">
        <option value="reviews" <?= ($sort ?? 'reviews') === 'reviews' ? 'selected' : '' ?>>по отзывам (рейтинг)</option>
        <option value="days" <?= ($sort ?? 'reviews') === 'days' ? 'selected' : '' ?>>по стажу (дней с регистрации)</option>
        <option value="reviews_count" <?= ($sort ?? 'reviews') === 'reviews_count' ? 'selected' : '' ?>>по количеству отзывов</option>
        <option value="alphabet" <?= ($sort ?? 'reviews') === 'alphabet' ? 'selected' : '' ?>>по алфавиту</option>
    </select>
</form>
<div class="masters-grid">
    <?php foreach ($masters as $m): ?>
        <?php
        $workedDays = 0;
        if (!empty($m['created_at'])) {
            $createdTs = strtotime((string)$m['created_at']);
            if ($createdTs !== false) {
                $workedDays = max(0, (int)floor((time() - $createdTs) / 86400));
            }
        }
        ?>
        <div class="master-card" onclick="location.href='<?= htmlspecialchars($root . 'master.php?id=' . (int)$m['id']) ?>'" style="cursor: pointer;">
            <div class="avatar-wrap">
                <?php if (!empty($m['avatar_path'])): ?>
                    <img src="<?= htmlspecialchars($root . ltrim($m['avatar_path'] ?? '', '/')) ?>" alt="">
                <?php else: ?>
                    <div class="avatar-initials-sm"><?= mb_strtoupper(mb_substr($m['full_name'] ?? '?', 0, 1)) ?></div>
                <?php endif; ?>
            </div>
            <div class="body">
                <h3 class="name"><?= htmlspecialchars($m['full_name']) ?><?= !empty($m['is_verified']) ? ' ✓' : '' ?></h3>
                <div class="rating">
                    ★ <?= htmlspecialchars($m['rating_avg'] ?? '0') ?> (<?= (int)($m['rating_count'] ?? 0) ?>)
                    <a href="<?= htmlspecialchars($root . 'reviews.php?id=' . (int)$m['id']) ?>" class="btn-link-reviews-sm" onclick="event.stopPropagation()">Отзывы</a>
                </div>
                <p class="master-meta">В студии: <?= $workedDays ?> дн.</p>
                <?php if (!empty($m['bio'])): ?>
                    <p class="bio"><?= htmlspecialchars(mb_substr($m['bio'], 0, 80)) ?><?= mb_strlen($m['bio']) > 80 ? '...' : '' ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if (empty($masters)): ?>
    <p class="card" style="color: var(--text-muted);">Мастеров пока нет.</p>
<?php endif; ?>
