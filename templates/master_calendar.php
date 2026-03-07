<?php
$root = (defined('BASE_PATH') && BASE_PATH !== '') ? rtrim(BASE_PATH, '/') . '/' : '/';
$daysInMonth = (int)date('t', strtotime($monthStart));
$firstWeekday = (int)date('w', strtotime($monthStart)); // 0=Sunday
$firstWeekday = $firstWeekday === 0 ? 6 : $firstWeekday - 1; // Mon=0
?>
<div class="calendar-page">
    <p style="margin-bottom: 24px;"><a href="<?= htmlspecialchars($root . 'master.php?id=' . (int)$master['id']) ?>">← Моя страница</a></p>
    <h1 class="card-title">Мой календарь</h1>
    <?php if ($error ?? ''): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success ?? ''): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (!empty($pendingRequests)): ?>
    <div class="card settings-card" style="margin-bottom: 24px; background: rgba(212,175,55,0.08); border-color: var(--accent-gold);">
        <h2 style="font-size: 1.1rem; margin: 0 0 12px;">Ожидают подтверждения</h2>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <?php foreach ($pendingRequests as $pr): ?>
            <li style="padding: 12px 0; border-bottom: 1px solid var(--border);">
                <div style="margin-bottom: 8px;"><?= htmlspecialchars($pr['client_name']) ?> — <?= date('d.m.Y', strtotime($pr['booking_date'])) ?></div>
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
                        <button type="submit" class="btn btn-small">Отклонить</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="calendar-nav" style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
        <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $selectedDate ? '&date=' . urlencode($selectedDate) : '' ?>" class="btn btn-secondary">←</a>
        <h2 style="margin: 0; font-size: 1.25rem;"><?= htmlspecialchars($monthNames[$month]) ?> <?= $year ?></h2>
        <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $selectedDate ? '&date=' . urlencode($selectedDate) : '' ?>" class="btn btn-secondary">→</a>
    </div>

    <div class="calendar-grid card settings-card" style="margin-bottom: 24px;">
        <div class="calendar-weekdays" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 8px;">
            <span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span>
        </div>
        <div class="calendar-days" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px;">
            <?php for ($i = 0; $i < $firstWeekday; $i++): ?>
                <div class="calendar-cell calendar-cell--empty"></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                <?php
                $dStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isPast = strtotime($dStr) < strtotime(date('Y-m-d'));
                $hasBookings = in_array($dStr, $datesWithBookings, true);
                $isSelected = $selectedDate === $dStr;
                $url = '?year=' . $year . '&month=' . $month . '&date=' . $dStr;
                ?>
                <a href="<?= htmlspecialchars($url) ?>" class="calendar-cell <?= $isPast ? 'calendar-cell--past' : '' ?> <?= $hasBookings ? 'calendar-cell--has-bookings' : '' ?> <?= $isSelected ? 'calendar-cell--selected' : '' ?>">
                    <?= $d ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <?php if ($selectedDate): ?>
        <div class="card settings-card">
            <h2 style="font-size: 1.1rem; margin: 0 0 16px;">Записи на <?= date('d.m.Y', strtotime($selectedDate)) ?></h2>
            <?php if (empty($bookingsOfDay)): ?>
                <p style="color: var(--text-muted);">На эту дату записей нет.</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach ($bookingsOfDay as $b): ?>
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                            <?php if ($b['status'] === 'pending'): ?>
                                <span style="color: var(--accent-gold);">⏳ Ожидает — <?= htmlspecialchars($b['client_name']) ?></span>
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
                                        <button type="submit" class="btn btn-small">Отклонить</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span><strong><?= htmlspecialchars(substr($b['slot_time'] ?? '', 0, 5)) ?>–<?= htmlspecialchars(substr($b['slot_end'] ?? '', 0, 5)) ?></strong> — <?= htmlspecialchars($b['client_name']) ?></span>
                                <form method="post" action="<?= htmlspecialchars($root . 'master_calendar.php?' . $redirectParams) ?>" style="display:inline;" onsubmit="return confirm('Отменить запись?');">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                    <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                                    <button type="submit" class="btn btn-small">Отменить</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.calendar-cell { display: flex; align-items: center; justify-content: center; min-height: 40px; border-radius: 8px; text-decoration: none; color: var(--text); background: var(--bg-dark); }
.calendar-cell:hover { background: rgba(212,175,55,0.15); }
.calendar-cell--empty { background: transparent; pointer-events: none; }
.calendar-cell--past { opacity: 0.5; }
.calendar-cell--has-bookings { border: 1px solid var(--accent, #d4af37); }
.calendar-cell--selected { background: rgba(212,175,55,0.25); font-weight: 600; }
</style>
