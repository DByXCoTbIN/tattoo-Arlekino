<?php
$root = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';
$daysInMonth = (int)date('t', strtotime($monthStart));
$firstWeekday = (int)date('w', strtotime($monthStart)); // 0=Sunday
$firstWeekday = $firstWeekday === 0 ? 6 : $firstWeekday - 1; // Mon=0
?>
<div class="calendar-page">
    <a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>" class="settings-back-link">← Моя страница</a>
    <h1 class="section-heading calendar-page-main-title">Мой календарь</h1>
    <?php if ($error ?? ''): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success ?? ''): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (!empty($pendingRequests)): ?>
    <div class="card studio-block calendar-pending-card">
        <h2>Ожидают подтверждения</h2>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <?php foreach ($pendingRequests as $pr): ?>
            <li class="calendar-pending-item">
                <div class="calendar-pending-item__client"><?= htmlspecialchars($pr['client_name']) ?> — <?= date('d.m.Y', strtotime($pr['booking_date'])) ?></div>
                <div class="booking-time-row" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <form method="post" action="<?= htmlspecialchars($root . 'master_calendar.php?' . $redirectParams . '&date=' . urlencode($pr['booking_date'])) ?>" class="booking-confirm-form" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="booking_id" value="<?= (int)$pr['id'] ?>">
                        <input type="hidden" name="date" value="<?= htmlspecialchars($pr['booking_date']) ?>">
                        <input type="time" name="slot_start" min="00:00" max="23:59" required title="С">
                        <span>—</span>
                        <input type="time" name="slot_end" min="00:00" max="23:59" required title="До">
                        <button type="submit" class="btn btn-primary btn-small">Подтвердить</button>
                    </form>
                    <form method="post" action="<?= htmlspecialchars($root . 'master_calendar.php?' . $redirectParams) ?>" style="display:inline;">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="booking_id" value="<?= (int)$pr['id'] ?>">
                        <input type="hidden" name="date" value="<?= htmlspecialchars($pr['booking_date']) ?>">
                        <button type="submit" class="btn btn-secondary btn-small">Отклонить</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="calendar-nav-bar">
        <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $selectedDate ? '&date=' . urlencode($selectedDate) : '' ?>" class="btn btn-secondary" aria-label="Предыдущий месяц">←</a>
        <h2><?= htmlspecialchars($monthNames[$month]) ?> <?= $year ?></h2>
        <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $selectedDate ? '&date=' . urlencode($selectedDate) : '' ?>" class="btn btn-secondary" aria-label="Следующий месяц">→</a>
    </div>

    <div class="calendar-legend">
        <span><span class="calendar-legend__dot calendar-legend__dot--booking" aria-hidden="true"></span> Есть записи</span>
        <span><span class="calendar-legend__dot calendar-legend__dot--off" aria-hidden="true"></span> Выходной</span>
        <span><span class="calendar-legend__dot calendar-legend__dot--selected" aria-hidden="true"></span> Выбрано</span>
    </div>

    <div class="calendar-grid card studio-block" style="margin-bottom: 24px;">
        <div class="calendar-weekdays">
            <span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span>
        </div>
        <div class="calendar-days">
            <?php for ($i = 0; $i < $firstWeekday; $i++): ?>
                <div class="calendar-cell calendar-cell--empty"></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                <?php
                $dStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isPast = strtotime($dStr) < strtotime(date('Y-m-d'));
                $hasBookings = in_array($dStr, $datesWithBookings, true);
                $isOffDay = !empty($offDatesInMonth) && in_array($dStr, $offDatesInMonth, true);
                $isSelected = $selectedDate === $dStr;
                $url = '?year=' . $year . '&month=' . $month . '&date=' . $dStr;
                ?>
                <a href="<?= htmlspecialchars($url) ?>" class="calendar-cell <?= $isPast ? 'calendar-cell--past' : '' ?> <?= $hasBookings ? 'calendar-cell--has-bookings' : '' ?> <?= $isOffDay ? 'calendar-cell--off' : '' ?> <?= $isSelected ? 'calendar-cell--selected' : '' ?>"><?= $d ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <?php if ($selectedDate): ?>
        <div class="card studio-block calendar-day-panel">
            <h2>Записи на <?= date('d.m.Y', strtotime($selectedDate)) ?></h2>
            <?php if (empty($bookingsOfDay)): ?>
                <p style="color: var(--text-muted); margin: 0;">На эту дату записей нет.</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach ($bookingsOfDay as $b): ?>
                        <li class="calendar-booking-row<?= $b['status'] === 'pending' ? ' calendar-booking-row--pending' : '' ?>">
                            <?php if ($b['status'] === 'pending'): ?>
                                <span class="calendar-booking-row__label">Ожидает — <?= htmlspecialchars($b['client_name']) ?></span>
                                <div class="booking-time-row" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <form method="post" action="<?= htmlspecialchars($root . 'master_calendar.php?' . $redirectParams . '&date=' . urlencode($selectedDate)) ?>" class="booking-confirm-form" style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <input type="hidden" name="action" value="confirm">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                                        <input type="time" name="slot_start" min="00:00" max="23:59" required>
                                        <span>—</span>
                                        <input type="time" name="slot_end" min="00:00" max="23:59" required>
                                        <button type="submit" class="btn btn-primary btn-small">Подтвердить</button>
                                    </form>
                                    <form method="post" action="<?= htmlspecialchars($root . 'master_calendar.php?' . $redirectParams) ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                                        <button type="submit" class="btn btn-secondary btn-small">Отклонить</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span><strong><?= htmlspecialchars(substr($b['slot_time'] ?? '', 0, 5)) ?>–<?= htmlspecialchars(substr($b['slot_end'] ?? '', 0, 5)) ?></strong> — <?= htmlspecialchars($b['client_name']) ?></span>
                                <form method="post" action="<?= htmlspecialchars($root . 'master_calendar.php?' . $redirectParams) ?>" style="display:inline;" onsubmit="return confirm('Отменить запись?');">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                    <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                                    <button type="submit" class="btn btn-ghost btn-small">Отменить</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
