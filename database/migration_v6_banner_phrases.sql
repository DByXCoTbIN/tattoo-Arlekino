-- Миграция v6: баннер, приветственные фразы, логотип студии

-- Приветственные фразы (дата_from/to = NULL = постоянное отображение)
CREATE TABLE IF NOT EXISTS welcome_phrases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phrase TEXT NOT NULL,
    date_from DATE NULL DEFAULT NULL,
    date_to DATE NULL DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dates (date_from, date_to),
    INDEX idx_order (sort_order)
) ENGINE=InnoDB;

-- Дефолтная фраза
INSERT INTO welcome_phrases (phrase, date_from, date_to, sort_order) VALUES ('Добро пожаловать в студию', NULL, NULL, 0);
