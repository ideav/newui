<?php
/**
 * Application Configuration File - EXAMPLE TEMPLATE
 *
 * This is a template configuration file. To use it:
 * 1. Copy this file to config.php
 * 2. Fill in your actual credentials and settings
 * 3. Never commit config.php to version control!
 *
 * This file contains all sensitive configuration data including:
 * - Database credentials
 * - SMTP/Email settings
 * - OAuth credentials (Google, Yandex)
 * - SMS service settings
 * - Security settings (salt, admin hash)
 */

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

define('DB_HOST', 'localhost');
define('DB_USERNAME', 'your_database_username');
define('DB_PASSWORD', 'your_database_password');
define('DB_NAME', 'your_database_name');
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// SMTP/EMAIL CONFIGURATION
// ============================================================================

// SMTP Configuration Array
global $mail_config;
$mail_config = [
    'smtp_username' => 'your_email@example.com',  // SMTP username / reply-to address
    'smtp_port'     => '465',                      // SMTP port (465 for SSL, 587 for TLS)
    'smtp_host'     => 'ssl://smtp.example.com',   // SMTP server
    'smtp_password' => 'your_smtp_password',       // SMTP password
    'smtp_debug'    => false,                      // Set to true to enable debug output
    'smtp_charset'  => 'utf-8',                   // Email charset (utf-8 or windows-1251)
    'smtp_from'     => 'Your App Name',           // "From" name by default
];

// ============================================================================
// ADMIN CONFIGURATION
// ============================================================================

define('ADMINEMAIL', 'admin@example.com');

// ============================================================================
// APPLICATION SETTINGS
// ============================================================================

define('TEMPLATES', ':en:ru:fu:');

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

// Security salt - used for password hashing and other cryptographic operations
// IMPORTANT: Change this to a unique random string!
// You can generate a random salt using: openssl rand -base64 32
define('SALT', 'your_unique_salt_here_change_this');

// Admin hash - generated dynamically based on server name and salt
// This provides an additional layer of security for admin operations
define('ADMINHASH', sha1($_SERVER['SERVER_NAME'] . ($z ?? '') . 'your_secret_key_here'));

// ============================================================================
// SMS SERVICE CONFIGURATION
// ============================================================================

define('SMS_SADR', 'pwd');

// SMS Operator endpoint
// Uncomment and configure the one you need:
// Option 1:
// define('SMS_OP', 'http://95.213.129.83/sendsms.php?user=your_username&pwd=your_password');
// Option 2:
define('SMS_OP', 'http://gateway.api.sc/get/?user=your_username&pwd=your_password');

// ============================================================================
// GOOGLE OAUTH CONFIGURATION
// ============================================================================

// Get these credentials from: https://console.developers.google.com/
define('G_CLIENT_ID', 'your_google_client_id.apps.googleusercontent.com');
define('G_CLIENT_PK', 'your_google_client_secret');

// ============================================================================
// YANDEX OAUTH CONFIGURATION
// ============================================================================

// Get these credentials from: https://oauth.yandex.com/
define('Y_CLIENT_ID', 'your_yandex_client_id');
define('Y_CLIENT_PK', 'your_yandex_client_secret');

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

/**
 * Establish database connection
 * Uses mysqli with proper error handling
 */
$connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME)
    or die("Couldn't connect to database: " . mysqli_connect_error());

// Set charset for proper UTF-8 support
$connection->set_charset(DB_CHARSET);

// ============================================================================
// TABLE VALIDATION
// ============================================================================

/**
 * Validate that the requested table exists
 * Prevents SQL injection and ensures table availability
 *
 * Note: $z variable should be defined before including this config file
 */
if (isset($z) && !mysqli_query($connection, "SELECT 1 FROM $z LIMIT 1") && ($z !== "auth.asp")) {
    header("HTTP/1.0 404 Not found");
    die("$z does not exist");
}

// ============================================================================
// DATABASE SETTINGS (OPTIONAL)
// ============================================================================

/**
 * Set SQL mode if needed
 * Uncomment the line below to enforce strict SQL mode
 */
// mysqli_query($connection, "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");

?>
