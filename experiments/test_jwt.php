<?php
/**
 * Test script for JWT token handling
 *
 * Tests the JWT decoding functions from index.php
 */

// JWT decode functions from index.php
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

function getRoleFromJwt($token) {
    $payload = decodeJwtPayload($token);

    if ($payload && isset($payload['role'])) {
        return $payload['role'];
    }

    return null;
}

function isJwtExpired($token) {
    $payload = decodeJwtPayload($token);

    if (!$payload || !isset($payload['exp'])) {
        return true;
    }

    return $payload['exp'] < time();
}

// Create a test JWT token (for testing purposes only)
function createTestJwt($payload, $secret = 'test-secret') {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $header = str_replace(['+', '/', '='], ['-', '_', ''], $header);

    $payloadEncoded = base64_encode(json_encode($payload));
    $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], $payloadEncoded);

    $signature = base64_encode(hash_hmac('sha256', "$header.$payloadEncoded", $secret, true));
    $signature = str_replace(['+', '/', '='], ['-', '_', ''], $signature);

    return "$header.$payloadEncoded.$signature";
}

echo "=== JWT Token Tests ===\n\n";

// Test 1: Valid token with future expiration
$validPayload = [
    'sub' => '12345',
    'user_id' => 'user123',
    'role' => 'admin',
    'exp' => time() + 3600 // 1 hour in the future
];
$validToken = createTestJwt($validPayload);

echo "Test 1: Valid token with future expiration\n";
echo "Token: " . substr($validToken, 0, 50) . "...\n";

$decoded = decodeJwtPayload($validToken);
if ($decoded) {
    echo "[PASS] Token decoded successfully\n";
    echo "  - sub: {$decoded['sub']}\n";
    echo "  - role: {$decoded['role']}\n";
} else {
    echo "[FAIL] Failed to decode token\n";
}

$role = getRoleFromJwt($validToken);
if ($role === 'admin') {
    echo "[PASS] Role extracted correctly: $role\n";
} else {
    echo "[FAIL] Expected role 'admin', got '$role'\n";
}

$expired = isJwtExpired($validToken);
if (!$expired) {
    echo "[PASS] Token not expired (as expected)\n";
} else {
    echo "[FAIL] Token incorrectly marked as expired\n";
}

echo "\n";

// Test 2: Expired token
$expiredPayload = [
    'sub' => '67890',
    'role' => 'user',
    'exp' => time() - 3600 // 1 hour in the past
];
$expiredToken = createTestJwt($expiredPayload);

echo "Test 2: Expired token\n";
$expired = isJwtExpired($expiredToken);
if ($expired) {
    echo "[PASS] Expired token detected correctly\n";
} else {
    echo "[FAIL] Expired token not detected\n";
}

echo "\n";

// Test 3: Invalid token
echo "Test 3: Invalid token format\n";
$invalidToken = "not.a.valid.jwt.token";
$decoded = decodeJwtPayload($invalidToken);
if ($decoded === null) {
    echo "[PASS] Invalid token rejected\n";
} else {
    echo "[FAIL] Invalid token should have been rejected\n";
}

echo "\n";

// Test 4: Token without expiration
echo "Test 4: Token without expiration\n";
$noExpPayload = [
    'sub' => '11111',
    'role' => 'guest'
];
$noExpToken = createTestJwt($noExpPayload);
$expired = isJwtExpired($noExpToken);
if ($expired) {
    echo "[PASS] Token without exp is treated as expired (secure default)\n";
} else {
    echo "[FAIL] Token without exp should be treated as expired\n";
}

echo "\n=== All JWT Tests Complete ===\n";

?>
