<?php $root = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/'; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="booking-page">
    <p style="margin-bottom: 24px;"><a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>">← <?= htmlspecialchars($master['full_name']) ?></a></p>
    <h1 class="card-title">Запись к мастеру</h1>

    <?php if (!$schedule): ?>
        <div class="card settings-card">
            <p style="color: var(--text-muted); margin: 0 0 16px;">Мастер ещё не настроил расписание для онлайн-записи.</p>
            <p style="margin: 0;"><a href="<?= htmlspecialchars($root . 'messages.php?to=' . (int)$master['id']) ?>" class="btn btn-primary">Написать сообщение</a></p>
        </div>
    <?php else: ?>
    <form method="get" action="" class="card booking-datetime-card" style="margin-bottom: 24px;">
        <input type="hidden" name="master" value="<?= (int)$master['id'] ?>">
        <div class="form-group">
            <label for="bookingDate">Выберите дату</label>
            <input type="date" id="bookingDate" name="date" value="<?= htmlspecialchars($selectedDate) ?>" min="<?= date('Y-m-d') ?>"
                   onchange="this.form.submit()">
        </div>
    </form>

    <?php if ($selectedDate): ?>
        <div class="card settings-card">
            <h2 style="font-size: 1.1rem; margin: 0 0 16px;">Запрос на запись на <?= date('d.m.Y', strtotime($selectedDate)) ?></h2>
            <p style="color: var(--text-muted); margin: 0 0 16px;">Мастер получит ваш запрос и подтвердит запись, указав время сеанса (с чего по что).</p>
            <form method="post" action="">
                <input type="hidden" name="action" value="request">
                <input type="hidden" name="booking_date" value="<?= htmlspecialchars($selectedDate) ?>">
                <button type="submit" class="btn btn-primary">Отправить запрос</button>
            </form>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

