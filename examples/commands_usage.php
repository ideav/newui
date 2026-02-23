<?php
/**
 * Example: How to Use the API Commands Module
 *
 * This file demonstrates the proper way to include and use the commands.php module
 * for implementing API endpoints in your application.
 *
 * The module supports both legacy command names (_m_*, _d_*) and
 * recommended RESTful naming conventions.
 */

// Include the commands module
require_once __DIR__ . '/../api/commands.php';

/**
 * Example 1: Basic Usage with Legacy Command Names
 */
function example_legacy_commands() {
    global $connection, $z;

    // Create API instance
    $api = new ApiCommands($connection, $z);

    // Set current user (for permission checks)
    $api->setUser(1, 'admin');

    // Example: Create a new object
    $result = $api->execute('_m_new', [
        'type_id' => 18,  // User type
        'up' => 1,        // Parent ID
        'val' => 'newuser@example.com'
    ]);
    echo "Created object: " . json_encode($result) . "\n";

    // Example: Update an attribute
    $result = $api->execute('_m_set', [
        'id' => 123,
        't41' => 'updated@example.com'  // Set email attribute
    ]);
    echo "Updated: " . json_encode($result) . "\n";

    // Example: Get object metadata
    $result = $api->execute('obj_meta', ['id' => 123]);
    echo "Metadata: " . json_encode($result) . "\n";
}

/**
 * Example 2: Using Recommended RESTful Names
 */
function example_restful_commands() {
    global $connection, $z;

    $api = new ApiCommands($connection, $z);
    $api->setUser(1, 'admin');

    // Example: Create object using RESTful name
    $result = $api->execute('objects', [
        'type_id' => 18,
        'up' => 1,
        'val' => 'user@example.com'
    ]);

    // Example: Get types list
    $result = $api->execute('types/list', []);
    echo "Types: " . json_encode($result) . "\n";

    // Example: Get XSRF token
    $result = $api->execute('auth/token', []);
    echo "Auth: " . json_encode($result) . "\n";
}

/**
 * Example 3: Using the Router for HTTP Requests
 */
function example_router() {
    global $connection, $z;

    // Create router
    $router = new ApiRouter($connection, $z);

    // Set current user from session
    if (isset($_SESSION['user_id'])) {
        $router->setUser($_SESSION['user_id'], $_SESSION['role'] ?? 'user');
    }

    // Handle incoming request (parses URL and executes command)
    $router->handleRequest();
}

/**
 * Example 4: Working with DDL Commands (Type Management)
 */
function example_ddl_commands() {
    global $connection, $z;

    $api = new ApiCommands($connection, $z);
    $api->setUser(1, 'admin');

    // Create a new type
    $result = $api->execute('_d_new', [
        'val' => 'Customer',
        't' => 3,         // SHORT base type
        'unique' => 1     // Values must be unique
    ]);
    $typeId = $result['id'];
    echo "Created type: $typeId\n";

    // Add an attribute to the type
    $result = $api->execute('_d_req', [
        'id' => $typeId,
        't' => 41         // EMAIL attribute type
    ]);
    echo "Added attribute: " . json_encode($result) . "\n";

    // Set attribute alias
    $result = $api->execute('_d_alias', [
        'id' => $result['id'],
        'val' => 'Email Address'
    ]);
    echo "Set alias: " . json_encode($result) . "\n";
}

/**
 * Example 5: Error Handling
 */
function example_error_handling() {
    global $connection, $z;

    $api = new ApiCommands($connection, $z);
    $api->setUser(1, 'user');

    try {
        // This will throw an error - object not found
        $result = $api->execute('_m_del', ['id' => 999999]);
    } catch (ApiError $e) {
        echo "API Error: " . $e->getMessage() . "\n";
        echo "HTTP Code: " . $e->getHttpCode() . "\n";
        echo "Response: " . json_encode($e->toArray()) . "\n";
    }
}

/**
 * Example 6: Getting Available Commands
 */
function example_list_commands() {
    $commands = ApiCommands::getAvailableCommands();

    echo "=== Available API Commands ===\n\n";

    foreach ($commands as $category => $cmds) {
        echo strtoupper($category) . " Commands:\n";
        foreach ($cmds as $name => $description) {
            echo "  $name - $description\n";
        }
        echo "\n";
    }
}

/**
 * Example 7: Integrating into Existing API Endpoint
 */
function example_endpoint_integration() {
    // This shows how to integrate into an existing endpoint like api/register.php

    global $connection, $z;

    // Typical endpoint setup
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    // Create API instance
    $api = new ApiCommands($connection, $z);

    // Get command from URL or POST
    $command = $_GET['cmd'] ?? $_POST['cmd'] ?? '';
    $params = array_merge($_GET, $_POST);
    unset($params['cmd']);

    try {
        $result = $api->execute($command, $params);
        echo json_encode($result);
    } catch (ApiError $e) {
        http_response_code($e->getHttpCode());
        echo json_encode($e->toArray());
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}

// Run list commands example to show available commands
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    echo "=== API Commands Usage Examples ===\n\n";
    echo "This file demonstrates how to use the commands.php module.\n";
    echo "See the source code for implementation examples.\n\n";
    example_list_commands();
}
