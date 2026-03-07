-- Миграция v14: уникальность телефона (SQLite)
-- Уникальный индекс только для непустых телефонов (NULL разрешён многократно)
DROP INDEX IF EXISTS idx_users_phone;
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_phone ON users(phone) WHERE phone IS NOT NULL;
