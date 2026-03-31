-- v18: выходные дни записи + видимость профиля мастера + админ-override
ALTER TABLE master_profiles ADD COLUMN profile_hidden_by_master INTEGER NOT NULL DEFAULT 0;
ALTER TABLE master_profiles ADD COLUMN admin_profile_visibility TEXT;

ALTER TABLE master_schedule ADD COLUMN off_weekdays TEXT NOT NULL DEFAULT '[]';

CREATE TABLE IF NOT EXISTS master_day_off (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    off_date TEXT NOT NULL,
    UNIQUE(master_id, off_date)
);
CREATE INDEX IF NOT EXISTS idx_master_day_off_master ON master_day_off(master_id);
