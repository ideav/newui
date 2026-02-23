<?php
/**
 * Main Routing Module (index.php)
 *
 * This is the main entry point for the application that handles:
 * 1. Static file serving - Files that exist on disk (e.g., /terms.html, /index.html)
 * 2. User database access - Routes like /{db_name}/{action} that require authentication
 * 3. API methods - JSON responses that also require authentication
 *
 * When a URL is not found (ЧПУ - clean URLs), Apache routes to this file
 * which then determines what action to take based on the path and user context.
 *
 * Configuration is loaded from include/connection.php
 * Authentication supports JWT tokens and session-based auth.
 *
 * @see API_SPECIFICATION.md for API details
 */

// ============================================================================
// ERROR HANDLING AND SESSION
// ============================================================================

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// PARSE REQUEST
// ============================================================================

// Get the request URI and method
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Parse the URI
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';
$queryString = $parsedUrl['query'] ?? '';

// Remove leading slash and split into parts
$pathParts = array_values(array_filter(explode('/', trim($path, '/'))));

// ============================================================================
// STATIC FILE DETECTION
// ============================================================================

/**
 * Check if the request is for a static file that exists on disk
 *
 * @param string $path The request path
 * @return string|false The full file path if exists, false otherwise
 */
function isStaticFile($path) {
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;

    // Normalize path (remove any directory traversal attempts)
    $safePath = str_replace(['../', '..\\'], '', $path);

    // Check common static file extensions
    $staticExtensions = ['html', 'htm', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'txt', 'xml', 'json'];

    $ext = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));

    if (in_array($ext, $staticExtensions)) {
        $fullPath = $documentRoot . '/' . ltrim($safePath, '/');

        if (file_exists($fullPath) && is_file($fullPath)) {
            return $fullPath;
        }
    }

    return false;
}

// ============================================================================
// ROUTE DETECTION
// ============================================================================

/**
 * Determine the type of route
 *
 * @param array $pathParts Path parts array
 * @return array Route information [type, db, action, params]
 */
function detectRoute($pathParts) {
    // Empty path or root - serve index.html
    if (empty($pathParts) || (count($pathParts) === 1 && $pathParts[0] === '')) {
        return [
            'type' => 'static',
            'file' => 'index.html',
            'db' => null,
            'action' => null
        ];
    }

    // First part could be a database name or a static file
    $firstPart = $pathParts[0];

    // API routes - they start with 'api' or contain specific API commands
    $apiCommands = ['_m_new', '_m_save', '_m_set', '_m_del', '_m_move', '_m_up', '_m_ord', '_m_id',
                    '_d_new', '_d_save', '_d_del', '_d_req', '_d_del_req', '_d_ref', '_d_alias',
                    '_d_null', '_d_multi', '_d_attrs', '_d_up', '_d_ord',
                    'xsrf', 'exit', 'terms', 'metadata', 'obj_meta', '_ref_reqs', '_new_db', '_connect',
                    'auth', 'auth.asp'];

    // Check if second part is an API command
    if (count($pathParts) >= 2 && in_array($pathParts[1], $apiCommands)) {
        return [
            'type' => 'api',
            'db' => $pathParts[0],
            'action' => $pathParts[1],
            'params' => array_slice($pathParts, 2)
        ];
    }

    // Check if the path looks like a database access: /{db_name}/{action}
    // Database names should be alphanumeric
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $firstPart)) {
        // This is likely a database access
        $action = $pathParts[1] ?? 'info';  // Default action is 'info'

        return [
            'type' => 'database',
            'db' => $firstPart,
            'action' => $action,
            'params' => array_slice($pathParts, 2)
        ];
    }

    // If nothing matches, treat as static file request (will 404 if not found)
    return [
        'type' => 'static',
        'file' => implode('/', $pathParts),
        'db' => null,
        'action' => null
    ];
}

// ============================================================================
// JWT TOKEN HANDLING
// ============================================================================

/**
 * Decode a JWT token without verification (for role extraction)
 * In production, use a proper JWT library with signature verification
 *
 * @param string $token The JWT token
 * @return array|null Decoded payload or null if invalid
 */
