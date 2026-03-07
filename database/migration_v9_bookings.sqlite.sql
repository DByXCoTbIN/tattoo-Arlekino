-- Миграция v9: записи к мастерам (SQLite)

CREATE TABLE IF NOT EXISTS master_schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    work_start TEXT NOT NULL DEFAULT '10:00:00',
    work_end TEXT NOT NULL DEFAULT '18:00:00',
    slot_duration INTEGER NOT NULL DEFAULT 60,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_ms_master ON master_schedule(master_id);

CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    booking_date TEXT NOT NULL,
    slot_time TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'confirmed' CHECK(status IN ('confirmed','cancelled')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(master_id, booking_date, slot_time)
);
CREATE INDEX IF NOT EXISTS idx_bookings_master_date ON bookings(master_id, booking_date);
CREATE INDEX IF NOT EXISTS idx_bookings_client ON bookings(client_id);
