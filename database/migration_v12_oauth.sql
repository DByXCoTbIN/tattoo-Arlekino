-- Миграция v12: OAuth (Telegram, VK) + телефон для клиентов
ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL AFTER avatar_path;
ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(30) NULL AFTER phone;
ALTER TABLE users ADD COLUMN oauth_id VARCHAR(100) NULL AFTER oauth_provider;
CREATE UNIQUE INDEX idx_users_oauth ON users(oauth_provider, oauth_id);
CREATE INDEX idx_users_phone ON users(phone);
