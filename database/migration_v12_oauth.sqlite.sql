-- Миграция v12: OAuth (Telegram, VK) + телефон для клиентов (SQLite)
ALTER TABLE users ADD COLUMN phone TEXT;
ALTER TABLE users ADD COLUMN oauth_provider TEXT;
ALTER TABLE users ADD COLUMN oauth_id TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_oauth ON users(oauth_provider, oauth_id);
CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
