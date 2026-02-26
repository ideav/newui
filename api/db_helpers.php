<?php
/**
 * Database Helper Functions
 *
 * Provides helper functions for database operations including:
 * - SQL query execution with error handling
 * - Insert operations
 * - Data validation
 */

require_once __DIR__ . '/../config.php';

// DB name masks - defined at module level
if (!defined('DB_MASK'))      define('DB_MASK',      "/^[a-z0-9_]{1,15}$/i");
if (!defined('USER_DB_MASK')) define('USER_DB_MASK', "/^[a-z]\w{2,14}$/i");

// Type constants - defined at module level to prevent PHP 8.3 redefinition errors
if (!defined('USER'))     define('USER',     18);
if (!defined('DATABASE')) define('DATABASE', 271);
if (!defined('PHONE'))    define('PHONE',    30);
if (!defined('XSRF'))     define('XSRF',     40);
if (!defined('EMAIL'))    define('EMAIL',    41);
if (!defined('ROLE'))     define('ROLE',     42);
if (!defined('ACTIVITY')) define('ACTIVITY', 124);
if (!defined('PASSWORD')) define('PASSWORD', 20);
if (!defined('TOKEN'))    define('TOKEN',    125);
if (!defined('SECRET'))   define('SECRET',   130);
if (!defined('VERSION'))  define('VERSION',  8);
if (!defined('NAME'))     define('NAME',     33);
if (!defined('PICTURE'))  define('PICTURE',  280);
if (!defined('DATE'))     define('DATE',     156);

/**
 * Execute SQL query with error handling and logging
 *
 * @param string $sql SQL query to execute
 * @param string $message Description of the operation
 * @return mysqli_result|bool Query result or false on failure
 */
function Exec_sql($sql, $message = "") {
    global $connection;

    $result = mysqli_query($connection, $sql);

    if (!$result) {
        $error = mysqli_error($connection);
        error_log("SQL Error [$message]: $error\nQuery: $sql");
        throw new Exception("Database error: $message");
    }

    return $result;
}

/**
 * Insert a new record into the database and return the ID
 *
 * @param int $up Parent ID (up field)
 * @param int $ord Order field
 * @param int $t Type field
 * @param string $val Value to insert
 * @param string $message Description of the operation
 * @return int The ID of the inserted record
 */
function Insert($up, $ord, $t, $val, $message = "") {
    global $connection, $z;

    $val_escaped = mysqli_real_escape_string($connection, $val);
    $sql = "INSERT INTO `$z` (up, ord, t, val) VALUES ($up, $ord, $t, '$val_escaped')";

    Exec_sql($sql, "Insert: $message");

    $id = mysqli_insert_id($connection);
    return $id;
}

/**
 * Create a new user and return their ID
 *
 * @param string $user Username (typically email)
 * @param string $email Email address
 * @param string $role Role ID (e.g., "115" for regular user)
 * @param string $name Display name
 * @param string $picture Profile picture URL
 * @return int The new user's ID
 */
function newUser($user, $email, $role, $name = "", $picture = "") {
    $id = Insert(1, 0, USER, $user, "Insert new user");
    Insert($id, 1, EMAIL, $email, "Insert email");
    Insert($id, 1, 164, $role, "Insert User role link");

    // Insert registration date
    Insert($id, 1, DATE, date("Ymd"), "Insert date");

    if (strlen($name)) {
        Insert($id, 1, NAME, $name, "Insert name");
    }

    if (strlen($picture)) {
        Insert($id, 1, PICTURE, $picture, "Insert picture");
    }

    return $id;
}

/**
 * Check if a user with the given email already exists
 *
 * @param string $email Email address to check
 * @return bool True if user exists, false otherwise
 */
function userExists($email) {
    global $z, $connection;

    $email_escaped = mysqli_real_escape_string($connection, $email);
    $result = Exec_sql("SELECT 1 FROM `$z` WHERE val='$email_escaped' AND t=" . USER, "Check user name uniquity");

    $row = mysqli_fetch_array($result);
    return $row && $row[0];
}

/**
 * Get user information by ID
 *
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserById($userId) {
    global $z;

    $result = Exec_sql("SELECT user.val user, user.id uid, token.id tok, token.val token,
                              xsrf.id xsrf, act.id act, pwd.val pwd, pwd.id pid, email.val email
                        FROM `$z` user
                        LEFT JOIN `$z` token ON token.up=user.id AND token.t=" . TOKEN . "
                        LEFT JOIN `$z` xsrf ON xsrf.up=user.id AND xsrf.t=" . XSRF . "
                        LEFT JOIN `$z` pwd ON pwd.up=user.id AND pwd.t=" . PASSWORD . "
                        LEFT JOIN `$z` act ON act.up=user.id AND act.t=" . ACTIVITY . "
                        LEFT JOIN `$z` email ON email.up=user.id AND email.t=" . EMAIL . "
                        WHERE user.id=$userId AND user.t=" . USER, "Get user by ID");

    $row = mysqli_fetch_array($result);
    return $row ? $row : null;
}

?>
