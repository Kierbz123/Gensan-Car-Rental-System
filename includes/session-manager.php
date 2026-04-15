<?php
// /var/www/html/gensan-car-rental-system/includes/session-manager.php

/**
 * Session Management
 * Must be included at the beginning of every page.
 * Self-bootstrapping: loads config.php if not already loaded.
 */

// Bootstrap: ensure config constants are available no matter what order files are included
if (!defined('GCR_SYSTEM')) {
    require_once __DIR__ . '/../config/config.php';
}

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    // Automatically enable secure cookies in production (requires HTTPS)
    ini_set('session.cookie_secure', (defined('ENVIRONMENT') && ENVIRONMENT === 'production') ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

    // Ensure session path exists
    if (!is_dir(SESSION_PATH)) {
        mkdir(SESSION_PATH, 0755, true);
    }
    ini_set('session.save_path', SESSION_PATH);

    // IMPORTANT: Use a DIFFERENT name for PHP's native session so it
    // doesn't overwrite our custom DB-backed GCR_Session cookie token.
    session_name(PHP_SESSION_NAME);
    session_start();
}

// Validate session
$currentUser = User::validateSession();

// Check if we are already on an exempt page to avoid redirect loops
$currentPage = basename($_SERVER['PHP_SELF']);
$exemptPages = ['login.php', 'vehicle-scan.php'];
$isExemptPage = in_array($currentPage, $exemptPages, true);

if (!$currentUser && !$isExemptPage) {
    // Redirect to login
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// Create global user object if logged in
/** @var User|null $authUser */
$authUser = ($currentUser) ? new User($currentUser['user_id']) : null;

// Check session timeout based on LAST ACTIVITY (not login time).
// login_time is set once and never updated — using it would log out
// any user exactly 1 hour after login even if they are actively navigating.
if ($currentUser && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    User::logout($currentUser['user_id'], $_SESSION['session_id'] ?? null);
    header("Location: " . BASE_URL . "login.php?timeout=1");
    exit;
}

// Update session activity
$_SESSION['last_activity'] = time();

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token
 */
function getCsrfToken()
{
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Generate CSRF token field for forms
 */
function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken()) . '">';
}
