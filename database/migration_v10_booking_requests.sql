-- Миграция v10: запросы на запись (клиент → запрос, мастер подтверждает с указанием времени)
-- Выполнять по одному: ADD COLUMN, MODIFY slot_time, MODIFY status. DROP INDEX — только если есть.

ALTER TABLE bookings ADD COLUMN slot_end TIME NULL AFTER slot_time;
ALTER TABLE bookings MODIFY slot_time TIME NULL;
ALTER TABLE bookings MODIFY status ENUM('pending', 'confirmed', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending';
