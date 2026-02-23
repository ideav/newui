<?php
/**
 * Test script for routing logic
 *
 * This script tests the route detection and template engine functions
 * without requiring a database connection.
 */

// Simulate the functions from index.php
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

    $firstPart = $pathParts[0];

    // API commands
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

    // Database name pattern
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $firstPart)) {
        $action = $pathParts[1] ?? 'info';
        return [
            'type' => 'database',
            'db' => $firstPart,
            'action' => $action,
            'params' => array_slice($pathParts, 2)
        ];
    }

    return [
        'type' => 'static',
        'file' => implode('/', $pathParts),
        'db' => null,
        'action' => null
    ];
}

// Test cases
$testCases = [
    // Static files
    ['path' => '/', 'expected_type' => 'static'],
    ['path' => '/index.html', 'expected_type' => 'static'],
    ['path' => '/terms.html', 'expected_type' => 'static'],

    // Database routes
    ['path' => '/mydb', 'expected_type' => 'database', 'expected_db' => 'mydb', 'expected_action' => 'info'],
    ['path' => '/mydb/edit', 'expected_type' => 'database', 'expected_db' => 'mydb', 'expected_action' => 'edit'],
    ['path' => '/userdb123/settings', 'expected_type' => 'database', 'expected_db' => 'userdb123', 'expected_action' => 'settings'],

    // API routes
    ['path' => '/mydb/xsrf', 'expected_type' => 'api', 'expected_db' => 'mydb', 'expected_action' => 'xsrf'],
    ['path' => '/mydb/_m_new', 'expected_type' => 'api', 'expected_db' => 'mydb', 'expected_action' => '_m_new'],
    ['path' => '/mydb/auth', 'expected_type' => 'api', 'expected_db' => 'mydb', 'expected_action' => 'auth'],
    ['path' => '/testdb/_d_new/123', 'expected_type' => 'api', 'expected_db' => 'testdb', 'expected_action' => '_d_new'],
];

echo "=== Routing Tests ===\n\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    $path = $test['path'];
    $pathParts = array_values(array_filter(explode('/', trim($path, '/'))));

    $result = detectRoute($pathParts);

    $pass = true;
    $errors = [];

    if ($result['type'] !== $test['expected_type']) {
        $pass = false;
        $errors[] = "type: expected '{$test['expected_type']}', got '{$result['type']}'";
    }

    if (isset($test['expected_db']) && $result['db'] !== $test['expected_db']) {
        $pass = false;
        $errors[] = "db: expected '{$test['expected_db']}', got '{$result['db']}'";
    }

    if (isset($test['expected_action']) && $result['action'] !== $test['expected_action']) {
        $pass = false;
        $errors[] = "action: expected '{$test['expected_action']}', got '{$result['action']}'";
    }

    if ($pass) {
        echo "[PASS] $path\n";
        $passed++;
    } else {
        echo "[FAIL] $path\n";
        foreach ($errors as $error) {
            echo "       - $error\n";
        }
        $failed++;
    }
}

echo "\n=== Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

// ============================================================================
// Test Template Engine
// ============================================================================

echo "\n=== Template Engine Tests ===\n\n";

// Include template functions
function Make_tree($text, $curBlock = '') {
    $tree = [];
    $pattern = '/<!--\s*File:\s*(\w+)\s*-->(.*?)(?:<!--\s*End:\s*\1\s*-->|$)/si';

    if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        $lastEnd = 0;

        foreach ($matches as $match) {
            $blockName = $match[1][0];
            $blockContent = $match[2][0];
            $startPos = $match[0][1];

            $textBefore = substr($text, $lastEnd, $startPos - $lastEnd);
            if (!empty(trim($textBefore))) {
                $tree[] = ['type' => 'text', 'content' => $textBefore];
            }

            $subTree = Make_tree($blockContent, $blockName);

            $tree[] = [
                'type' => 'block',
                'name' => $blockName,
                'content' => $blockContent,
                'children' => $subTree
            ];

            $lastEnd = $match[0][1] + strlen($match[0][0]);
        }

        $textAfter = substr($text, $lastEnd);
        if (!empty(trim($textAfter))) {
            $tree[] = ['type' => 'text', 'content' => $textAfter];
        }
    } else {
        if (!empty(trim($text))) {
            $tree[] = ['type' => 'text', 'content' => $text];
        }
    }

    return $tree;
}

// Test template
$template = <<<HTML
<html>
<head>
    <title>Test</title>
</head>
<body>
    <!-- File: header -->
    <header>Header content</header>
    <!-- End: header -->

    <!-- File: content -->
    Main content here
    <!-- End: content -->
</body>
</html>
HTML;

$tree = Make_tree($template, 'main');

echo "Template tree structure:\n";
echo json_encode($tree, JSON_PRETTY_PRINT) . "\n";

// Check the tree
$blockCount = 0;
foreach ($tree as $node) {
    if ($node['type'] === 'block') {
        $blockCount++;
    }
}

if ($blockCount === 2) {
    echo "\n[PASS] Found 2 blocks in template\n";
} else {
    echo "\n[FAIL] Expected 2 blocks, found $blockCount\n";
}

// Test with actual templates directory
$templatesDir = __DIR__ . '/../templates';
if (is_dir($templatesDir)) {
    echo "\n=== Testing Actual Templates ===\n\n";

    $mainTemplate = $templatesDir . '/main.html';
    if (file_exists($mainTemplate)) {
        $content = file_get_contents($mainTemplate);
        $tree = Make_tree($content, 'main');
        echo "main.html tree nodes: " . count($tree) . "\n";

        foreach ($tree as $i => $node) {
            if ($node['type'] === 'block') {
                echo "  Block found: {$node['name']}\n";
            }
        }
    }
}

echo "\n=== All Tests Complete ===\n";

?>
