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
 * Generate XSRF token from two values
 *
 * @param string $a First value (typically auth token)
 * @param string $b Second value (typically db name)
 * @return string XSRF token (22 chars)
 */
function xsrf($a, $b) {
    return substr(hash('sha512', Salt($a, $b)), 0, 22);
}

/**
 * Write to log
 *
 * @param string $text Text to log
 * @param string $mode Log mode (default: 'log')
 * @return void
 */
function wlog($text, $mode = "log") {
    if (isset($GLOBALS["TRACE"])) {
        $GLOBALS["TRACE"] .= $text . "<br/>\n";
        if ($mode === "log" && defined('LOGS_DIR') && isset($GLOBALS["logFile"])) {
            $file = fopen($GLOBALS["logFile"], "a");
            if ($file) {
                fwrite($file, $text . "\n");
                fclose($file);
            }
        }
    }
    error_log($text);
}

/**
 * Generate a database name from email and user ID
 *
 * @param string $email Email address
 * @param int $userId User ID
 * @return string Database name
 */
function mail2DB($email, $userId) {
    // Try converting the user's email into the DB name
    $tmp = explode('@', $email);
    $db = strtolower(substr(preg_replace("/[^A-Za-z0-9 ]/", '', array_shift($tmp)), 0, 15));
    // In case the name does not fit, try using 'g' + user ID
    if (!defined('USER_DB_MASK') || !preg_match(USER_DB_MASK, $db) || !isDbVacant($db))
        $db = "g$userId";
    return $db;
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

    $userId = $row['uid'];

    // Generate or reuse auth token
    if ($row['tok']) {
        $token = $row['token'];
    } else {
        $token = md5(microtime(true));
        Insert($userId, 1, TOKEN, $token, "Save token");
    }

    // Generate XSRF token based on auth token and db name
    $xsrf = xsrf($token, $z);

    // Update or insert XSRF token
    if ($row['xsrf']) {
        Exec_sql("UPDATE `$z` SET val='" . mysqli_real_escape_string($GLOBALS['connection'], $xsrf) . "' WHERE id=" . $row['xsrf'], "Update XSRF token");
    } else {
        Insert($userId, 1, XSRF, $xsrf, "Save xsrf");
    }

    // Update activity time
    if (isset($row['act']) && $row['act']) {
        Exec_sql("UPDATE `$z` SET val='" . microtime(true) . "' WHERE id=" . $row['act'], "Update activity time");
    } else {
        Insert($userId, 1, ACTIVITY, microtime(true), "Save activity time");
    }

    // Set the auth cookie
    setcookie($z, $token, time() + 2592000 * 12, "/"); // 30*12 days

    // Store in globals for downstream use
    $GLOBALS['GLOBAL_VARS']['token'] = $token;
    $GLOBALS['GLOBAL_VARS']['xsrf'] = $xsrf;
}

/**
 * Check if a database name is available (not taken)
 *
 * @param string $db Database name
 * @return bool True if available, false if already exists
 */
function isDbVacant($db) {
    global $connection;
    $result = mysqli_query($connection, "SELECT 1 FROM `$db` LIMIT 1");
    return $result === false; // false means table doesn't exist = vacant
}

/**
 * Create a new database for a user from a template
 *
 * @param string $db Database name
 * @param string $template Template name (ru/en/fu)
 * @param string $name User display name
 * @param string $email User email
 * @param string $pwd User password (plain text, will be hashed)
 * @return void
 */
