-- Миграция v15: user_id в уведомлениях (NULL = для всех админов, иначе для конкретного пользователя)
ALTER TABLE notifications ADD COLUMN user_id INT UNSIGNED NULL;
CREATE INDEX idx_notifications_user_read ON notifications (user_id, is_read);
