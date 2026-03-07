-- Миграция v8: адреса и метки на карте

CREATE TABLE IF NOT EXISTS map_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT '',
    address TEXT NOT NULL,
    map_url VARCHAR(1000) NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (sort_order)
) ENGINE=InnoDB;
