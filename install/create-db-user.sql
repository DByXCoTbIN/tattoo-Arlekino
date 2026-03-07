-- Выполнить: sudo mysql < install/create-db-user.sql
-- Пароль должен совпадать с DB_PASS в .env (admin123)

CREATE DATABASE IF NOT EXISTS circus_social CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS 'circus'@'localhost';
DROP USER IF EXISTS 'circus'@'127.0.0.1';
CREATE USER 'circus'@'localhost' IDENTIFIED BY 'admin123';
CREATE USER 'circus'@'127.0.0.1' IDENTIFIED BY 'admin123';
GRANT ALL PRIVILEGES ON circus_social.* TO 'circus'@'localhost';
GRANT ALL PRIVILEGES ON circus_social.* TO 'circus'@'127.0.0.1';
FLUSH PRIVILEGES;
