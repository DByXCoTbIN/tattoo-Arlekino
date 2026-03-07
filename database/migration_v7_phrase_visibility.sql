-- Миграция v7: возможность отключить видимость фразы

ALTER TABLE welcome_phrases ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1;
