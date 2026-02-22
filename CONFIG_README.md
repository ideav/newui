# Руководство по настройке конфигурации

Этот документ описывает процесс настройки конфигурационных файлов для PHP-части приложения.

## 📋 Содержание

- [Быстрый старт](#быстрый-старт)
- [Файлы конфигурации](#файлы-конфигурации)
- [Детальная настройка](#детальная-настройка)
- [Безопасность](#безопасность)
- [Альтернативный подход с .env](#альтернативный-подход-с-env)

## 🚀 Быстрый старт

### Вариант 1: Использование config.php (рекомендуется)

1. **Скопируйте шаблон конфигурации:**
   ```bash
   cp config.example.php config.php
   ```

2. **Отредактируйте config.php** и заполните свои реальные данные:
   ```bash
   nano config.php
   # или используйте любой другой редактор
   ```

3. **Убедитесь, что config.php не добавлен в git:**
   ```bash
   git status
   # config.php должен быть в .gitignore
   ```

### Вариант 2: Использование .env файла

1. **Скопируйте шаблон .env:**
   ```bash
   cp .env.example .env
   ```

2. **Отредактируйте .env** и заполните свои реальные данные

3. **Установите библиотеку для работы с .env** (опционально):
   ```bash
   composer require vlucas/phpdotenv
   ```

## 📁 Файлы конфигурации

### config.php
**Главный конфигурационный файл** с реальными данными (НЕ коммитить в git!)

Содержит:
- Подключение к базе данных
- SMTP настройки
- OAuth ключи (Google, Yandex)
- SMS сервис
- Секретные ключи и соли

### config.example.php
**Шаблон конфигурации** для разработчиков (коммитится в git)

Используется как:
- Пример для новых разработчиков
- Документация по всем доступным настройкам
- Шаблон для создания config.php

### .env.example
**Альтернативный шаблон** в формате переменных окружения

Для тех, кто предпочитает:
- Современный подход с переменными окружения
- Интеграцию с Docker
- Использование библиотеки phpdotenv

## ⚙️ Детальная настройка

### 1. База данных

```php
define('DB_HOST', 'localhost');        // Хост базы данных
define('DB_USERNAME', 'your_username'); // Имя пользователя
define('DB_PASSWORD', 'your_password'); // Пароль
define('DB_NAME', 'your_database');    // Название базы данных
define('DB_CHARSET', 'utf8mb4');       // Кодировка (не меняйте)
```

**Важно:**
- Используйте `utf8mb4` для поддержки всех Unicode символов, включая эмодзи
- Убедитесь, что пользователь имеет необходимые права доступа

### 2. SMTP / Email

```php
$mail_config = [
    'smtp_username' => 'your_email@example.com',
    'smtp_port'     => '465',                    // 465 для SSL, 587 для TLS
    'smtp_host'     => 'ssl://smtp.example.com', // Префикс ssl:// для SSL
    'smtp_password' => 'your_password',
    'smtp_debug'    => false,                    // true для отладки
    'smtp_charset'  => 'utf-8',
    'smtp_from'     => 'Your App Name',
];
```

**Популярные SMTP серверы:**

| Провайдер | Host | Port | Префикс |
|-----------|------|------|---------|
| Yandex | smtp.yandex.ru | 465 | ssl:// |
| Gmail | smtp.gmail.com | 465 | ssl:// |
| Mail.ru | smtp.mail.ru | 465 | ssl:// |
| Office365 | smtp.office365.com | 587 | tls:// |

### 3. Безопасность

#### Соль (SALT)

```php
define('SALT', 'your_unique_salt_here');
```

**Генерация безопасной соли:**

```bash
# Вариант 1: OpenSSL (рекомендуется)
openssl rand -base64 32

# Вариант 2: /dev/urandom
head -c 32 /dev/urandom | base64

# Вариант 3: PHP
php -r "echo bin2hex(random_bytes(32));"
```

**Важно:**
- НИКОГДА не используйте слово "salt" или другие простые значения
- Соль должна быть уникальной для каждого проекта
- НЕ меняйте соль после начала использования в production!

#### Admin Hash

```php
define('ADMINHASH', sha1($_SERVER['SERVER_NAME'] . ($z ?? '') . 'your_secret_key'));
```

Генерируется автоматически, но требует:
- Уникальный секретный ключ (замените `'your_secret_key'`)
- Используется для дополнительной защиты админ-функций

### 4. Google OAuth

Получение учетных данных:

1. Перейдите на https://console.developers.google.com/
2. Создайте новый проект или выберите существующий
3. Включите Google+ API
4. Создайте OAuth 2.0 Client ID
5. Выберите тип "Web application"
6. Добавьте разрешенные redirect URIs
7. Скопируйте Client ID и Client Secret

```php
define('G_CLIENT_ID', 'your_id.apps.googleusercontent.com');
define('G_CLIENT_PK', 'your_client_secret');
```

### 5. Yandex OAuth

Получение учетных данных:

1. Перейдите на https://oauth.yandex.com/
2. Создайте новое приложение
3. В разделе "Платформы" выберите "Веб-сервисы"
4. Укажите Callback URL
5. Выберите необходимые права доступа
6. Скопируйте Client ID и Client Secret

```php
define('Y_CLIENT_ID', 'your_yandex_client_id');
define('Y_CLIENT_PK', 'your_yandex_client_secret');
```

### 6. SMS сервис

```php
define('SMS_OP', 'http://gateway.api.sc/get/?user=your_user&pwd=your_pwd');
```

Настройте в соответствии с вашим SMS провайдером.

## 🔒 Безопасность

### ❌ НИКОГДА не коммитьте

- `config.php` - содержит реальные пароли и ключи
- `.env` - содержит переменные окружения с секретами
- Любые бэкапы конфигов (`config.php.backup`, `*.bak`)

### ✅ Всегда коммитьте

- `config.example.php` - шаблон без реальных данных
- `.env.example` - пример переменных окружения
- `.gitignore` - для защиты от случайного коммита

### Проверка безопасности

```bash
# Убедитесь, что config.php в .gitignore
grep -r "config.php" .gitignore

# Проверьте, что config.php не отслеживается git
git ls-files | grep config.php

# Если config.php показывается, удалите его из git:
git rm --cached config.php
git commit -m "Remove config.php from version control"
```

### Права доступа к файлам

```bash
# Для Unix/Linux систем
chmod 600 config.php  # Только владелец может читать/писать
chmod 644 config.example.php  # Все могут читать, владелец может писать
```

## 🔄 Альтернативный подход с .env

Если вы предпочитаете использовать переменные окружения:

### 1. Установите phpdotenv

```bash
composer require vlucas/phpdotenv
```

### 2. Создайте loader.php

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Теперь используйте переменные окружения
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_USERNAME', $_ENV['DB_USERNAME']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);
// ... и т.д.
?>
```

### 3. Подключите в начале скриптов

```php
require_once __DIR__ . '/loader.php';
```

## 📝 Примеры использования

### Подключение config.php в ваших скриптах

```php
<?php
// В начале вашего PHP скрипта
require_once __DIR__ . '/config.php';

// Теперь все константы и $connection доступны
echo DB_NAME;  // Название базы данных
echo ADMINEMAIL;  // Email администратора

// Работа с базой данных
$result = mysqli_query($connection, "SELECT * FROM users");
?>
```

### Проверка подключения

Создайте файл `test_config.php` для проверки:

```php
<?php
require_once __DIR__ . '/config.php';

echo "✓ Config loaded successfully!\n";
echo "Database: " . DB_NAME . "\n";
echo "SMTP From: " . $mail_config['smtp_from'] . "\n";
echo "Admin Email: " . ADMINEMAIL . "\n";

if ($connection) {
    echo "✓ Database connection established!\n";
} else {
    echo "✗ Database connection failed!\n";
}
?>
```

Запустите: `php test_config.php`

## 🐛 Решение проблем

### Ошибка: "Couldn't connect to database"

1. Проверьте учетные данные в config.php
2. Убедитесь, что MySQL сервер запущен
3. Проверьте, что пользователь имеет права доступа
4. Проверьте правильность хоста (localhost vs 127.0.0.1)

### Ошибка: "config.php not found"

1. Убедитесь, что вы скопировали config.example.php в config.php
2. Проверьте путь к файлу в require_once
3. Проверьте права доступа к файлу

### SMTP не отправляет письма

1. Включите отладку: `'smtp_debug' => true`
2. Проверьте логи сервера
3. Убедитесь в правильности порта и хоста
4. Для Gmail: включите "менее безопасные приложения" или используйте App Password

## 📚 Дополнительные ресурсы

- [PHP mysqli документация](https://www.php.net/manual/ru/book.mysqli.php)
- [Google OAuth 2.0](https://developers.google.com/identity/protocols/oauth2)
- [Yandex OAuth](https://yandex.ru/dev/oauth/)
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) - для более продвинутой работы с email
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) - для работы с .env файлами

## 📞 Поддержка

Если у вас возникли вопросы или проблемы:

1. Проверьте раздел "Решение проблем" выше
2. Изучите комментарии в config.example.php
3. Создайте issue в репозитории проекта

---

**Важно:** Всегда проверяйте безопасность ваших конфигурационных файлов и никогда не публикуйте реальные пароли и ключи!
