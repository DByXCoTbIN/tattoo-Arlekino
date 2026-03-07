-- Миграция v7 SQLite: возможность отключить видимость фразы

ALTER TABLE welcome_phrases ADD COLUMN is_visible INTEGER NOT NULL DEFAULT 1;
