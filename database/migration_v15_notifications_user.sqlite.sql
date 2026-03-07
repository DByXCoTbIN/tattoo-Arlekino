-- Миграция v15: user_id в уведомлениях (NULL = для всех админов)
ALTER TABLE notifications ADD COLUMN user_id INTEGER NULL;
