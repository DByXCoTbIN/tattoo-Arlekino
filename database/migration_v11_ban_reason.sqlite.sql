-- Миграция v11: причина блокировки (SQLite)
ALTER TABLE users ADD COLUMN ban_reason TEXT;
