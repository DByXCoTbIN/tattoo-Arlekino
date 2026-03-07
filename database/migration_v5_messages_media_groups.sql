-- Миграция v5: медиа в сообщениях, групповые чаты, индикатор набора
SET FOREIGN_KEY_CHECKS = 0;

-- Медиа в личных сообщениях
ALTER TABLE messages ADD COLUMN media_path VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE messages ADD COLUMN media_type VARCHAR(20) NULL DEFAULT NULL;

-- Групповые чаты (мастер создаёт, участники присоединяются)
CREATE TABLE IF NOT EXISTS group_chats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    creator_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_creator (creator_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_chat_members (
    group_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    INDEX idx_gcm_group (group_id),
    INDEX idx_gcm_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    body TEXT,
    media_path VARCHAR(500) NULL,
    media_type VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gmsg_group (group_id),
    INDEX idx_gmsg_sender (sender_id),
    INDEX idx_gmsg_created (created_at)
) ENGINE=InnoDB;

-- Индикатор набора (conversation_id=0 для группового, group_id=0 для личного)
CREATE TABLE IF NOT EXISTS typing_status (
    user_id INT UNSIGNED NOT NULL,
    conversation_id INT UNSIGNED NOT NULL DEFAULT 0,
    group_id INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, conversation_id, group_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conv (conversation_id),
    INDEX idx_group (group_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
