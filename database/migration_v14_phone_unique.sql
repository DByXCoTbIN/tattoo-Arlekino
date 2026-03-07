-- Миграция v14: уникальность телефона (один номер — один аккаунт)
DROP INDEX idx_users_phone ON users;
CREATE UNIQUE INDEX idx_users_phone ON users(phone);
