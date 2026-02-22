<?php
/**
 * XSRF Token and User Status Endpoint
 *
 * Returns XSRF token and current user status
 * This endpoint is called by the frontend to check authentication status
 *
 * Response format:
 * {
 *   "_xsrf": "token",
 *   "token": "auth_token",
 *   "user": "username or guest",
 *   "role": "role",
 *   "id": "user_id",
 *   "msg": ""
 * }
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load dependencies
require_once __DIR__ . '/auth_helpers.php';

try {
    // Start session if not already started
    if (!session_id()) {
        session_start();
    }

    // Get or generate XSRF token
    $xsrfToken = getXsrfToken();

    // Check if user is authenticated
    $user = 'guest';
    $role = 'guest';
    $userId = '';
    $authToken = '';

    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        // User is authenticated
        $userId = $_SESSION['user_id'];
        $authToken = $_SESSION['auth_token'] ?? '';

        // Load user info from database (if needed)
        require_once __DIR__ . '/db_helpers.php';

        try {
            $userData = getUserById($userId);
            if ($userData) {
                $user = $userData['user'] ?? 'guest';
                $role = $userData['role'] ?? 'user';
            }
        } catch (Exception $e) {
            // Database error - user might not exist
            error_log("Error loading user $userId: " . $e->getMessage());
        }
    }

    // Return response
    echo json_encode([
        '_xsrf' => $xsrfToken,
        'token' => $authToken,
        'user' => $user,
        'role' => $role,
        'id' => $userId,
        'msg' => ''
    ]);

} catch (Exception $e) {
    error_log("XSRF endpoint error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        '_xsrf' => '',
        'token' => '',
        'user' => 'guest',
        'role' => 'guest',
        'id' => '',
        'msg' => 'Server error'
    ]);
}

?>
