<?php
// /var/www/html/gensan-car-rental-system/includes/security.php

/**
 * Security Functions and Middleware
 */

/**
 * Sanitize input data
 */
function sanitize($data, $type = 'string')
{
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password)
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    return empty($errors) ? true : $errors;
}

/**
 * Rate limiting check
 */
function checkRateLimit($identifier, $maxAttempts = 5, $window = 3600)
{
    $db = Database::getInstance();

    $attempts = $db->fetchColumn(
        "SELECT COUNT(*) FROM rate_limit 
         WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
        [$identifier, $window]
    );

    if ($attempts >= $maxAttempts) {
        return false;
    }

    // Log attempt
    $db->execute(
        "INSERT INTO rate_limit (identifier, ip_address, created_at) VALUES (?, ?, NOW())",
        [$identifier, $_SERVER['REMOTE_ADDR']]
    );

    return true;
}

/**
 * Encrypt sensitive data
 *
 * Uses OPENSSL_RAW_DATA so both the IV (16 bytes) and ciphertext are raw binary.
 * They are concatenated and then base64-encoded together, ensuring decryption
 * can correctly split the IV from the ciphertext by byte offset.
 */
function encryptData($data, $key = null)
{
    $key = $key ?? (defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_key');
    $iv  = random_bytes(16);
    // OPENSSL_RAW_DATA → returns raw binary, not base64
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        throw new Exception('Encryption failed.');
    }
    // Prepend raw IV to raw ciphertext, then base64 the whole thing
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function decryptData($data, $key = null)
{
    $key = $key ?? (defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_key');
    $raw = base64_decode($data, true);
    if ($raw === false || strlen($raw) <= 16) {
        return false; // Malformed payload
    }
    $iv        = substr($raw, 0, 16);   // First 16 raw bytes = IV
    $encrypted = substr($raw, 16);      // Remainder = raw ciphertext
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted; // Returns false on failure
}

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Verify file upload
 */
function verifyUpload($file, $allowedTypes = [], $maxSize = null)
{
    $maxSize = $maxSize ?? (defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 10 * 1024 * 1024);

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File size exceeds limit'];
    }

    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }

    // Verify extension matches MIME
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $validExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'application/pdf' => ['pdf']
    ];

    if (isset($validExtensions[$mimeType]) && !in_array($extension, $validExtensions[$mimeType])) {
        return ['valid' => false, 'error' => 'File extension does not match content'];
    }

    return ['valid' => true];
}

/**
 * Log security event
 */
function logSecurityEvent($event, $severity = 'info', $details = [])
{
    $db = Database::getInstance();

    $db->execute(
        "INSERT INTO security_logs 
         (event_type, severity, user_id, ip_address, user_agent, details, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        [
            $event,
            $severity,
            $_SESSION['user_id'] ?? null,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            json_encode($details)
        ]
    );
}

/**
 * Procedural Authentication for API context
 */
function authenticateAPI($token = null)
{
    if (!$token) {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (empty($token))
        return false;

    // Clean Bearer prefix
    $token = str_replace('Bearer ', '', $token);

    try {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT u.* FROM users u 
             JOIN user_sessions s ON u.user_id = s.user_id 
             WHERE s.session_id = ? AND s.expires_at > NOW() AND s.is_valid = TRUE",
            [$token]
        );

        return $user ?: false;
    } catch (Exception $e) {
        logError("API Auth Failure: " . $e->getMessage());
        return false;
    }
}

/**
 * Procedural Permission Check
 */
function hasPermission($user, $permission)
{
    global $ROLE_PERMISSIONS;

    if (!$user || !isset($user['role']))
        return false;

    $role = $user['role'];
    $perms = $ROLE_PERMISSIONS[$role] ?? [];

    if (in_array('*', $perms))
        return true;
    if (in_array($permission, $perms))
        return true;

    // Wildcard check
    $parts = explode('.', $permission);
    if (isset($parts[0]) && in_array($parts[0] . '.*', $perms)) {
        return true;
    }

    return false;
}
