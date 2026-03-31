<?php $base = defined('BASE_PATH') ? BASE_PATH : ''; $root = ($base !== '') ? rtrim($base, '/') . '/' : '/'; ?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="settings-page">
    <h1 class="section-heading settings-page-main-title">Настройки профиля</h1>
    <a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)($user['id'] ?? 0)) ?>" class="settings-back-link">← Назад на страницу</a>

    <h2 class="section-heading settings-block-heading">Аватар</h2>
    <div class="card settings-panel">
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

    <h2 class="section-heading settings-block-heading">Баннер профиля</h2>
    <div class="card settings-panel">
        <p class="settings-section-lead" style="margin-top: 0;">Фон верхней части вашей публичной страницы</p>
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

    <h2 class="section-heading settings-block-heading">Информация и контакты</h2>
    <div class="card settings-panel">
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
            <div class="settings-form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>

    <h2 class="section-heading settings-block-heading">Видимость на сайте</h2>
    <div class="card settings-panel">
        <?php
        $admVis = trim((string)($master['admin_profile_visibility'] ?? ''));
        if ($admVis !== ''): ?>
            <div class="settings-callout" role="status">
                Администратор задал правило отображения вашего профиля: <strong><?= $admVis === 'force_show' ? 'принудительно показывать' : 'принудительно скрывать' ?></strong>. Пока это действует, переключатель ниже может не влиять на сайт.
            </div>
        <?php endif; ?>
        <p class="settings-section-lead" style="<?= $admVis !== '' ? '' : 'margin-top:0;' ?>">Скрытый профиль не отображается в списке мастеров и не открывается по ссылке для гостей. Онлайн-запись к вам будет недоступна.</p>
        <form method="post" action="">
            <input type="hidden" name="action" value="save_profile_visibility">
            <div class="settings-visibility-panel">
                <label class="settings-checkbox"><input type="checkbox" name="profile_hidden" value="1" <?= !empty($master['profile_hidden_by_master']) ? 'checked' : '' ?>> Скрыть мой профиль от посетителей сайта</label>
            </div>
            <div class="settings-form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>

    <h2 class="section-heading settings-block-heading">Расписание для записи клиентов</h2>
    <div class="card settings-panel settings-panel--schedule">
        <p class="settings-section-lead" style="margin-top: 0;">Укажите рабочие часы. Клиенты отправляют запрос на дату, вы подтверждаете и указываете время сеанса. Отметьте постоянные выходные по дням недели; отдельные даты (отпуск, праздники) — в колонке справа.</p>
        <div class="settings-schedule-layout">
            <div class="settings-schedule-main">
                <form method="post" action="">
                    <input type="hidden" name="action" value="save_schedule">
                    <div class="settings-schedule-times">
                        <div class="form-group">
                            <label for="workStart">Начало работы</label>
                            <input type="time" name="work_start" value="<?= htmlspecialchars(substr($schedule['work_start'] ?? '10:00:00', 0, 5)) ?>" min="00:00" max="23:59" required id="workStart">
                        </div>
                        <div class="form-group">
                            <label for="workEnd">Конец работы</label>
                            <input type="time" name="work_end" value="<?= htmlspecialchars(substr($schedule['work_end'] ?? '18:00:00', 0, 5)) ?>" min="00:00" max="23:59" required id="workEnd">
                        </div>
                    </div>
                    <?php
                    $ow = $schedule['off_weekdays'] ?? [];
                    $weekdayOpts = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
                    ?>
                    <div class="form-group" style="margin-top: 22px;">
                        <label>Не принимаю онлайн-запись в эти дни недели</label>
                        <div class="settings-weekday-pills" role="group" aria-label="Выходные по дням недели">
                            <?php foreach ($weekdayOpts as $num => $label): ?>
                                <label class="settings-weekday-pill">
                                    <input type="checkbox" name="off_weekday[]" value="<?= (int)$num ?>" <?= in_array($num, $ow, true) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="settings-form-actions">
                        <button type="submit" class="btn btn-primary">Сохранить расписание</button>
                    </div>
                </form>
            </div>
            <aside class="settings-schedule-sidebar">
                <h3 class="settings-sidebar-title">Отдельные выходные</h3>
                <p class="settings-sidebar-lead">Одноразовые дни без записи (не совпадают с графиком слева).</p>
                <form method="post" action="" class="settings-dayoff-add">
                    <input type="hidden" name="action" value="add_day_off">
                    <div class="form-group">
                        <label for="offDatePick">Дата</label>
                        <input type="date" name="off_date" id="offDatePick" min="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-secondary">Добавить</button>
                </form>
                <?php if (!empty($dayOffDates)): ?>
                    <ul class="settings-dayoff-list">
                        <?php foreach ($dayOffDates as $od): ?>
                            <li class="settings-dayoff-item">
                                <time datetime="<?= htmlspecialchars($od) ?>"><?= date('d.m.Y', strtotime($od)) ?></time>
                                <form method="post" action="" style="margin:0;">
                                    <input type="hidden" name="action" value="remove_day_off">
                                    <input type="hidden" name="off_date" value="<?= htmlspecialchars($od) ?>">
                                    <button type="submit" class="btn btn-ghost btn-small">Убрать</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="settings-empty-hint">Нет дополнительных выходных.</p>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <?php if (!empty($services)): ?>
    <h2 class="section-heading settings-block-heading">Мои услуги</h2>
    <div class="card settings-panel">
        <form method="post" action="">
            <input type="hidden" name="action" value="save_services">
            <div class="form-group">
                <?php foreach ($services as $sv): ?>
                <label class="settings-checkbox"><input type="checkbox" name="service_ids[]" value="<?= (int)$sv['id'] ?>" <?= in_array((int)$sv['id'], $masterServiceIds, true) ? 'checked' : '' ?>> <?= htmlspecialchars($sv['name']) ?></label>
                <?php endforeach; ?>
            </div>
            <div class="settings-form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
