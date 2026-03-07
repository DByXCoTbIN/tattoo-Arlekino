-- Миграция v10: запросы на запись (SQLite)

-- SQLite не поддерживает MODIFY, нужен пересоздание таблицы
CREATE TABLE IF NOT EXISTS bookings_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    booking_date TEXT NOT NULL,
    slot_time TEXT,
    slot_end TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','confirmed','rejected','cancelled')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

INSERT INTO bookings_new (id, master_id, client_id, booking_date, slot_time, slot_end, status, created_at)
SELECT id, master_id, client_id, booking_date, slot_time, slot_time, status, created_at FROM bookings;

DROP TABLE bookings;
ALTER TABLE bookings_new RENAME TO bookings;
CREATE INDEX IF NOT EXISTS idx_bookings_master_date ON bookings(master_id, booking_date);
CREATE INDEX IF NOT EXISTS idx_bookings_client ON bookings(client_id);
CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(master_id, status);
