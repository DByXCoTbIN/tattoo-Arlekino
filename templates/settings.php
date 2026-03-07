<?php $base = defined('BASE_PATH') ? BASE_PATH : ''; $root = ($base !== '') ? rtrim($base, '/') . '/' : '/'; ?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="settings-page">
    <h1 class="card-title">Настройки профиля</h1>
    <p style="color: var(--text-muted); margin-bottom: 24px;"><a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)($user['id'] ?? 0)) ?>">← Назад на страницу</a></p>

    <div class="card settings-card">
        <h2 class="card-title" style="font-size: 1.1rem;">Аватар</h2>
        <div class="settings-avatar-row">
            <?php if (!empty($user['avatar_path'])): ?>
                <img src="<?= htmlspecialchars($root . ltrim($user['avatar_path'], '/')) ?>" alt="" class="settings-avatar-preview">
            <?php else: ?>
                <div class="settings-avatar-placeholder"><?= mb_substr($user['full_name'] ?? '?', 0, 1) ?></div>
            <?php endif; ?>
            <div class="settings-avatar-actions">
                <form method="post" enctype="multipart/form-data" class="settings-file-form" id="avatarForm">
                    <input type="hidden" name="action" value="upload_avatar">
                    <label class="btn btn-secondary file-input-label">
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input-hidden" onchange="this.closest('form').submit()">
                        Выбрать фото
                    </label>
                </form>
                <?php if (!empty($user['avatar_path'])): ?>
                <form method="post" style="display:inline; margin-left:8px;">
                    <input type="hidden" name="action" value="remove_avatar">
                    <button type="submit" class="btn btn-ghost">Удалить</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card settings-card">
        <h2 class="card-title" style="font-size: 1.1rem;">Баннер профиля</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px;">Фон верхней части вашей публичной страницы</p>
        <?php if (!empty($master['banner_path'])): ?>
        <div class="settings-banner-preview" style="background-image: url('<?= htmlspecialchars($root . ltrim($master['banner_path'], '/')) ?>');"></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="settings-file-form">
            <input type="hidden" name="action" value="upload_banner">
            <label class="btn btn-secondary file-input-label">
                <input type="file" name="banner" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input-hidden" onchange="this.closest('form').submit()">
                <?= !empty($master['banner_path']) ? 'Заменить' : 'Выбрать' ?> изображение
            </label>
        </form>
        <?php if (!empty($master['banner_path'])): ?>
        <form method="post" style="margin-top: 12px;">
            <input type="hidden" name="action" value="remove_banner">
            <button type="submit" class="btn btn-ghost">Удалить баннер</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card settings-card">
        <h2 class="card-title" style="font-size: 1.1rem;">Информация и контакты</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="profile">
            <div class="form-group">
                <label>Имя</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>О себе</label>
                <textarea name="bio" rows="4"><?= htmlspecialchars($master['bio'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Телефон</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($master['phone'] ?? '') ?>" placeholder="+7 999 123-45-67">
            </div>
            <div class="form-group">
                <label>Instagram</label>
                <input type="text" name="instagram" value="<?= htmlspecialchars($master['instagram'] ?? '') ?>" placeholder="@username">
            </div>
            <div class="form-group">
                <label>ВКонтакте</label>
                <input type="text" name="vk" value="<?= htmlspecialchars($master['vk'] ?? '') ?>" placeholder="https://vk.com/username">
            </div>
            <div class="form-group">
                <label>Telegram</label>
                <input type="text" name="telegram" value="<?= htmlspecialchars($master['telegram'] ?? '') ?>" placeholder="@username или https://t.me/username">
            </div>
            <div class="form-group">
                <label>YouTube</label>
                <input type="text" name="youtube" value="<?= htmlspecialchars($master['youtube'] ?? '') ?>" placeholder="https://youtube.com/@channel">
            </div>
            <div class="form-group">
                <label>MAX</label>
                <input type="text" name="max_link" value="<?= htmlspecialchars($master['max_link'] ?? '') ?>" placeholder="@username или ссылка">
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
    </div>

    <div class="card settings-card">
        <h2 class="card-title" style="font-size: 1.1rem;">Расписание (для записи клиентов)</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px;">Укажите рабочие часы. Клиенты отправляют запрос на дату, вы подтверждаете и указываете время сеанса индивидуально.</p>
        <form method="post" action="">
            <input type="hidden" name="action" value="save_schedule">
            <div class="form-group" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                <div>
                    <label>Начало работы</label>
                    <input type="time" name="work_start" value="<?= htmlspecialchars(substr($schedule['work_start'] ?? '10:00:00', 0, 5)) ?>" min="00:00" max="23:59" required id="workStart">
                </div>
                <div>
                    <label>Конец работы</label>
                    <input type="time" name="work_end" value="<?= htmlspecialchars(substr($schedule['work_end'] ?? '18:00:00', 0, 5)) ?>" min="00:00" max="23:59" required id="workEnd">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить расписание</button>
        </form>
    </div>

    <?php if (!empty($services)): ?>
    <div class="card settings-card">
        <h2 class="card-title" style="font-size: 1.1rem;">Мои услуги</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="save_services">
            <div class="form-group">
                <?php foreach ($services as $sv): ?>
                <label class="settings-checkbox"><input type="checkbox" name="service_ids[]" value="<?= (int)$sv['id'] ?>" <?= in_array((int)$sv['id'], $masterServiceIds, true) ? 'checked' : '' ?>> <?= htmlspecialchars($sv['name']) ?></label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
    </div>
    <?php endif; ?>
</div>