function decodeJwtPayload($token) {
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        return null;
    }

    $payload = $parts[1];

    // Base64 URL decode
    $payload = str_replace(['-', '_'], ['+', '/'], $payload);
    $payload = base64_decode($payload);

    if ($payload === false) {
        return null;
    }

    $decoded = json_decode($payload, true);

    return $decoded;
}

/**
 * Extract user role from JWT token
 *
 * @param string $token JWT token
 * @return string|null User role or null
 */
function getRoleFromJwt($token) {
    $payload = decodeJwtPayload($token);

    if ($payload && isset($payload['role'])) {
        return $payload['role'];
    }

    return null;
}

/**
 * Check if JWT token is expired
 *
 * @param string $token JWT token
 * @return bool True if expired or invalid
 */
function isJwtExpired($token) {
    $payload = decodeJwtPayload($token);

    if (!$payload || !isset($payload['exp'])) {
        return true;
    }

    return $payload['exp'] < time();
}

// ============================================================================
// AUTHENTICATION
// ============================================================================

/**
 * Check if user is authenticated
 *
 * @return array Authentication result [authenticated, user_id, role, method]
 */
function checkAuthentication() {
    global $connection, $z;

    // Check for JWT token in Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $token = $matches[1];

        if (!isJwtExpired($token)) {
            $payload = decodeJwtPayload($token);

            if ($payload) {
                return [
                    'authenticated' => true,
                    'user_id' => $payload['sub'] ?? $payload['user_id'] ?? null,
                    'role' => $payload['role'] ?? 'user',
                    'method' => 'jwt'
                ];
            }
        }
    }

    // Check for token in cookie
    if (isset($_COOKIE[$z]) && !empty($_COOKIE[$z])) {
        $cookieToken = $_COOKIE[$z];

        // Validate token against database
        if ($connection && $z) {
            $tokenEsc = mysqli_real_escape_string($connection, $cookieToken);
            $result = mysqli_query($connection, "SELECT u.id, u.val as user FROM `$z` u
                                                  JOIN `$z` t ON t.up = u.id AND t.t = 125
                                                  WHERE t.val = '$tokenEsc' LIMIT 1");

            if ($result && $row = mysqli_fetch_assoc($result)) {
                return [
                    'authenticated' => true,
                    'user_id' => $row['id'],
                    'role' => 'user',
                    'method' => 'cookie'
                ];
            }
        }
    }

    // Check session
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return [
            'authenticated' => true,
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'user',
            'method' => 'session'
        ];
    }

    return [
        'authenticated' => false,
        'user_id' => null,
        'role' => 'guest',
        'method' => null
    ];
}

/**
 * Redirect to login page
 *
 * @param string $db Database name
 * @param string $returnUrl URL to return to after login
 */
function redirectToLogin($db, $returnUrl = '') {
    $loginUrl = '/login.html';

    if (!empty($db)) {
        $loginUrl .= '?db=' . urlencode($db);
    }

    if (!empty($returnUrl)) {
        $loginUrl .= (strpos($loginUrl, '?') !== false ? '&' : '?') . 'return=' . urlencode($returnUrl);
    }

    header('Location: ' . $loginUrl);
    exit;
}

/**
 * Return JSON authentication error
 *
 * @param string $message Error message
 */
function jsonAuthError($message = 'Authentication required') {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'authenticated' => false,
        'message' => $message,
        'redirect' => '/login.html'
    ]);
    exit;
}

// ============================================================================
// TEMPLATE ENGINE
// ============================================================================

/**
 * Build a tree structure of blocks from template text
 *
 * Template blocks are defined as:
 * <!-- File: block_name -->
 * ... block content ...
 * <!-- End: block_name -->
 *
 * @param string $text Template text
 * @param string $curBlock Current block name (for recursion)
 * @return array Tree structure of blocks
 */
