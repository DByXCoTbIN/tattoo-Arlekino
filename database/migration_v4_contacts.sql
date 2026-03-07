-- Контакты мастера (телефон, соцсети)
-- При повторном запуске будет ошибка "Duplicate column" — это нормально
ALTER TABLE master_profiles ADD COLUMN phone VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE master_profiles ADD COLUMN instagram VARCHAR(255) NULL DEFAULT NULL;
