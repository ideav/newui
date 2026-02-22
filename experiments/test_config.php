<?php
/**
 * Test Configuration File Loading
 *
 * This script tests that config.php loads correctly and all constants are defined.
 * Run: php experiments/test_config.php
 */

echo "=== Configuration Test ===\n\n";

// Test 1: Load config file
echo "Test 1: Loading config.php...\n";
try {
    require_once __DIR__ . '/../config.php';
    echo "✓ Config file loaded successfully\n\n";
} catch (Exception $e) {
    echo "✗ Failed to load config: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check database constants
echo "Test 2: Checking database constants...\n";
$db_constants = ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_NAME', 'DB_CHARSET'];
foreach ($db_constants as $const) {
    if (defined($const)) {
        echo "✓ $const is defined\n";
    } else {
        echo "✗ $const is NOT defined\n";
    }
}
echo "\n";

// Test 3: Check mail config
echo "Test 3: Checking mail configuration...\n";
global $mail_config;
if (isset($mail_config) && is_array($mail_config)) {
    echo "✓ \$mail_config is defined as array\n";
    $required_keys = ['smtp_username', 'smtp_port', 'smtp_host', 'smtp_password', 'smtp_debug', 'smtp_charset', 'smtp_from'];
    foreach ($required_keys as $key) {
        if (array_key_exists($key, $mail_config)) {
            echo "✓ mail_config['$key'] is defined\n";
        } else {
            echo "✗ mail_config['$key'] is NOT defined\n";
        }
    }
} else {
    echo "✗ \$mail_config is NOT defined or not an array\n";
}
echo "\n";

// Test 4: Check security constants
echo "Test 4: Checking security constants...\n";
$security_constants = ['SALT', 'ADMINHASH', 'ADMINEMAIL'];
foreach ($security_constants as $const) {
    if (defined($const)) {
        echo "✓ $const is defined\n";
    } else {
        echo "✗ $const is NOT defined\n";
    }
}
echo "\n";

// Test 5: Check OAuth constants
echo "Test 5: Checking OAuth constants...\n";
$oauth_constants = ['G_CLIENT_ID', 'G_CLIENT_PK', 'Y_CLIENT_ID', 'Y_CLIENT_PK'];
foreach ($oauth_constants as $const) {
    if (defined($const)) {
        echo "✓ $const is defined\n";
    } else {
        echo "✗ $const is NOT defined\n";
    }
}
echo "\n";

// Test 6: Check SMS constants
echo "Test 6: Checking SMS constants...\n";
$sms_constants = ['SMS_SADR', 'SMS_OP'];
foreach ($sms_constants as $const) {
    if (defined($const)) {
        echo "✓ $const is defined\n";
    } else {
        echo "✗ $const is NOT defined\n";
    }
}
echo "\n";

// Test 7: Check other constants
echo "Test 7: Checking other constants...\n";
if (defined('TEMPLATES')) {
    echo "✓ TEMPLATES is defined: " . TEMPLATES . "\n";
} else {
    echo "✗ TEMPLATES is NOT defined\n";
}
echo "\n";

// Test 8: Check database connection
echo "Test 8: Checking database connection...\n";
if (isset($connection) && $connection instanceof mysqli) {
    echo "✓ Database connection object exists\n";
    if (mysqli_ping($connection)) {
        echo "✓ Database connection is active\n";
    } else {
        echo "✗ Database connection is not active: " . mysqli_error($connection) . "\n";
    }
} else {
    echo "✗ Database connection object not found or invalid\n";
}
echo "\n";

echo "=== Test Complete ===\n";
?>