function Make_tree($text, $curBlock = '') {
    $tree = [];

    // Pattern to match block definitions: <!-- File: name --> ... content ... <!-- End: name -->
    // Also support simpler format: <!-- File: name --> for includes
    $pattern = '/<!--\s*File:\s*(\w+)\s*-->(.*?)(?:<!--\s*End:\s*\1\s*-->|$)/si';

    if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        $lastEnd = 0;

        foreach ($matches as $match) {
            $blockName = $match[1][0];
            $blockContent = $match[2][0];
            $startPos = $match[0][1];

            // Add text before this block
            $textBefore = substr($text, $lastEnd, $startPos - $lastEnd);
            if (!empty(trim($textBefore))) {
                $tree[] = ['type' => 'text', 'content' => $textBefore];
            }

            // Process block content recursively
            $subTree = Make_tree($blockContent, $blockName);

            $tree[] = [
                'type' => 'block',
                'name' => $blockName,
                'content' => $blockContent,
                'children' => $subTree
            ];

            $lastEnd = $match[0][1] + strlen($match[0][0]);
        }

        // Add remaining text after last block
        $textAfter = substr($text, $lastEnd);
        if (!empty(trim($textAfter))) {
            $tree[] = ['type' => 'text', 'content' => $textAfter];
        }
    } else {
        // No blocks found, treat entire text as content
        if (!empty(trim($text))) {
            $tree[] = ['type' => 'text', 'content' => $text];
        }
    }

    return $tree;
}

/**
 * Parse and render a block, replacing variables and including sub-templates
 *
 * Variables are in format: {variable_name} or {{variable_name}}
 * Includes are in format: <!-- File: template_name -->
 *
 * @param array $block Block structure from Make_tree
 * @param array $data Data for variable substitution
 * @param string $templatesDir Directory containing templates
 * @return string Rendered content
 */
function Parse_block($block, $data = [], $templatesDir = null) {
    if ($templatesDir === null) {
        $templatesDir = __DIR__ . '/templates';
    }

    $output = '';

    if ($block['type'] === 'text') {
        $output = $block['content'];
    } elseif ($block['type'] === 'block') {
        // Check if this is an include (block with just a name reference)
        $blockName = $block['name'];
        $templateFile = $templatesDir . '/' . $blockName . '.html';

        if (file_exists($templateFile)) {
            // Load and process the template file
            $templateContent = file_get_contents($templateFile);
            $subTree = Make_tree($templateContent, $blockName);

            foreach ($subTree as $subBlock) {
                $output .= Parse_block($subBlock, $data, $templatesDir);
            }
        } else {
            // Process the block content directly
            if (!empty($block['children'])) {
                foreach ($block['children'] as $child) {
                    $output .= Parse_block($child, $data, $templatesDir);
                }
            } else {
                $output = $block['content'];
            }
        }
    }

    // Replace variables
    $output = replaceVariables($output, $data);

    return $output;
}

/**
 * Replace template variables with data values
 *
 * @param string $content Template content
 * @param array $data Data array
 * @return string Content with variables replaced
 */
function replaceVariables($content, $data) {
    // Replace {{variable}} format
    $content = preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
        $key = $matches[1];
        return isset($data[$key]) ? htmlspecialchars($data[$key], ENT_QUOTES, 'UTF-8') : '';
    }, $content);

    // Replace {variable} format
    $content = preg_replace_callback('/\{(\w+)\}/', function($matches) use ($data) {
        $key = $matches[1];
        return isset($data[$key]) ? htmlspecialchars($data[$key], ENT_QUOTES, 'UTF-8') : '';
    }, $content);

    return $content;
}

/**
 * Render a complete template with the main layout
 *
 * @param string $action The action/page to render
 * @param array $data Data for the template
 * @return string Rendered HTML
 */
function renderTemplate($action, $data = []) {
    $templatesDir = __DIR__ . '/templates';

    // Load main template
    $mainTemplate = $templatesDir . '/main.html';

    if (!file_exists($mainTemplate)) {
        return '<h1>Template not found</h1><p>Main template is missing.</p>';
    }

    $mainContent = file_get_contents($mainTemplate);

    // Build the tree from main template
    $tree = Make_tree($mainContent, 'main');

    // Load the action-specific template
    $actionTemplate = $templatesDir . '/' . $action . '.html';
    $actionContent = '';

    if (file_exists($actionTemplate)) {
        $actionContent = file_get_contents($actionTemplate);
    }

    // Add action content to data
    $data['content'] = $actionContent;
    $data['action'] = $action;

    // Parse and render the tree
    $output = '';
    foreach ($tree as $block) {
        $output .= Parse_block($block, $data, $templatesDir);
    }

    // Replace the main content placeholder with action content
    // Look for <!-- File: a --> or similar markers
    $output = preg_replace('/<!--\s*File:\s*\w+\s*-->\s*/', $actionContent, $output);

    return $output;
}

