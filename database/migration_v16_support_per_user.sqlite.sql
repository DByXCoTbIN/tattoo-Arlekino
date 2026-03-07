-- Миграция v16 SQLite: отдельный чат поддержки на каждого пользователя
CREATE TABLE IF NOT EXISTS support_user_chats (
    user_id INTEGER NOT NULL PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    group_id INTEGER NOT NULL REFERENCES group_chats(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_support_user_chats_group ON support_user_chats(group_id);
