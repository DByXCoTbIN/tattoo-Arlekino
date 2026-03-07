-- Миграция v2: заявки мастеров, отзывы с подтверждением, услуги, жалобы, last_seen, тексты главной

ALTER TABLE users
    ADD COLUMN last_seen_at DATETIME NULL AFTER updated_at,
    ADD COLUMN role_requested_at DATETIME NULL COMMENT 'Заявка на роль мастера' AFTER last_seen_at;

ALTER TABLE ratings
    ADD COLUMN status ENUM('pending','approved','rejected','hidden') NOT NULL DEFAULT 'approved' AFTER comment,
    ADD COLUMN approved_at DATETIME NULL AFTER status,
    ADD COLUMN rejected_reason VARCHAR(500) NULL AFTER approved_at,
    ADD COLUMN admin_restored TINYINT(1) NOT NULL DEFAULT 0 AFTER rejected_reason;

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS master_services (
    master_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (master_id, service_id),
    FOREIGN KEY (master_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL COMMENT 'master_request, complaint',
    ref_id INT UNSIGNED NULL COMMENT 'user_id or complaint id',
    from_user_id INT UNSIGNED NULL,
    data TEXT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_read (is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS complaints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT UNSIGNED NOT NULL,
    about_type ENUM('user','rating') NOT NULL,
    about_user_id INT UNSIGNED NULL,
    about_rating_id INT UNSIGNED NULL,
    reason TEXT,
    status ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
    admin_decision TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO settings (`key`, `value`) VALUES
('hero_title', 'Добро пожаловать на арену'),
('hero_tagline', 'Смотрите работы мастеров, ставьте оценки и общайтесь в личных сообщениях.'),
('section_masters_title', 'Наши мастера'),
('section_feed_title', 'Лента'),
('section_services_title', 'Услуги студии')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
