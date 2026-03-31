-- v18: выходные дни записи + видимость профиля мастера + админ-override (MySQL)
ALTER TABLE master_profiles ADD COLUMN profile_hidden_by_master TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE master_profiles ADD COLUMN admin_profile_visibility VARCHAR(20) NULL DEFAULT NULL;

ALTER TABLE master_schedule ADD COLUMN off_weekdays TEXT NOT NULL DEFAULT '[]';

CREATE TABLE IF NOT EXISTS master_day_off (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    master_id INT UNSIGNED NOT NULL,
    off_date DATE NOT NULL,
    UNIQUE KEY uk_master_day_off (master_id, off_date),
    CONSTRAINT fk_master_day_off_user FOREIGN KEY (master_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
