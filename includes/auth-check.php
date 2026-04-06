<?php
// includes/auth-check.php

/**
 * Authorization Guard Component
 * 
 * Provides a lightweight verification layer for sensitive endpoints,
 * primarily used in API/AJAX controllers and specialized module fragments.
 * Self-bootstrapping: loads config.php if not already loaded.
 */

// Bootstrap: ensure config constants are available
if (!defined('GCR_SYSTEM')) {
    require_once __DIR__ . '/../config/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_name(PHP_SESSION_NAME); // Must match session-manager.php
    session_start();
}

// Global helper for unauthorized response
if (!function_exists('sendUnauthorized')) {
    function sendUnauthorized($message = 'Security protocol violation. Access denied.')
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => $message,
                'code' => 'AUTH_FAILURE'
            ]);
            exit;
        } else {
            $_SESSION['error_message'] = $message;
            header("Location: " . BASE_URL . "login.php");
            exit;
        }
    }
}

// 1. Verify Session Persistence
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
    sendUnauthorized();
}

// 2. Validate Identity Integrity
try {
    $db = Database::getInstance();
    $sessionScan = $db->fetchOne(
        "SELECT s.session_id, u.status 
         FROM user_sessions s
         JOIN users u ON s.user_id = u.user_id
         WHERE s.session_id = ? AND s.is_valid = TRUE",
        [$_SESSION['session_id']]
    );

    if (!$sessionScan) {
        // Destroy compromised/expired session
        session_destroy();
        sendUnauthorized('Session invalidated. Please re-authenticate.');
    }

    if ($sessionScan['status'] !== 'active') {
        session_destroy();
        sendUnauthorized('Account restricted. Contact system administrator.');
    }

} catch (Exception $e) {
    // Fail closed on database/system errors
    error_log("AuthCheck failure: " . $e->getMessage());
    sendUnauthorized('System authentication service temporarily unavailable.');
}

// 3. CSRF Verification for Non-GET Requests
// AJAX endpoints must send the token via X-CSRF-TOKEN header or csrf_token POST field.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendUnauthorized('CSRF Security Token Mismatch.');
    }
}
