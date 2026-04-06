<?php
/**
 * API Endpoint: Verify Token Validity
 * Method: GET
 * Headers: Authorization: Bearer {token}
 * Response: {success, valid, user, expires_at, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/api/v1/bootstrap.php';

handleCORSPreflight();


$response = [
    'success' => false,
    'valid' => false,
    'user' => null,
    'expires_at' => null,
    'message' => ''
];

try {
    // Get token from header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    if (empty($token)) {
        throw new Exception('No token provided');
    }

    $db = Database::getInstance();

    // Validate session
    $session = $db->fetchOne(
        "SELECT s.*, u.user_id, u.username, u.email, u.first_name, u.last_name, 
                u.role, u.department, u.avatar_path, u.status as user_status
         FROM user_sessions s
         JOIN users u ON s.user_id = u.user_id
         WHERE s.session_id = ? AND s.is_valid = TRUE AND s.expires_at > NOW()",
        [$token]
    );

    if (!$session) {
        $response['message'] = 'Invalid or expired token';
        echo json_encode($response);
        exit;
    }

    if ($session['user_status'] !== 'active') {
        $response['message'] = 'User account is not active';
        echo json_encode($response);
        exit;
    }

    $response['success'] = true;
    $response['valid'] = true;
    $response['expires_at'] = $session['expires_at'];
    $response['user'] = [
        'user_id' => $session['user_id'],
        'username' => $session['username'],
        'email' => $session['email'],
        'first_name' => $session['first_name'],
        'last_name' => $session['last_name'],
        'full_name' => $session['first_name'] . ' ' . $session['last_name'],
        'role' => $session['role'],
        'department' => $session['department'],
        'avatar_path' => $session['avatar_path']
    ];
    $response['message'] = 'Token is valid';
    http_response_code(200);

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
