-- Баннер мастера (фон верхней части профиля)
-- При повторном запуске будет ошибка "Duplicate column" — это нормально, колонка уже есть.
ALTER TABLE master_profiles
    ADD COLUMN banner_path VARCHAR(500) NULL DEFAULT NULL AFTER is_verified;
