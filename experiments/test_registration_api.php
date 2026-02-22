<?php
/**
 * Test Registration API
 *
 * This script tests the registration API endpoints
 * Run: php experiments/test_registration_api.php
 */

echo "=== Registration API Test ===\n\n";

// Test 1: Check if API files exist
echo "Test 1: Checking API files...\n";
$api_files = [
    'api/db_helpers.php',
    'api/email_helpers.php',
    'api/auth_helpers.php',
    'api/register.php',
    'api/confirm.php',
    'api/xsrf.php'
];

foreach ($api_files as $file) {
    if (file_exists(__DIR__ . '/../' . $file)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file NOT found\n";
    }
}
echo "\n";

// Test 2: Load helper files
echo "Test 2: Loading helper files...\n";
try {
    require_once __DIR__ . '/../api/db_helpers.php';
    echo "✓ db_helpers.php loaded\n";
} catch (Exception $e) {
    echo "✗ Failed to load db_helpers.php: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../api/email_helpers.php';
    echo "✓ email_helpers.php loaded\n";
} catch (Exception $e) {
    echo "✗ Failed to load email_helpers.php: " . $e->getMessage() . "\n";
}

try {
    require_once __DIR__ . '/../api/auth_helpers.php';
    echo "✓ auth_helpers.php loaded\n";
} catch (Exception $e) {
    echo "✗ Failed to load auth_helpers.php: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Test utility functions
echo "Test 3: Testing utility functions...\n";

// Test t9n function
$test_text = "[RU]Привет[EN]Hello";
$ru_result = t9n($test_text, 'RU');
$en_result = t9n($test_text, 'EN');

if ($ru_result === 'Привет') {
    echo "✓ t9n(RU) works correctly\n";
} else {
    echo "✗ t9n(RU) failed: got '$ru_result'\n";
}

if ($en_result === 'Hello') {
    echo "✓ t9n(EN) works correctly\n";
} else {
    echo "✗ t9n(EN) failed: got '$en_result'\n";
}

// Test mail2DB function
$db_name = mail2DB('test@example.com', 123);
if (strlen($db_name) > 0 && preg_match('/^[a-z][a-z0-9]*$/', $db_name)) {
    echo "✓ mail2DB works: '$db_name'\n";
} else {
    echo "✗ mail2DB failed: got '$db_name'\n";
}

// Test Salt function
$hashed = Salt('user@example.com', 'password123');
if (strlen($hashed) > 20) {
    echo "✓ Salt function works\n";
} else {
    echo "✗ Salt function failed\n";
}

// Test generateToken function
$token = generateToken(32);
if (strlen($token) === 32 && ctype_xdigit($token)) {
    echo "✓ generateToken works\n";
} else {
    echo "✗ generateToken failed: got '$token'\n";
}
echo "\n";

// Test 4: Test XSRF token generation
echo "Test 4: Testing XSRF token...\n";
$xsrf_token = getXsrfToken();
if (strlen($xsrf_token) > 0) {
    echo "✓ XSRF token generated: " . substr($xsrf_token, 0, 16) . "...\n";
} else {
    echo "✗ XSRF token generation failed\n";
}

$xsrf_token2 = getXsrfToken();
if ($xsrf_token === $xsrf_token2) {
    echo "✓ XSRF token is consistent\n";
} else {
    echo "✗ XSRF token is not consistent\n";
}
echo "\n";

// Test 5: Test database connection (if config exists)
echo "Test 5: Testing database connection...\n";
if (isset($connection) && $connection instanceof mysqli) {
    if (mysqli_ping($connection)) {
        echo "✓ Database connection is active\n";

        // Test if required constants are defined
        if (defined('USER') && defined('EMAIL') && defined('PASSWORD') && defined('TOKEN')) {
            echo "✓ Required constants are defined\n";
        } else {
            echo "⚠ Some constants may not be defined (this is OK if not using CRM tables)\n";
        }
    } else {
        echo "✗ Database connection is not active\n";
    }
} else {
    echo "⚠ Database connection not available (config.php may not exist)\n";
}
echo "\n";

// Test 6: Simulate registration validation
echo "Test 6: Testing registration validation logic...\n";

// Test email validation
$valid_email = "test@example.com";
$invalid_email = "notanemail";

if (filter_var($valid_email, FILTER_VALIDATE_EMAIL)) {
    echo "✓ Valid email passes validation\n";
} else {
    echo "✗ Valid email fails validation\n";
}

if (!filter_var($invalid_email, FILTER_VALIDATE_EMAIL)) {
    echo "✓ Invalid email fails validation (as expected)\n";
} else {
    echo "✗ Invalid email passes validation (unexpected)\n";
}

// Test password length validation
$valid_password = "password123";
$short_password = "123";

if (strlen($valid_password) >= 6) {
    echo "✓ Valid password passes length check\n";
} else {
    echo "✗ Valid password fails length check\n";
}

if (strlen($short_password) < 6) {
    echo "✓ Short password fails length check (as expected)\n";
} else {
    echo "✗ Short password passes length check (unexpected)\n";
}
echo "\n";

// Summary
echo "=== Test Complete ===\n\n";
echo "Note: This is a basic functionality test.\n";
echo "For full integration testing, you need to:\n";
echo "1. Create config.php from config.example.php\n";
echo "2. Set up your database\n";
echo "3. Configure SMTP settings\n";
echo "4. Test the actual HTTP endpoints using curl or a browser\n";

?>
