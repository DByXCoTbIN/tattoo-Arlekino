#!/bin/bash
# Запуск: ./install/setup-mysql.sh
# Для установки пакетов потребуется пароль sudo.

set -e
cd "$(dirname "$0")/.."

echo "=== 0. Запуск СУБД (в Ubuntu обычно MariaDB) ==="
if sudo systemctl start mariadb 2>/dev/null; then
    echo "MariaDB запущен."
elif sudo systemctl start mysql 2>/dev/null; then
    echo "MySQL запущен."
else
    echo "Служба mysql/mariadb не найдена. Установите: sudo apt-get install -y mariadb-server"
    echo "Или запустите вручную: sudo systemctl start mariadb   (или mysql)"
    read -p "Продолжить без запуска? [y/N] " -n 1 -r; echo
    [[ $REPLY =~ ^[yY] ]] || exit 1
fi
echo "Проверьте .env: DB_USER и DB_PASS (логин/пароль MySQL)."
echo ""

echo "=== 1. Установка PHP MySQL (потребуется пароль sudo) ==="
echo "Если apt update выдаст 403 (репозиторий Cursor) — временно отключите его в /etc/apt/sources.list.d/ или выполните шаги вручную."
sudo apt-get update 2>/dev/null || true
sudo apt-get install -y php-mysql 2>/dev/null || sudo apt-get install -y php8.3-mysql 2>/dev/null || true

echo ""
echo "=== 2. Проверка драйвера ==="
php -m | grep -i pdo_mysql || { echo "Ошибка: pdo_mysql не найден. Установите пакет php-mysql или php8.3-mysql"; exit 1; }

echo ""
echo "=== 3. Создание БД и таблиц, первый админ ==="
php install/install.php

echo ""
echo "=== 3.1. Миграция v2 (услуги, отзывы, заявки мастеров) ==="
php install/migrate_v2.php 2>/dev/null || true

echo ""
echo "=== 4. Запуск сервера (остановите через Ctrl+C) ==="
echo "Откройте в браузере: http://localhost:8000"
echo "Админ: admin@circus.local / admin123"
echo ""
php -S 0.0.0.0:8000 -t public