function newDb($db, $template, $name, $email, $pwd) {
    global $z, $locale;
    $oldz = $z;
    $z = $db;
    if (defined('TEMPLATES') && strpos(TEMPLATES, ":" . strtolower($template) . ":") !== false)
        $template = strtolower($template);
    else
        $template = "ru";
    Exec_sql("CREATE TABLE `$db` LIKE `$template`", "Create the initial table");
    Exec_sql("INSERT INTO `$db` SELECT * FROM `$template`", "Fill in the table by template");

    // Insert new user and its data into his DB
    $id = Insert(1, 0, USER, $db, "Insert new DB user");
    Insert($id, 1, 156, date("Ymd"), "Insert date");
    if (strlen($email))
        Insert($id, 1, EMAIL, $email, "Insert DB email");
    if (strlen($name))
        Insert($id, 1, 33, $name, "Insert user name");
    Insert($id, 1, 145, "115", "Insert Admin role link");

    // Set token and xsrf as of the current user's
    Insert($id, 1, TOKEN, $GLOBALS['GLOBAL_VARS']['token'] ?? md5(microtime(true)), "Save token DB");
    Insert($id, 1, XSRF, $GLOBALS['GLOBAL_VARS']['xsrf'] ?? '', "Save xsrf DB");
    Insert($id, 1, ACTIVITY, microtime(true), "Save activity time DB");
    if (strlen($pwd))
        Insert($id, 1, PASSWORD, hash('sha512', Salt($db, $pwd)), "Insert user password");
    setcookie($db, $GLOBALS['GLOBAL_VARS']['token'] ?? '', time() + 2592000 * 12, "/");

    // Create folders for files and templates
    $templateCustomDir = __DIR__ . "/../templates/custom/$template";
    $newCustomDir = __DIR__ . "/../templates/custom/$db";
    $downloadTemplateDir = __DIR__ . "/../download/$template";
    $downloadNewDir = __DIR__ . "/../download/$db";
    if (is_dir($templateCustomDir) && !is_dir($newCustomDir))
        exec("cp -r " . escapeshellarg($templateCustomDir) . " " . escapeshellarg($newCustomDir));
    if (is_dir($downloadTemplateDir) && !is_dir($downloadNewDir))
        exec("cp -r " . escapeshellarg($downloadTemplateDir) . " " . escapeshellarg($downloadNewDir));

    if (defined('ADMINEMAIL'))
        mysendmail(ADMINEMAIL, "New DB from " . $_SERVER['SERVER_NAME'] . ": $db",
            "Email: $email\nhttps://" . $_SERVER['SERVER_NAME'] . "/$db/object/" . USER);
    setcookie($db . "_locale", isset($locale) ? $locale : "RU", time() + 2592000000, "/");
    $z = $oldz;
}

/**
 * Create user database
 *
 * @param int $userId User ID
 * @param string $name User display name
 * @param string $email Email address
 * @param string $pwd Password (plain text)
 * @return void
 */
function createDb($userId, $name, $email, $pwd = "") {
    global $z, $locale;
    $db = mail2DB($email, $userId);
    if (isDbVacant($db)) {
        $template = (isset($locale) && $locale === "EN") ? "en" : "ru";
        newDb($db, $template, $name, $email, $pwd);
        $dbId = Insert($userId, 1, DATABASE, $db, "Register new DB");
        Insert($dbId, 1, 275, date("Ymd"), "Insert new DB date");
        Insert($dbId, 1, 283, $template, "Insert new DB template");
        Insert($dbId, 1, 276, t9n("[RU]Тестовая база, создана при регистрации[EN]Test one, created upon registration"), "Insert new DB notes");
        $z = $db;
    }
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
 * Matches the original CRM login() function behavior:
 * - If API request: returns JSON response
 * - Otherwise: redirects to /index.html with query parameters
 *
 * @param string $db Database name
 * @param string $login Login name
 * @param string $msg Message code
 * @param string $details Additional details
 * @return void
 */
function login($db = "", $login = "", $msg = "", $details = "") {
    $p = "?";
    if (strlen($db))
        $p .= "db=$db&";
    if (strlen($login))
        $p .= "login=$login&";
    elseif (isset($_GET["u"]))
        $p .= "login=" . htmlentities($_GET["u"]) . "&";
    elseif (isset($_GET["login"]))
        $p .= "login=" . htmlentities($_GET["login"]) . "&";
    if (strlen($msg))
        $p .= "r=$msg&";
    $p .= "uri=" . htmlentities($_SERVER["REQUEST_URI"] ?? '/') . "&";
    if (strlen($details))
        $p .= "d=" . urlencode($details) . "&";
    header("Location: /index.html" . substr($p, 0, -1));
    exit;
}

?>
