<?php
/**
 * Database Connection and Configuration
 *
 * This file establishes database connection and provides configuration settings.
 * It should be included by index.php and other modules that need database access.
 *
 * Expected usage:
 *   $z = 'database_name'; // Set before including
 *   require_once __DIR__ . '/connection.php';
 */

// ============================================================================
// CONFIGURATION FILE LOADING
// ============================================================================

// Try to load config.php from parent directory
$configPath = __DIR__ . '/../config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // For development/testing, check for config.example.php
    $exampleConfigPath = __DIR__ . '/../config.example.php';
    if (file_exists($exampleConfigPath)) {
        // In development mode, log a warning but don't die
        error_log('Warning: config.php not found, using config.example.php');
        require_once $exampleConfigPath;
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => 'Configuration file not found. Please create config.php from config.example.php'
        ]);
        exit;
    }
}

// ============================================================================
// DATABASE CONNECTION GLOBALS
// ============================================================================

/**
 * Global database connection object
 * @var mysqli|null
 */
global $connection;

/**
 * Current database/table name being accessed
 * @var string
 */
global $z;

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get the database connection, creating it if necessary
 *
 * @return mysqli The database connection
 */
function getConnection() {
    global $connection;

    if (!$connection) {
        $connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if (!$connection) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => 'Database connection failed'
            ]);
            exit;
        }

        // Set charset for proper UTF-8 support
        $connection->set_charset(DB_CHARSET);
    }

    return $connection;
}

/**
 * Validate that the requested table/database exists
 *
 * @param string $tableName The table name to validate
 * @return bool True if valid, false otherwise
 */
function validateTable($tableName) {
    global $connection;

    // Skip validation for special routes
    if ($tableName === 'auth.asp' || empty($tableName)) {
        return true;
    }

    // Sanitize table name to prevent SQL injection
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $tableName)) {
        return false;
    }

    // Check if table exists
    $result = mysqli_query($connection, "SELECT 1 FROM `$tableName` LIMIT 1");

    return $result !== false;
}

/**
 * Close the database connection
 */
function closeConnection() {
    global $connection;

    if ($connection) {
        mysqli_close($connection);
        $connection = null;
    }
}

// Register shutdown function to close connection
register_shutdown_function('closeConnection');

?>
