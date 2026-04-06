<?php
/**
 * API Endpoint: Refresh Access Token
 * Method: POST
 * Headers: Authorization: Bearer {token}
 * Response: {success, token, expires_in, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/api/v1/bootstrap.php';

handleCORSPreflight();


$response = [
    'success' => false,
    'token' => null,
    'expires_in' => null,
    'message' => ''
];

$db = Database::getInstance();

try {
    // Get current token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $oldToken = str_replace('Bearer ', '', $authHeader);

    if (empty($oldToken)) {
        throw new Exception('No token provided');
    }

    $db = Database::getInstance();

    // Validate current session
    $session = $db->fetchOne(
        "SELECT s.*, u.status as user_status
         FROM user_sessions s
         JOIN users u ON s.user_id = u.user_id
         WHERE s.session_id = ? AND s.is_valid = TRUE AND s.expires_at > NOW()",
        [$oldToken]
    );

    if (!$session) {
        throw new Exception('Invalid or expired session');
    }

    if ($session['user_status'] !== 'active') {
        throw new Exception('User account is not active');
    }

    // Generate new token
    $newToken = generateSecureToken(64);
    $newExpiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);

    // Invalidate old token and create new
    $db->beginTransaction();

    $db->execute(
        "UPDATE user_sessions SET is_valid = FALSE WHERE session_id = ?",
        [$oldToken]
    );

    $db->execute(
        "INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, expires_at) 
         VALUES (?, ?, ?, ?, ?)",
        [
            $newToken,
            $session['user_id'],
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $newExpiresAt
        ]
    );

    $db->commit();

    $response['success'] = true;
    $response['token'] = $newToken;
    $response['expires_in'] = SESSION_TIMEOUT;
    $response['message'] = 'Token refreshed successfully';
    http_response_code(200);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    http_response_code(401);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
