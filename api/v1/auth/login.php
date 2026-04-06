<?php
/**
 * API Endpoint: User Login
 * Method: POST
 * Request: {username, password}
 * Response: {success, token, user, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/api/v1/bootstrap.php';
require_once dirname(__DIR__, 3) . '/api/middleware/rate-limiter.php';

handleCORSPreflight();



$response = [
    'success' => false,
    'token' => null,
    'user' => null,
    'message' => ''
];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Username and password are required');
    }

    $username = sanitize($input['username']);
    $password = $input['password'];
    $ipAddress = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Rate limiting check
    if (!checkRateLimit($ipAddress, 5, 300)) {
        throw new Exception('Too many login attempts. Please try again later.');
    }

    // Authenticate user
    $userObj = new User();
    $user = $userObj->authenticate($username, $password, $ipAddress, $userAgent);

    if (!$user) {
        http_response_code(401);
        throw new Exception('Invalid username or password');
    }

    // Generate JWT token
    $token = generateSecureToken(64);

    // Store session
    $db = Database::getInstance();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);

    $db->execute(
        "INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, expires_at) 
         VALUES (?, ?, ?, ?, ?)",
        [$token, $user['user_id'], $ipAddress, $userAgent, $expiresAt]
    );

    // Prepare user data (exclude sensitive info)
    $userData = [
        'user_id' => $user['user_id'],
        'employee_id' => $user['employee_id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'full_name' => $user['first_name'] . ' ' . $user['last_name'],
        'role' => $user['role'],
        'department' => $user['department'],
        'avatar_path' => $user['avatar_path'],
        'must_change_password' => $user['must_change_password']
    ];

    $response['success'] = true;
    $response['token'] = $token;
    $response['user'] = $userData;
    $response['message'] = 'Login successful';
    $response['expires_in'] = SESSION_TIMEOUT;

    http_response_code(200);

} catch (Exception $e) {
    http_response_code(401);
    $response['message'] = $e->getMessage();
    logError('Login failed: ' . $e->getMessage(), ['username' => $input['username'] ?? 'unknown']);
}

echo json_encode($response);
