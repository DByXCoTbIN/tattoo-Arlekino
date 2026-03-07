-- Миграция v6 SQLite: баннер, приветственные фразы, логотип

CREATE TABLE IF NOT EXISTS welcome_phrases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phrase TEXT NOT NULL,
    date_from TEXT NULL,
    date_to TEXT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_wp_dates ON welcome_phrases(date_from, date_to);

INSERT OR IGNORE INTO welcome_phrases (phrase, date_from, date_to, sort_order) VALUES ('Добро пожаловать в студию', NULL, NULL, 0);
