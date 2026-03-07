-- Миграция v13: напоминание об отзыве после сеанса
ALTER TABLE bookings ADD COLUMN review_dismissed_at DATETIME NULL;
