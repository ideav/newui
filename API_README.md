# PHP Backend API для регистрации пользователей

Этот документ описывает PHP backend API для поддержки регистрации пользователей, разработанный в соответствии с требованиями из [issue #6](https://github.com/ideav/newui/issues/6) и [issue #11](https://github.com/ideav/newui/issues/11).

## 📋 Содержание

- [Обзор](#обзор)
- [Структура файлов](#структура-файлов)
- [Установка и настройка](#установка-и-настройка)
- [API Endpoints](#api-endpoints)
- [Интеграция с фронтендом](#интеграция-с-фронтендом)
- [Тестирование](#тестирование)
- [Безопасность](#безопасность)
- [Устранение неполадок](#устранение-неполадок)

## 🎯 Обзор

PHP backend предоставляет полнофункциональную систему регистрации пользователей с:

- ✉️ Регистрацией по email с подтверждением
- 🔐 Хешированием паролей с солью
- 🔑 XSRF токенами для защиты
- 📧 Отправкой email через SMTP
- 🗄️ Интеграцией с CRM-подобной базой данных
- 🌍 Поддержкой i18n (русский/английский)

## 📁 Структура файлов

```
api/
├── db_helpers.php       # Функции для работы с базой данных
├── email_helpers.php    # Отправка email через SMTP
├── auth_helpers.php     # Утилиты для аутентификации
├── register.php         # Endpoint регистрации
├── confirm.php          # Endpoint подтверждения email
└── xsrf.php            # Endpoint получения XSRF токена

experiments/
└── test_registration_api.php  # Скрипт для тестирования API

config.php               # Конфигурация (создать из config.example.php)
config.example.php       # Шаблон конфигурации
```

### Описание файлов

#### `api/db_helpers.php`
Функции для работы с базой данных:
- `Exec_sql()` - выполнение SQL запросов с обработкой ошибок
- `Insert()` - вставка записей в таблицу
- `newUser()` - создание нового пользователя
- `userExists()` - проверка существования пользователя
- `getUserById()` - получение данных пользователя

#### `api/email_helpers.php`
Отправка email:
- `mysendmail()` - отправка email через SMTP
- `sendViaSMTP()` - низкоуровневая отправка через SMTP socket

#### `api/auth_helpers.php`
Утилиты аутентификации:
- `Salt()` - хеширование пароля с солью
- `mail2DB()` - генерация имени базы данных из email
- `t9n()` - перевод текста (i18n)
- `generateToken()` - генерация случайных токенов
- `getXsrfToken()` - получение/генерация XSRF токена
- `validateXsrfToken()` - проверка XSRF токена
- `updateTokens()` - обновление токенов пользователя

#### `api/register.php`
Endpoint регистрации новых пользователей.

#### `api/confirm.php`
Endpoint подтверждения email после регистрации.

#### `api/xsrf.php`
Endpoint для получения XSRF токена и проверки статуса пользователя.

## 🚀 Установка и настройка

### Шаг 1: Создание конфигурации

```bash
cp config.example.php config.php
```

### Шаг 2: Настройка config.php

Отредактируйте `config.php` и заполните необходимые данные:

```php
// База данных
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'your_database');

// SMTP для отправки email
$mail_config = [
    'smtp_username' => 'your_email@example.com',
    'smtp_port'     => '465',
    'smtp_host'     => 'ssl://smtp.example.com',
    'smtp_password' => 'your_password',
    'smtp_debug'    => false,
    'smtp_charset'  => 'utf-8',
    'smtp_from'     => 'Your App Name',
];

// Безопасность
define('SALT', 'your_unique_random_salt_here');
define('ADMINEMAIL', 'admin@example.com');
```

Для генерации безопасной соли:
```bash
openssl rand -base64 32
```

### Шаг 3: Создание структуры базы данных

Создайте таблицу для хранения данных (CRM-структура):

```sql
CREATE TABLE IF NOT EXISTS `your_table_name` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `up` INT(11) NOT NULL DEFAULT 0,
  `ord` INT(11) NOT NULL DEFAULT 0,
  `t` INT(11) NOT NULL,
  `val` TEXT,
  PRIMARY KEY (`id`),
  KEY `up` (`up`),
  KEY `t` (`t`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Важно:** Установите переменную `$z` с именем вашей таблицы в `config.php`:
```php
$z = 'your_table_name';
```

### Шаг 4: Настройка веб-сервера

Убедитесь, что ваш веб-сервер настроен для обработки PHP файлов и имеет доступ к директории `api/`.

**Для Apache:**
Файл `.htaccess` (если нужен):
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
</IfModule>

# Enable CORS if needed
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, X-Requested-With"
</IfModule>
```

**Для Nginx:**
```nginx
location /api/ {
    try_files $uri $uri/ =404;

    # CORS headers
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
    add_header Access-Control-Allow-Headers "Content-Type, X-Requested-With";
}
```

## 🔌 API Endpoints

### 1. POST `/api/register.php` - Регистрация пользователя

Регистрирует нового пользователя и отправляет email с подтверждением.

**Параметры (application/x-www-form-urlencoded):**

Вариант 1 (форма):
```
email: user@example.com
regpwd: password123
regpwd1: password123
agree: 1
_xsrf: <токен>
```

Вариант 2 (API):
```
t18: user@example.com
t20: password123
_xsrf: <токен>
```

**Успешный ответ (200):**
```json
{
  "success": true,
  "error": false,
  "msg": "Регистрация прошла успешно! Проверьте вашу почту для подтверждения.",
  "userId": 123,
  "db": "userexample123",
  "confirmationRequired": true
}
```

**Ответ с ошибкой (400):**
```json
{
  "success": false,
  "error": true,
  "msg": "Вы ввели неверный email"
}
```

### 2. GET `/api/confirm.php` - Подтверждение email

Подтверждает email пользователя по токену из письма.

**Параметры:**
```
u: <userId>
c: <confirmationToken>
```

**Пример:**
```
GET /api/confirm.php?u=123&c=abc123def456
```

**Ответ:**
HTML страница с сообщением об успехе или ошибке и автоматическим редиректом на страницу входа.

### 3. GET `/api/xsrf.php` - Получение XSRF токена

Возвращает XSRF токен и информацию о текущем пользователе.

**Параметры:** нет

**Ответ (200):**
```json
{
  "_xsrf": "abc123def456...",
  "token": "auth_token_here",
  "user": "user@example.com",
  "role": "user",
  "id": "123",
  "msg": ""
}
```

Для гостя (не авторизован):
```json
{
  "_xsrf": "abc123def456...",
  "token": "",
  "user": "guest",
  "role": "guest",
  "id": "",
  "msg": ""
}
```

## 🌐 Интеграция с фронтендом

Фронтенд (HTML/JS) уже настроен на работу с этим API. Для использования:

### 1. Настройка API в config.html

Откройте `config.html` в браузере и укажите:
- **API Host**: ваш домен (например, `example.com`)
- **Database Name**: имя базы данных (переменная `$z`)

### 2. Процесс регистрации

1. Пользователь заполняет форму на `register.html`
2. JavaScript отправляет POST запрос на `/api/register.php`
3. Backend создает пользователя и отправляет email
4. Пользователь получает письмо с ссылкой подтверждения
5. При переходе по ссылке вызывается `/api/confirm.php`
6. Аккаунт активируется, пользователь перенаправляется на `login.html`

### 3. Проверка статуса

При загрузке страниц фронтенд вызывает `/api/xsrf.php` для:
- Получения XSRF токена
- Проверки авторизации пользователя

## 🧪 Тестирование

### Запуск тестов

```bash
# Тест конфигурации
php experiments/test_config.php

# Тест API регистрации
php experiments/test_registration_api.php
```

### Тестирование через curl

**Получение XSRF токена:**
```bash
curl -X GET http://your-domain.com/api/xsrf.php
```

**Регистрация пользователя:**
```bash
curl -X POST http://your-domain.com/api/register.php \
  -d "email=test@example.com" \
  -d "regpwd=password123" \
  -d "regpwd1=password123" \
  -d "agree=1" \
  -d "_xsrf=your_token_here"
```

**Подтверждение email:**
```bash
curl -X GET "http://your-domain.com/api/confirm.php?u=123&c=confirmation_token_here"
```

### Проверка отправки email

В `config.php` включите отладку SMTP:
```php
'smtp_debug' => true,
```

Проверьте логи PHP для отладочной информации SMTP.

## 🔒 Безопасность

### Реализованные меры безопасности

1. **Хеширование паролей**: SHA-512 с уникальной солью
2. **XSRF защита**: Токены для защиты от CSRF атак
3. **Email валидация**: Фильтрация и проверка email адресов
4. **SQL injection защита**: Экранирование данных перед запросами
5. **Подтверждение email**: Проверка владения email адресом
6. **Безопасные токены**: Криптографически стойкие случайные токены

### Рекомендации для production

1. **HTTPS обязателен**: Всегда используйте SSL/TLS
2. **Отключите debug**: Установите `display_errors = 0` в PHP
3. **Ограничьте CORS**: Замените `*` на конкретные домены
4. **Rate limiting**: Добавьте ограничение запросов
5. **Проверяйте XSRF**: Раскомментируйте проверку в `register.php`:
   ```php
   if (!validateXsrfToken($xsrf)) {
       my_die("Security error");
   }
   ```
6. **Мониторинг**: Отслеживайте логи на подозрительную активность
7. **Обновления**: Регулярно обновляйте PHP и зависимости

### Защита конфигурации

Убедитесь, что `config.php` защищен:

```bash
# Права доступа
chmod 600 config.php

# Проверьте .gitignore
grep "config.php" .gitignore
```

## 🐛 Устранение неполадок

### Email не отправляются

1. **Проверьте настройки SMTP** в `config.php`
2. **Включите отладку**: `'smtp_debug' => true`
3. **Проверьте логи**: `tail -f /var/log/php/error.log`
4. **Тест подключения**:
   ```bash
   telnet smtp.example.com 465
   ```
5. **Проверьте firewall**: порты 465 (SSL) или 587 (TLS)

### Ошибка подключения к базе данных

1. **Проверьте учетные данные** в `config.php`
2. **Проверьте MySQL сервер**: `systemctl status mysql`
3. **Тест подключения**:
   ```bash
   mysql -h localhost -u username -p database_name
   ```
4. **Проверьте права пользователя**:
   ```sql
   SHOW GRANTS FOR 'username'@'localhost';
   ```

### CORS ошибки

Если фронтенд на другом домене/порту:

1. **Проверьте заголовки** в API файлах
2. **Настройте веб-сервер** (Apache/Nginx)
3. **Включите credentials** для сессий:
   ```javascript
   fetch(url, { credentials: 'include' })
   ```

### Проблемы с сессиями

1. **Проверьте права** на директорию сессий:
   ```bash
   ls -la /var/lib/php/sessions
   ```
2. **Настройте session.save_path** в `php.ini`
3. **Проверьте cookie settings** в браузере

### Таблица не существует

Убедитесь, что:
1. Таблица создана в базе данных
2. Переменная `$z` установлена корректно
3. Пользователь MySQL имеет права на таблицу

## 📊 Типы данных (CRM структура)

Backend использует CRM-подобную структуру с типами:

```php
define('USER', 1);       // Пользователь
define('TOKEN', 2);      // Токен подтверждения
define('XSRF', 3);       // XSRF токен
define('ACTIVITY', 4);   // Активность
define('EMAIL', 18);     // Email адрес
define('PASSWORD', 20);  // Пароль
define('NAME', 33);      // Имя пользователя
define('DATE', 156);     // Дата регистрации
define('ROLE', 164);     // Роль пользователя
define('PICTURE', 280);  // Аватар
define('CAMPAIGN', 304); // Campaign tag
define('AFFILIATE', 1012); // Affiliate reference
```

## 📝 Лицензия

MIT

## 🙋 Поддержка

Если у вас возникли вопросы или проблемы:

1. Проверьте раздел "Устранение неполадок"
2. Изучите логи PHP и веб-сервера
3. Запустите тест скрипты в `experiments/`
4. Создайте issue в репозитории проекта

---

**Важно:** Это реализация основана на коде из issue #6 и адаптирована для работы с существующим фронтендом из репозитория.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
