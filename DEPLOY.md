# Развёртывание АрлекинО

## Требования

- PHP 8.1+ с расширениями: pdo_mysql, mbstring, json, session
- MariaDB 10.3+ или MySQL 5.7+

## Быстрый старт (разработка)

```bash
# 1. Скопировать конфиг
cp .env.example .env

# 2. Создать пользователя и БД в MySQL (требуется sudo)
sudo mysql < install/create-db-user.sql

# 3. Установка таблиц и админа
php install/install.php

# 4. Миграция v2 (услуги, отзывы, заявки мастеров)
php install/migrate_v2.php

# 5. (Опционально) Баннер: mysql -u circus -p circus_social < database/migration_v3_banner.sql
# 6. (Опционально) Контакты: mysql -u circus -p circus_social < database/migration_v4_contacts.sql

# 7. Запуск сервера
php -S 0.0.0.0:8000 -t public

```

Откройте http://localhost:8000  
**Админ:** admin@circus.local / admin123

## Продакшн (Apache/Nginx)

1. Укажите корень документов на каталог `public/`
2. В `.env` задайте `SITE_URL=https://ваш-домен.ru`
3. Убедитесь, что `public/uploads/` доступен для записи
4. Для Apache добавьте в `.htaccess` (уже есть) или в виртуальный хост:

```apache
<Directory "/path/to/1_tattoo/public">
    AllowOverride All
    Require all granted
</Directory>
```

## Структура .env

| Переменная   | Описание           | Пример                |
|--------------|--------------------|------------------------|
| DB_DRIVER    | Драйвер БД         | mysql                  |
| DB_HOST      | Хост БД            | 127.0.0.1              |
| DB_NAME      | Имя БД             | circus_social           |
| DB_USER      | Пользователь MySQL | circus                  |
| DB_PASS      | Пароль             | admin123               |
| SITE_URL     | Полный URL сайта   | http://localhost:8000  |
