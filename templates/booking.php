<?php $root = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/'; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="booking-page">
    <a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>" class="booking-back-link">← <?= htmlspecialchars($master['full_name']) ?></a>
    <h1 class="section-heading booking-page-main-title">Запись к мастеру</h1>
    <p class="booking-page-sub">Выберите удобную дату. Мастер получит запрос и согласует время сеанса.</p>

    <?php if (!$schedule): ?>
        <div class="card studio-block booking-empty-card">
            <p>Мастер ещё не настроил расписание для онлайн-записи.</p>
            <a href="<?= htmlspecialchars($root . 'messages.php?to=' . (int)$master['id']) ?>" class="btn btn-primary">Написать сообщение</a>
        </div>
    <?php else: ?>
    <form method="get" action="" class="card studio-block booking-datetime-card" id="bookingDateForm">
        <input type="hidden" name="master" value="<?= (int)$master['id'] ?>">
        <div class="form-group">
            <label for="bookingDate">Дата</label>
            <input type="date" id="bookingDate" name="date" value="<?= htmlspecialchars($selectedDate) ?>" min="<?= date('Y-m-d') ?>"
                   onchange="this.form.submit()">
        </div>
    </form>
    <script>
    (function () {
        var offW = <?= json_encode($offWeekdays ?? [], JSON_UNESCAPED_UNICODE) ?>;
        var offD = <?= json_encode($dayOffDates ?? [], JSON_UNESCAPED_UNICODE) ?>;
        var form = document.getElementById('bookingDateForm');
        var input = document.getElementById('bookingDate');
        if (!form || !input) return;
        function isoWeekdayN(ymd) {
            var p = ymd.split('-').map(Number);
            var d = new Date(Date.UTC(p[0], p[1] - 1, p[2])).getUTCDay();
            return d === 0 ? 7 : d;
        }
        function isOff(ymd) {
            if (!ymd) return false;
            if (offD.indexOf(ymd) >= 0) return true;
            return offW.indexOf(isoWeekdayN(ymd)) >= 0;
        }
        form.addEventListener('submit', function (e) {
            var v = input.value;
            if (v && isOff(v)) {
                e.preventDefault();
                alert('Эта дата недоступна для записи (выходной).');
            }
        });
    })();
    </script>

    <?php if ($selectedDate): ?>
        <div class="card studio-block booking-request-card">
            <h2>Запрос на <?= date('d.m.Y', strtotime($selectedDate)) ?></h2>
            <p class="booking-request-lead">Мастер получит уведомление и подтвердит запись, указав время сеанса (с какого по какой час).</p>
            <form method="post" action="">
                <input type="hidden" name="action" value="request">
                <input type="hidden" name="booking_date" value="<?= htmlspecialchars($selectedDate) ?>">
                <button type="submit" class="btn btn-primary">Отправить запрос</button>
            </form>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
