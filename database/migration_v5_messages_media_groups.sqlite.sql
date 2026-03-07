-- Миграция v5 SQLite: медиа в сообщениях, групповые чаты, индикатор набора

-- Медиа в личных сообщениях
ALTER TABLE messages ADD COLUMN media_path TEXT NULL;
ALTER TABLE messages ADD COLUMN media_type TEXT NULL;

-- Групповые чаты
CREATE TABLE IF NOT EXISTS group_chats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    creator_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_gc_creator ON group_chats(creator_id);

CREATE TABLE IF NOT EXISTS group_chat_members (
    group_id INTEGER NOT NULL REFERENCES group_chats(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (group_id, user_id)
);

CREATE TABLE IF NOT EXISTS group_chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL REFERENCES group_chats(id) ON DELETE CASCADE,
    sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT,
    media_path TEXT NULL,
    media_type TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_gcm_group ON group_chat_messages(group_id);
CREATE INDEX IF NOT EXISTS idx_gcm_created ON group_chat_messages(created_at);

-- Индикатор набора (conversation_id=0 для группового, group_id=0 для личного)
CREATE TABLE IF NOT EXISTS typing_status (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    conversation_id INTEGER NOT NULL DEFAULT 0,
    group_id INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (user_id, conversation_id, group_id)
);
