-- Миграция v9: записи к мастерам
-- master_schedule: рабочие часы мастера
-- bookings: брони клиентов

CREATE TABLE IF NOT EXISTS master_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    master_id INT UNSIGNED NOT NULL UNIQUE,
    work_start TIME NOT NULL DEFAULT '10:00:00',
    work_end TIME NOT NULL DEFAULT '18:00:00',
    slot_duration INT UNSIGNED NOT NULL DEFAULT 60,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (master_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_master (master_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    master_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    slot_time TIME NOT NULL,
    status ENUM('confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (master_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (master_id, booking_date, slot_time),
    INDEX idx_master_date (master_id, booking_date),
    INDEX idx_client (client_id)
) ENGINE=InnoDB;
