<?php
/**
 * User Registration Endpoint
 *
 * Handles user registration with email confirmation
 * Based on the original CRM registration system from issue #6
 *
 * Expected POST parameters:
 * - email: User email address
 * - regpwd: Password
 * - regpwd1: Password confirmation
 * - agree: Agreement checkbox (optional)
 * - _xsrf: XSRF token for security
 *
 * Alternative parameters (matching frontend API call):
 * - t18: Email (alternative parameter name)
 * - t20: Password (alternative parameter name)
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => true, 'msg' => 'Method not allowed']);
    exit;
}

// Load dependencies
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/email_helpers.php';
require_once __DIR__ . '/auth_helpers.php';

// Additional type constants not in db_helpers.php
if (!defined('AFFILIATE')) define('AFFILIATE', 1012);
if (!defined('CAMPAIGN'))  define('CAMPAIGN',  304);

try {
    // Get parameters - support both parameter naming conventions
    $email = $_POST['email'] ?? $_POST['t18'] ?? '';
    $password = $_POST['regpwd'] ?? $_POST['t20'] ?? '';
    $password_confirm = $_POST['regpwd1'] ?? $_POST['t20'] ?? ''; // For t20, no confirmation needed
    $agree = $_POST['agree'] ?? '1'; // Default to agreed for API calls
    $xsrf = $_POST['_xsrf'] ?? '';

    // If using t18/t20 parameters (API mode), skip password confirmation check
    $api_mode = isset($_POST['t18']) && isset($_POST['t20']);

    // Validate XSRF token (optional - can be enabled later)
    // if (!validateXsrfToken($xsrf)) {
    //     my_die(t9n("[RU]Ошибка безопасности. Попробуйте еще раз.[EN]Security error. Please try again."));
    // }

    // Validation
    $errors = [];

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t9n("[RU]Вы ввели неверный email[EN]Please provide a correct email");
    }

    // Password validation
    if (!strlen($password)) {
        $errors[] = t9n("[RU]Введен пустой пароль[EN]Please input the password");
    } elseif (strlen($password) < 6) {
        $errors[] = t9n("[RU]Введенный вами пароль слишком короток (менее 6 символов)[EN]The password must be at least 6 characters long");
    }

    // Password confirmation (only in form mode)
    if (!$api_mode && $password !== $password_confirm) {
        $errors[] = t9n("[RU]Введенные вами пароли не совпадают[EN]Repeat the same password twice");
    }

    // Agreement validation (only in form mode)
    if (!$api_mode && !strlen($agree)) {
        $errors[] = t9n("[RU]Пожалуйста, поставьте отметку, что ознакомились с Лицензионным соглашением[EN]Please confirm that you have read the papers to protect you");
    }

    // Return errors if any
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'error' => true,
            'msg' => implode("<br/><br/>\n", $errors)
        ]);
        exit;
    }

    // Check if user already exists
    if (userExists($email)) {
        echo json_encode([
            'success' => false,
            'error' => true,
            'msg' => t9n("[RU]Этот email уже зарегистрирован.[EN]This email is already registered") . " [errMailExists]"
        ]);
        exit;
    }

    // Create new user
    $userId = newUser($email, $email, "115", "", "");

    // Insert password (hashed with salt)
    Insert($userId, 1, PASSWORD, $password, "Insert password");

    // Generate and insert confirmation token
    $confirmToken = md5("xz$email" . time());
    Insert($userId, 1, TOKEN, $confirmToken, "Insert confirmation code");

    // Insert affiliate reference if exists
    if (isset($_COOKIE["_aff"])) {
        Insert($userId, 1, AFFILIATE, (int)$_COOKIE["_aff"], "Insert the affiliate ref");
    }

    // Insert campaign tag if exists
    if (isset($_COOKIE["yd_param"])) {
        $campId = htmlspecialchars($_COOKIE["yd_param"]);
        Insert($userId, 1, CAMPAIGN, $campId, "Insert the campaign tag");
    } else {
        $campId = "";
    }

    // Generate database name
    $db = mail2DB($email, $userId);

    // Send admin notification
    $server_name = $_SERVER["SERVER_NAME"] ?? 'localhost';
    mysendmail(
        ADMINEMAIL,
        "Registration from $campId $server_name: $email",
        "Email: $email\nhttps://$server_name/$db/object/" . USER
    );

    // Send confirmation email to user
    $confirmation_link = "https://$server_name/my/register?u=$userId&c=$confirmToken";

    $email_body = t9n("[RU]\r\nЗдравствуйте![EN]Hello my friend,") . "\r\n\r\n"
        . t9n("[RU]Для подтверждения регистрации пройдите по ссылке:[EN]To complete the registration click the following link:")
        . "\r\n$confirmation_link\r\n"
        . t9n("[RU]или скопируйте её и откройте в Вашем web-браузере.\r\nЭта ссылка действительна в течение трех дней."
            . "[EN]or copy its text and open it in your Internet browser.\r\nThe link will expire in 3 days.")
        . "\r\n" . t9n("[RU]Имя вашей базы[EN]The name of your database") . ": $db, "
        . t9n("[RU]пользователь[EN]user") . ": $db"
        . "\r\n\r\n" . t9n("[RU]После подтверждения ваша база будет здесь[EN]After the confirmation you will find your database here")
        . ":\r\nhttps://$server_name/$db?login=$db"
        . "\r\n\r\n" . t9n("[RU]С уважением,\r\nКоманда Интеграл[EN]Best regards,\r\nIdeaV team")
        . "\r\n\r\n" . t9n("[RU]Если вы не хотите получать от нас писем, связанных с регистрацией, вы можете отписаться от оповещений:"
            . "\r\nhttps://$server_name/my/register?optout=$userId"
            . "[EN]In case you do not want to receive messages regarding your registration, unsubscribe here:"
            . "\r\nhttps://$server_name/my/register?optout=$userId");

    mysendmail(
        $email,
        t9n("[RU]Регистрация на сервисе [EN]Registration from ") . $server_name,
        $email_body
    );

    // Return success response
    echo json_encode([
        'success' => true,
        'error' => false,
        'msg' => t9n("[RU]Регистрация прошла успешно! Проверьте вашу почту для подтверждения.[EN]Registration successful! Check your email for confirmation."),
        'userId' => $userId,
        'db' => $db,
        'confirmationRequired' => true
    ]);

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'msg' => t9n("[RU]Произошла ошибка при регистрации. Попробуйте позже.[EN]An error occurred during registration. Please try again later.")
    ]);
}

?>
