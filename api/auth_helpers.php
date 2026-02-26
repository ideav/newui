<?php
/**
 * Authentication Helper Functions
 *
 * Provides utility functions for authentication and user management
 */

require_once __DIR__ . '/../config.php';

/**
 * Hash a password with salt
 *
 * @param string $user Username
 * @param string $password Password
 * @return string Hashed password
 */
function Salt($user, $password) {
    return SALT . $user . $password;
}

/**
 * Generate a database name from email and user ID
 *
 * @param string $email Email address
 * @param int $userId User ID
 * @return string Database name
 */
function mail2DB($email, $userId) {
    // Extract local part of email (before @)
    $local = strstr($email, '@', true);

    // Sanitize to create valid database name
    $db = preg_replace('/[^a-zA-Z0-9]/', '', $local);

    // Add user ID to make it unique
    $db .= $userId;

    // Ensure it starts with a letter
    if (!preg_match('/^[a-zA-Z]/', $db)) {
        $db = 'db' . $db;
    }

    // Limit length (MySQL database name max is 64 chars)
    $db = substr($db, 0, 64);

    return strtolower($db);
}

/**
 * Translate a string based on language tags
 * Format: [RU]Russian text[EN]English text[LANG]...
 *
 * @param string $text Text with language tags
 * @param string $lang Current language (default: 'RU')
 * @return string Translated text
 */
function t9n($text, $lang = 'RU') {
    // Extract text for current language
    $pattern = '/\[' . $lang . '\](.*?)(?:\[|$)/s';
    if (preg_match($pattern, $text, $matches)) {
        return trim($matches[1]);
    }

    // Fallback to first available language
    if (preg_match('/\[(.*?)\](.*?)(?:\[|$)/s', $text, $matches)) {
        return trim($matches[2]);
    }

    return $text;
}

/**
 * Generate a random token for email confirmation
 *
 * @param int $length Token length (default: 32)
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate or retrieve XSRF token for current session
 *
 * @return string XSRF token
 */
function getXsrfToken() {
    if (!session_id()) {
        session_start();
    }

    if (!isset($_SESSION['xsrf_token'])) {
        $_SESSION['xsrf_token'] = generateToken(32);
    }

    return $_SESSION['xsrf_token'];
}

/**
 * Validate XSRF token
 *
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function validateXsrfToken($token) {
    if (!session_id()) {
        session_start();
    }

    return isset($_SESSION['xsrf_token']) && hash_equals($_SESSION['xsrf_token'], $token);
}

/**
 * Update user tokens (for session management)
 *
 * @param array $row User data row
 * @return void
 */
function updateTokens($row) {
    global $z;

    define('TOKEN', 2);
    define('XSRF', 3);

    $userId = $row['uid'];
    $token = generateToken(64);
    $xsrf = generateToken(32);

    // Update or insert auth token
    if ($row['tok']) {
        Exec_sql("UPDATE `$z` SET val='$token' WHERE id=" . $row['tok'], "Update auth token");
    } else {
        Insert($userId, 1, TOKEN, $token, "Insert auth token");
    }

    // Update or insert XSRF token
    if ($row['xsrf']) {
        Exec_sql("UPDATE `$z` SET val='$xsrf' WHERE id=" . $row['xsrf'], "Update XSRF token");
    } else {
        Insert($userId, 1, XSRF, $xsrf, "Insert XSRF token");
    }

    // Store in session
    if (!session_id()) {
        session_start();
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['auth_token'] = $token;
    $_SESSION['xsrf_token'] = $xsrf;
}

/**
 * Create user database (placeholder - implement as needed)
 *
 * @param int $userId User ID
 * @param string $db Database name
 * @param string $email Email address
 * @param string $password Password
 * @return void
 */
function createDb($userId, $db, $email, $password) {
    // This function should create a user-specific database
    // Implementation depends on your CRM system architecture
    // For now, this is a placeholder
    error_log("createDb called for user $userId, db: $db");
}

/**
 * Handle die with message
 *
 * @param string $msg Message to display
 * @return void
 */
function my_die($msg) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => true,
        'msg' => $msg
    ]);
    exit;
}

/**
 * Redirect to login page with message
 *
 * @param string $db Database name
 * @param string $login Login name
 * @param string $msg Message code
 * @return void
 */
function login($db, $login, $msg) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'redirect' => "login.html",
        'db' => $db,
        'login' => $login,
        'msg' => $msg
    ]);
    exit;
}

?>
