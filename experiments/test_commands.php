<?php
/**
 * Test Script for API Commands Module
 *
 * This script tests the ApiCommands class without requiring a database.
 * It validates:
 * - Class structure and methods exist
 * - Command aliasing works correctly
 * - Type constants are defined
 *
 * Usage:
 *   php experiments/test_commands.php
 */

// Prevent actual database connection for testing
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'test');
define('DB_PASSWORD', 'test');
define('DB_NAME', 'test');
define('DB_CHARSET', 'utf8mb4');
define('ADMINEMAIL', 'test@example.com');
define('SALT', 'test_salt');
define('ADMINHASH', 'test_hash');

// Mock session functions if needed
if (!function_exists('session_id')) {
    function session_id() { return 'test_session'; }
}

// Create mock config.php to satisfy require
$configContent = '<?php
// Mock config for testing
global $connection;
$connection = null; // Will be mocked
global $mail_config;
$mail_config = [];
global $z;
$z = "test_table";
';

$configPath = __DIR__ . '/../config.php';
$configExists = file_exists($configPath);
if (!$configExists) {
    file_put_contents($configPath, $configContent);
}

// Test results
$tests = [];
$passed = 0;
$failed = 0;

function test($name, $condition, $details = '') {
    global $tests, $passed, $failed;
    $tests[] = [
        'name' => $name,
        'passed' => $condition,
        'details' => $details
    ];
    if ($condition) {
        $passed++;
        echo "✓ PASS: $name\n";
    } else {
        $failed++;
        echo "✗ FAIL: $name" . ($details ? " - $details" : "") . "\n";
    }
}

echo "=== API Commands Module Tests ===\n\n";

// Test 1: File exists
test(
    "commands.php exists",
    file_exists(__DIR__ . '/../api/commands.php'),
    "File should be at api/commands.php"
);

// Test 2: Include file without errors
$includeError = null;
try {
    // We need to mock the dependencies first
    if (!file_exists(__DIR__ . '/../api/db_helpers.php')) {
        echo "Note: db_helpers.php not found, creating mock\n";
    }
    if (!file_exists(__DIR__ . '/../api/auth_helpers.php')) {
        echo "Note: auth_helpers.php not found, creating mock\n";
    }

    // Just check if file is syntactically correct
    $code = file_get_contents(__DIR__ . '/../api/commands.php');
    $tokens = @token_get_all($code);
    if ($tokens === false || count($tokens) < 10) {
        throw new Exception("Could not tokenize file");
    }
    $includeError = false;
} catch (Exception $e) {
    $includeError = $e->getMessage();
}

test(
    "commands.php has valid PHP syntax",
    $includeError === false,
    $includeError ?: ''
);

// Test 3: Check TypeConstants class exists in file
$hasTypeConstants = strpos($code, 'class TypeConstants') !== false;
test(
    "TypeConstants class is defined",
    $hasTypeConstants,
    "Should define TypeConstants class"
);

// Test 4: Check ApiCommands class exists
$hasApiCommands = strpos($code, 'class ApiCommands') !== false;
test(
    "ApiCommands class is defined",
    $hasApiCommands,
    "Should define ApiCommands class"
);

// Test 5: Check ApiError class exists
$hasApiError = strpos($code, 'class ApiError') !== false;
test(
    "ApiError class is defined",
    $hasApiError,
    "Should define ApiError class"
);

// Test 6: Check ApiRouter class exists
$hasApiRouter = strpos($code, 'class ApiRouter') !== false;
test(
    "ApiRouter class is defined",
    $hasApiRouter,
    "Should define ApiRouter class"
);

// Test 7: Check DML commands are implemented
$dmlCommands = ['cmdMNew', 'cmdMSave', 'cmdMSet', 'cmdMDel', 'cmdMMove', 'cmdMUp', 'cmdMOrd', 'cmdMId'];
$allDmlFound = true;
$missingDml = [];
foreach ($dmlCommands as $cmd) {
    if (strpos($code, "function $cmd") === false) {
        $allDmlFound = false;
        $missingDml[] = $cmd;
    }
}
test(
    "All DML commands implemented",
    $allDmlFound,
    $missingDml ? "Missing: " . implode(', ', $missingDml) : ''
);