// ============================================================================
// RESPONSE HELPERS
// ============================================================================

/**
 * Send JSON response
 *
 * @param mixed $data Data to encode
 * @param int $statusCode HTTP status code
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send HTML response
 *
 * @param string $html HTML content
 * @param int $statusCode HTTP status code
 */
function htmlResponse($html, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

/**
 * Send 404 Not Found response
 *
 * @param bool $json Whether to return JSON
 */
function notFoundResponse($json = false) {
    if ($json) {
        jsonResponse(['error' => true, 'message' => 'Not found'], 404);
    } else {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested resource was not found.</p></body></html>';
        exit;
    }
}

// ============================================================================
// MAIN ROUTING LOGIC
// ============================================================================

// Detect route type
$route = detectRoute($pathParts);

// Handle static files
if ($route['type'] === 'static') {
    $staticFile = isStaticFile($path);

    if ($staticFile) {
        // Let Apache serve the file (should not reach here with proper .htaccess)
        $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
        $mimeTypes = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'xml' => 'application/xml'
        ];

        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($staticFile);
        exit;
    }

    // Check for index.html as default
    if (empty($pathParts) || $route['file'] === 'index.html') {
        $indexFile = __DIR__ . '/index.html';
        if (file_exists($indexFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($indexFile);
            exit;
        }
    }

    notFoundResponse();
}

// ============================================================================
// DATABASE AND API ROUTES - REQUIRE AUTHENTICATION
// ============================================================================

// Set the database/table name globally
$z = $route['db'];

// Load database connection
require_once __DIR__ . '/include/connection.php';

// Get database connection
$connection = getConnection();

// Validate table exists (skip for auth routes)
$authRoutes = ['auth', 'auth.asp', 'xsrf'];
if (!in_array($route['action'], $authRoutes) && !validateTable($z)) {
    notFoundResponse($route['type'] === 'api');
}

// Check authentication
$auth = checkAuthentication();

// Handle API routes
if ($route['type'] === 'api') {
    // Some API routes don't require authentication
    $publicApiRoutes = ['xsrf', 'auth', 'auth.asp'];

    if (!in_array($route['action'], $publicApiRoutes) && !$auth['authenticated']) {
        jsonAuthError();
    }

    // Include and use the API commands module
    require_once __DIR__ . '/api/commands.php';

    // Create API router
    $router = new ApiRouter($connection, $z);

    if ($auth['authenticated']) {
        $router->setUser($auth['user_id'], $auth['role']);
    }

    // Handle the request
    $router->handleRequest();
    exit;
}

// Handle database routes (template-based pages)
if ($route['type'] === 'database') {
    // Database routes require authentication
    if (!$auth['authenticated']) {
        // Check if this is a JSON request
        $wantsJson = (isset($_GET['JSON']) || isset($_GET['json']) ||
                      (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false));

        if ($wantsJson) {
            jsonAuthError();
        } else {
            redirectToLogin($z, $requestUri);
        }
    }

    // Prepare template data
    $templateData = [
        'db' => $z,
        'action' => $route['action'],
        'user_id' => $auth['user_id'],
        'user_role' => $auth['role'],
        'params' => $route['params']
    ];

    // Load user info from database if available
    if ($auth['user_id'] && $connection) {
        require_once __DIR__ . '/api/db_helpers.php';

        try {
            $userInfo = getUserById($auth['user_id']);
            if ($userInfo) {
                $templateData['user_email'] = $userInfo['email'] ?? $userInfo['user'] ?? '';
                $templateData['user_name'] = $userInfo['user'] ?? '';
            }
        } catch (Exception $e) {
            // Log but continue
            error_log('Error loading user info: ' . $e->getMessage());
        }
    }

    // Render the template
    $html = renderTemplate($route['action'], $templateData);

    htmlResponse($html);
}

// Fallback - should not reach here
notFoundResponse();

?>
