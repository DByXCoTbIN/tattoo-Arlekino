-- Миграция v11: причина блокировки (отображается на экране блокировки)
ALTER TABLE users ADD COLUMN ban_reason TEXT NULL;
