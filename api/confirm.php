<?php
/**
 * Email Confirmation Endpoint
 *
 * Handles email confirmation after user registration
 * Based on the original CRM confirmation system from issue #6
 *
 * Expected GET parameters:
 * - u: User ID
 * - c: Confirmation token
 *
 * Alternative parameters:
 * - optout: User ID (to opt-out of emails)
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load dependencies
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/auth_helpers.php';

// Handle opt-out request
if (isset($_GET["optout"])) {
    $userId = (int)$_GET["optout"];

    if ($userId > 0) {
        try {
            // Mark user as opted out (you can implement this as needed)
            // For now, just log it
            error_log("User $userId opted out of emails");

            // Show opt-out confirmation page
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отписка от рассылки</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>' . t9n("[RU]Вы отписались от рассылки[EN]You have been unsubscribed") . '</h1>
            <p>' . t9n("[RU]Вы больше не будете получать письма от нас.[EN]You will no longer receive emails from us.") . '</p>
            <a href="../index.html" class="btn-primary">' . t9n("[RU]На главную[EN]Go to homepage") . '</a>
        </div>
    </div>
</body>
</html>';
            exit;
        } catch (Exception $e) {
            error_log("Opt-out error: " . $e->getMessage());
        }
    }

    http_response_code(400);
    echo t9n("[RU]Неверный запрос[EN]Invalid request");
    exit;
}

// Check if this is a confirmation request
if (isset($_GET["c"]) && isset($_GET["u"])) {
    $userId = (int)$_GET["u"];
    $confirmToken = $_GET["c"];

    if ($userId <= 0) {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>' . t9n("[RU]Ошибка[EN]Error") . '</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>' . t9n("[RU]Ошибка подтверждения[EN]Confirmation error") . '</h1>
            <p>' . t9n("[RU]Неверный идентификатор пользователя[EN]Invalid user ID") . '</p>
            <a href="../login.html" class="btn-primary">' . t9n("[RU]Войти[EN]Login") . '</a>
        </div>
    </div>
</body>
</html>';
        exit;
    }

    try {
        // Get user data
        $user = getUserById($userId);

        if (!$user || !$user["uid"]) {
            // User not found
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>' . t9n("[RU]Ошибка[EN]Error") . '</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>' . t9n("[RU]Ошибка подтверждения[EN]Confirmation error") . '</h1>
            <p>' . t9n("[RU]Пользователь не найден[EN]User not found") . '</p>
            <a href="../register.html" class="btn-primary">' . t9n("[RU]Зарегистрироваться[EN]Register") . '</a>
        </div>
    </div>
</body>
</html>';
            exit;
        }

        // Verify confirmation token
        if ($user["token"] !== $confirmToken) {
            // Invalid or expired token
            http_response_code(400);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>' . t9n("[RU]Ошибка[EN]Error") . '</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>' . t9n("[RU]Ошибка подтверждения[EN]Confirmation error") . '</h1>
            <p>' . t9n("[RU]Неверный или истекший токен подтверждения[EN]Invalid or expired confirmation token") . '</p>
            <a href="../login.html" class="btn-primary">' . t9n("[RU]Войти[EN]Login") . '</a>
        </div>
    </div>
</body>
</html>';
            exit;
        }

        // Update the user's password to confirmed state (hash it with salt)
        $hashedPassword = hash('sha512', Salt($user["user"], $user["pwd"]));
        global $z;
        Exec_sql("UPDATE `$z` SET val='$hashedPassword' WHERE id=" . $user["pid"], "Update user's password");

        // Update tokens for the user
        updateTokens($user);

        // Create user database (if applicable)
        createDb($user["uid"], "", $user["email"], $user["pwd"]);

        // Successful confirmation - redirect to login or main page
        $db = mail2DB($user["email"], $user["uid"]);

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . t9n("[RU]Подтверждение успешно[EN]Confirmation successful") . '</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>✓ ' . t9n("[RU]Email подтвержден![EN]Email confirmed!") . '</h1>
            <p>' . t9n("[RU]Ваш аккаунт успешно активирован.[EN]Your account has been successfully activated.") . '</p>
            <p>' . t9n("[RU]Теперь вы можете войти в систему.[EN]You can now log in.") . '</p>
            <a href="../login.html?email=' . urlencode($user["email"]) . '" class="btn-primary">' . t9n("[RU]Войти в систему[EN]Log in") . '</a>
        </div>
    </div>
    <script>
        // Auto-redirect after 3 seconds
        setTimeout(function() {
            window.location.href = "../login.html?email=' . urlencode($user["email"]) . '";
        }, 3000);
    </script>
</body>
</html>';
        exit;

    } catch (Exception $e) {
        error_log("Confirmation error: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>' . t9n("[RU]Ошибка[EN]Error") . '</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>' . t9n("[RU]Ошибка сервера[EN]Server error") . '</h1>
            <p>' . t9n("[RU]Произошла ошибка при подтверждении. Попробуйте позже.[EN]An error occurred during confirmation. Please try again later.") . '</p>
            <a href="../login.html" class="btn-primary">' . t9n("[RU]Войти[EN]Login") . '</a>
        </div>
    </div>
</body>
</html>';
        exit;
    }
}

// No valid parameters - show error
http_response_code(400);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>' . t9n("[RU]Ошибка[EN]Error") . '</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>' . t9n("[RU]Неверный запрос[EN]Invalid request") . '</h1>
            <p>' . t9n("[RU]Отсутствуют необходимые параметры[EN]Missing required parameters") . '</p>
            <a href="../index.html" class="btn-primary">' . t9n("[RU]На главную[EN]Go to homepage") . '</a>
        </div>
    </div>
</body>
</html>';
?>
