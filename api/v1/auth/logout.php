<?php
/**
 * API Endpoint: User Logout
 * Method: POST
 * Headers: Authorization: Bearer {token}
 * Response: {success, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/api/v1/bootstrap.php';

handleCORSPreflight();


$response = [
    'success' => false,
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

    // Invalidate session
    $db = Database::getInstance();
    $session = $db->fetchOne(
        "SELECT user_id FROM user_sessions WHERE session_id = ? AND is_valid = TRUE",
        [$token]
    );

    if ($session) {
        // Log the logout
        AuditLogger::log(
            $session['user_id'],
            null,
            null,
            'logout',
            'auth',
            'user_sessions',
            $token,
            'User logout via API',
            json_encode(['is_valid' => true]),
            json_encode(['is_valid' => false]),
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            'POST',
            '/api/v1/auth/logout',
            'info'
        );

        // Invalidate session
        $db->execute(
            "UPDATE user_sessions SET is_valid = FALSE WHERE session_id = ?",
            [$token]
        );
    }

    $response['success'] = true;
    $response['message'] = 'Logout successful';
    http_response_code(200);

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
