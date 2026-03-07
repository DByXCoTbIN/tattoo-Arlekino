-- Миграция v13: напоминание об отзыве после сеанса (SQLite)
ALTER TABLE bookings ADD COLUMN review_dismissed_at TEXT;
