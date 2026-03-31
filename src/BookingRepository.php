<?php

declare(strict_types=1);

namespace App;

use PDO;

class BookingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::get();
    }

    /** Получить расписание мастера (рабочие часы, длительность слота, выходные по дням недели) */
    public function getSchedule(int $masterId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM master_schedule WHERE master_id = ?");
        $stmt->execute([$masterId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $raw = $row['off_weekdays'] ?? '[]';
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $row['off_weekdays'] = is_array($decoded) ? array_values(array_unique(array_map('intval', $decoded))) : [];
        } elseif (!is_array($row['off_weekdays'] ?? null)) {
            $row['off_weekdays'] = [];
        }
        return $row;
    }

    /** Вычислить длительность рабочего дня в часах (00:00–23:59). work_end=00:00 трактуется как конец дня (24:00). */
    public function getWorkDurationHours(string $workStart, string $workEnd): int
    {
        $start = strtotime("2000-01-01 " . $this->normalizeTime($workStart));
        $end = strtotime("2000-01-01 " . $this->normalizeTime($workEnd));
        if ($end <= $start) {
            $end += 86400; // work_end=00:00 или ночная смена: +24ч
        }
        return max(1, (int)round(($end - $start) / 3600));
    }

    private function normalizeTime(string $t): string
    {
        $t = trim($t);
        if (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
            return $t . ':00';
        }
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $t)) {
            return $t;
        }
        return '10:00:00';
    }

    /**
     * Сохранить/обновить расписание мастера. slot_duration хранится в часах (1, 2, 3...).
     * @param string $offWeekdaysJson JSON-массив номеров дней ISO 1–7 (пн–вс), в которые мастер не принимает онлайн-запись
     */
    public function saveSchedule(int $masterId, string $workStart, string $workEnd, int $slotDuration, string $offWeekdaysJson = '[]'): void
    {
        $workStart = $this->normalizeTime($workStart);
        $workEnd = $this->normalizeTime($workEnd);
        $maxHours = $this->getWorkDurationHours($workStart, $workEnd);
        $slotDuration = max(1, min($maxHours, $slotDuration));
        $offWeekdaysJson = $this->normalizeOffWeekdaysJson($offWeekdaysJson);
        $row = $this->getSchedule($masterId);
        if ($row) {
            try {
                $stmt = $this->pdo->prepare("UPDATE master_schedule SET work_start=?, work_end=?, slot_duration=?, off_weekdays=? WHERE master_id=?");
                $stmt->execute([$workStart, $workEnd, $slotDuration, $offWeekdaysJson, $masterId]);
            } catch (\Throwable $e) {
                $stmt = $this->pdo->prepare("UPDATE master_schedule SET work_start=?, work_end=?, slot_duration=? WHERE master_id=?");
                $stmt->execute([$workStart, $workEnd, $slotDuration, $masterId]);
            }
        } else {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO master_schedule (master_id, work_start, work_end, slot_duration, off_weekdays) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$masterId, $workStart, $workEnd, $slotDuration, $offWeekdaysJson]);
            } catch (\Throwable $e) {
                $stmt = $this->pdo->prepare("INSERT INTO master_schedule (master_id, work_start, work_end, slot_duration) VALUES (?, ?, ?, ?)");
                $stmt->execute([$masterId, $workStart, $workEnd, $slotDuration]);
            }
        }
    }

    private function normalizeOffWeekdaysJson(string $json): string
    {
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return '[]';
        }
        $out = [];
        foreach ($arr as $n) {
            $n = (int) $n;
            if ($n >= 1 && $n <= 7) {
                $out[$n] = $n;
            }
        }
        ksort($out);
        return json_encode(array_values($out));
    }

    /** Даты выходных с указанной даты (YYYY-MM-DD) и дальше */
    public function listDayOffsFrom(int $masterId, string $fromYmd): array
    {
        $stmt = $this->pdo->prepare("SELECT off_date FROM master_day_off WHERE master_id = ? AND off_date >= ? ORDER BY off_date");
        $stmt->execute([$masterId, $fromYmd]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'off_date');
    }

    public function addDayOff(int $masterId, string $dateYmd): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return false;
        }
        try {
            if (Database::isSqlite()) {
                $this->pdo->prepare("INSERT OR IGNORE INTO master_day_off (master_id, off_date) VALUES (?, ?)")->execute([$masterId, $dateYmd]);
            } else {
                $this->pdo->prepare("INSERT IGNORE INTO master_day_off (master_id, off_date) VALUES (?, ?)")->execute([$masterId, $dateYmd]);
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function removeDayOff(int $masterId, string $dateYmd): void
    {
        $this->pdo->prepare("DELETE FROM master_day_off WHERE master_id = ? AND off_date = ?")->execute([$masterId, $dateYmd]);
    }

    /** Можно ли клиенту запросить запись на эту дату (расписание есть, не выходной по неделе и не в списке дат) */
    public function isBookableDate(int $masterId, string $dateYmd): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return false;
        }
        $sched = $this->getSchedule($masterId);
        if (!$sched) {
            return false;
        }
        $ts = strtotime($dateYmd . ' 12:00:00');
        if ($ts === false) {
            return false;
        }
        $today = strtotime(date('Y-m-d') . ' 12:00:00');
        if ($ts < $today) {
            return false;
        }
        $weekday = (int) date('N', $ts);
        $offW = $sched['off_weekdays'] ?? [];
        if (in_array($weekday, $offW, true)) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM master_day_off WHERE master_id = ? AND off_date = ?");
            $stmt->execute([$masterId, $dateYmd]);
            return $stmt->fetchColumn() === false;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** Клиент: создать запрос на запись (ожидает подтверждения мастера). null — дата недоступна */
    public function createBookingRequest(int $masterId, int $clientId, string $date): ?int
    {
        if (!$this->isBookableDate($masterId, $date)) {
            return null;
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO bookings (master_id, client_id, booking_date, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$masterId, $clientId, $date]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Мастер: подтвердить запись, указав время (с–до). Проверка на пересечение с другими записями. */
    public function confirmBooking(int $bookingId, int $masterId, string $slotStart, string $slotEnd): bool
    {
        $slotStart = $this->normalizeTime($slotStart);
        $slotEnd = $this->normalizeTime($slotEnd);
        if ($slotEnd <= $slotStart) {
            return false;
        }
        $booking = $this->getBookingById($bookingId, $masterId);
        if (!$booking || ($booking['status'] ?? '') !== 'pending') {
            return false;
        }
        $date = $booking['booking_date'];
        if ($this->hasOverlap($masterId, $date, $slotStart, $slotEnd, $bookingId)) {
            return false;
        }
        $stmt = $this->pdo->prepare("
            UPDATE bookings SET status = 'confirmed', slot_time = ?, slot_end = ?
            WHERE id = ? AND master_id = ? AND status = 'pending'
        ");
        $stmt->execute([$slotStart, $slotEnd, $bookingId, $masterId]);
        return $stmt->rowCount() > 0;
    }

    /** Проверить, пересекается ли интервал (slotStart, slotEnd) с подтверждёнными записями на дату */
    private function hasOverlap(int $masterId, string $date, string $slotStart, string $slotEnd, int $excludeId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT slot_time, slot_end FROM bookings
            WHERE master_id = ? AND booking_date = ? AND status = 'confirmed' AND id != ?
        ");
        $stmt->execute([$masterId, $date, $excludeId]);
        $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $newStart = strtotime("2000-01-01 " . $slotStart);
        $newEnd = strtotime("2000-01-01 " . $slotEnd);
        foreach ($existing as $row) {
            $exStart = strtotime("2000-01-01 " . $this->normalizeTime($row['slot_time'] ?? '00:00:00'));
            $exEndRaw = $row['slot_end'] ?? $row['slot_time'] ?? '23:59:59';
            $exEnd = strtotime("2000-01-01 " . $this->normalizeTime($exEndRaw));
            if ($newStart < $exEnd && $exStart < $newEnd) {
                return true;
            }
        }
        return false;
    }

    /** Мастер: отклонить запрос */
    public function rejectBooking(int $bookingId, int $masterId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ? AND master_id = ? AND status = 'pending'");
        $stmt->execute([$bookingId, $masterId]);
        return $stmt->rowCount() > 0;
    }

    /** Получить ожидающие запросы мастера */
    public function getPendingRequests(int $masterId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.full_name as client_name
            FROM bookings b
            JOIN users u ON u.id = b.client_id
            WHERE b.master_id = ? AND b.status = 'pending'
            ORDER BY b.booking_date ASC, b.created_at ASC
        ");
        $stmt->execute([$masterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Получить записи мастера на дату (подтверждённые и ожидающие) */
    public function getBookingsByDate(int $masterId, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.full_name as client_name
            FROM bookings b
            JOIN users u ON u.id = b.client_id
            WHERE b.master_id = ? AND b.booking_date = ? AND b.status IN ('confirmed', 'pending')
            ORDER BY b.slot_time IS NULL, b.slot_time ASC, b.created_at ASC
        ");
        $stmt->execute([$masterId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Получить даты, на которые есть записи или запросы в интервале */
    public function getDatesWithBookings(int $masterId, string $monthStart, string $monthEnd): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT booking_date FROM bookings
            WHERE master_id = ? AND booking_date >= ? AND booking_date <= ? AND status IN ('confirmed', 'pending')
            ORDER BY booking_date
        ");
        $stmt->execute([$masterId, $monthStart, $monthEnd]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'booking_date');
    }

    /** Отменить/отклонить бронь (для мастера) */
    public function cancelBooking(int $bookingId, int $masterId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND master_id = ?");
        $stmt->execute([$bookingId, $masterId]);
        return $stmt->rowCount() > 0;
    }

    /** Подтверждённые записи мастера на период (для напоминаний) */
    public function getUpcomingConfirmed(int $masterId, string $dateFrom, string $dateTo): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.full_name AS client_name
            FROM bookings b
            JOIN users u ON u.id = b.client_id
            WHERE b.master_id = ? AND b.status = 'confirmed'
              AND b.booking_date >= ? AND b.booking_date <= ?
            ORDER BY b.booking_date ASC, b.slot_time ASC
        ");
        $stmt->execute([$masterId, $dateFrom, $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Получить запись по ID для мастера */
    public function getBookingById(int $bookingId, int $masterId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT b.*, u.full_name as client_name FROM bookings b JOIN users u ON u.id = b.client_id WHERE b.id = ? AND b.master_id = ?");
        $stmt->execute([$bookingId, $masterId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Получить запись клиента, по которой можно попросить отзыв:
     * подтверждённая, сеанс в прошлом, отзыв ещё не оставлен, не отклонена.
     */
    public function getPendingReviewRequest(int $clientId): ?array
    {
        $dateCond = Database::isSqlite()
            ? "b.booking_date < date('now')"
            : "(b.booking_date < CURDATE() OR (b.booking_date = CURDATE() AND COALESCE(b.slot_end, b.slot_time) < CURTIME()))";
        $sql = "SELECT b.*, u.full_name as master_name FROM bookings b
                JOIN users u ON u.id = b.master_id
                LEFT JOIN ratings r ON r.master_id = b.master_id AND r.client_id = b.client_id
                WHERE b.client_id = ? AND b.status = 'confirmed'
                  AND (b.review_dismissed_at IS NULL)
                  AND r.id IS NULL
                  AND " . $dateCond . "
                ORDER BY b.booking_date DESC, b.slot_time DESC LIMIT 1";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$clientId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Клиент отклонил просьбу об отзыве */
    public function dismissReviewRequest(int $bookingId, int $clientId): bool
    {
        $sql = Database::isSqlite()
            ? "UPDATE bookings SET review_dismissed_at = datetime('now') WHERE id = ? AND client_id = ?"
            : "UPDATE bookings SET review_dismissed_at = NOW() WHERE id = ? AND client_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$bookingId, $clientId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