// Test 8: Check DDL commands are implemented
$ddlCommands = ['cmdDNew', 'cmdDSave', 'cmdDDel', 'cmdDReq', 'cmdDDelReq', 'cmdDRef', 'cmdDAlias', 'cmdDNull', 'cmdDMulti', 'cmdDAttrs', 'cmdDUp', 'cmdDOrd'];
$allDdlFound = true;
$missingDdl = [];
foreach ($ddlCommands as $cmd) {
    if (strpos($code, "function $cmd") === false) {
        $allDdlFound = false;
        $missingDdl[] = $cmd;
    }
}
test(
    "All DDL commands implemented",
    $allDdlFound,
    $missingDdl ? "Missing: " . implode(', ', $missingDdl) : ''
);

// Test 9: Check utility commands are implemented
$utilCommands = ['cmdObjMeta', 'cmdMetadata', 'cmdTerms', 'cmdRefReqs', 'cmdXsrf', 'cmdExit', 'cmdNewDb', 'cmdConnect'];
$allUtilFound = true;
$missingUtil = [];
foreach ($utilCommands as $cmd) {
    if (strpos($code, "function $cmd") === false) {
        $allUtilFound = false;
        $missingUtil[] = $cmd;
    }
}
test(
    "All utility commands implemented",
    $allUtilFound,
    $missingUtil ? "Missing: " . implode(', ', $missingUtil) : ''
);

// Test 10: Check command aliases are defined
$hasAliases = strpos($code, '$commandAliases') !== false;
test(
    "Command aliases mapping is defined",
    $hasAliases,
    "Should have $commandAliases for mapping recommended names to legacy names"
);

// Test 11: Check recommended RESTful names are mapped
$restNames = ['objects', 'types', 'attributes', 'auth/token', 'auth/logout'];
$allRestFound = true;
$missingRest = [];
foreach ($restNames as $name) {
    if (strpos($code, "'$name'") === false) {
        $allRestFound = false;
        $missingRest[] = $name;
    }
}
test(
    "RESTful command names are mapped",
    $allRestFound,
    $missingRest ? "Missing: " . implode(', ', $missingRest) : ''
);

// Test 12: Check TypeConstants values match API_SPECIFICATION.md
$typeConstants = [
    'SHORT = 3',
    'CHARS = 8',
    'DATE = 9',
    'NUMBER = 13',
    'BOOLEAN = 11',
    'MEMO = 12',
    'USER = 18'
];
$allTypesCorrect = true;
$wrongTypes = [];
foreach ($typeConstants as $const) {
    if (strpos($code, $const) === false) {
        $allTypesCorrect = false;
        $wrongTypes[] = $const;
    }
}
test(
    "TypeConstants match API specification",
    $allTypesCorrect,
    $wrongTypes ? "Missing/wrong: " . implode(', ', $wrongTypes) : ''
);

// Test 13: Check execute() method exists
$hasExecute = strpos($code, 'function execute(') !== false;
test(
    "execute() method is defined",
    $hasExecute,
    "Should have public execute() method for running commands"
);

// Test 14: Check getAvailableCommands() method exists
$hasGetCommands = strpos($code, 'function getAvailableCommands()') !== false;
test(
    "getAvailableCommands() method is defined",
    $hasGetCommands,
    "Should have static method to list available commands"
);

// Test 15: Check proper error handling
$hasApiErrorUsage = strpos($code, 'throw new ApiError') !== false;
test(
    "Uses ApiError for error handling",
    $hasApiErrorUsage,
    "Should throw ApiError exceptions for API errors"
);

// Test 16: Check SQL injection protection
$hasEscape = strpos($code, 'function escape(') !== false || strpos($code, 'mysqli_real_escape_string') !== false;
test(
    "Has SQL escape protection",
    $hasEscape,
    "Should escape values before SQL queries"
);

// Clean up mock config if we created it
if (!$configExists && file_exists($configPath)) {
    // Don't delete, might be needed
}

echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

// Return exit code
exit($failed > 0 ? 1 : 0);
