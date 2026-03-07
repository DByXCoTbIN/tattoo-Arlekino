-- Миграция v16: отдельный чат поддержки на каждого пользователя (user_id -> group_id)
CREATE TABLE IF NOT EXISTS support_user_chats (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_support_group (group_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES group_chats(id) ON DELETE CASCADE
) ENGINE=InnoDB;
