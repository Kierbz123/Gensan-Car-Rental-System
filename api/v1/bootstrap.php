<?php
// api/v1/bootstrap.php
//
// Shared bootstrap for ALL standalone route files in api/v1/**/*.php
// Include this BEFORE any logic in every route.
//
// Provides:
//   - handleCORSPreflight()  → correct origin restriction + OPTIONS handling
//   - authenticateAPI($token) → validates Bearer token against user_sessions
//   - hasPermission($user, $perm) → checks ROLE_PERMISSIONS with wildcard support
//   - apiError($msg, $code)   → standardized JSON error exit

require_once dirname(__DIR__, 2) . '/config/config.php';

// ---------------------------------------------------------------
// CORS + OPTIONS preflight
// ---------------------------------------------------------------
function handleCORSPreflight(): void
{
    $allowed = ['http://localhost', 'http://127.0.0.1'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowed, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    } elseif ($origin === '') {
        header('Access-Control-Allow-Origin: *'); // same-origin / server requests
    }
    // Unknown origins: no ACAO header → browser blocks it (correct)

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ---------------------------------------------------------------
// Token authentication against user_sessions table
// ---------------------------------------------------------------
function authenticateAPI(string $token = ''): array|bool
{
    $raw = trim(str_replace('Bearer ', '', $token));

    if ($raw === '') {
        return false;
    }

    $db = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT s.session_id, u.user_id, u.username, u.email,
                u.first_name, u.last_name, u.role, u.status
         FROM user_sessions s
         JOIN users u ON s.user_id = u.user_id
         WHERE s.session_id = ?
           AND s.expires_at > NOW()
           AND s.is_valid   = TRUE
           AND u.status     = 'active'",
        [$raw]
    );

    return $row ?: false;
}

// ---------------------------------------------------------------
// Permission check using ROLE_PERMISSIONS (supports wildcard *)
// ---------------------------------------------------------------
function hasPermission(array|bool $user, string $permission): bool
{
    if (!$user) {
        return false;
    }

    global $ROLE_PERMISSIONS;

    $perms = $ROLE_PERMISSIONS[$user['role']] ?? [];

    // Global wildcard (system_admin)
    if (in_array('*', $perms, true)) {
        return true;
    }

    // Exact match
    if (in_array($permission, $perms, true)) {
        return true;
    }

    // Module-level wildcard (e.g. 'vehicles.*' matches 'vehicles.create')
    $parts = explode('.', $permission);
    $wildcard = $parts[0] . '.*';

    return in_array($wildcard, $perms, true);
}

// ---------------------------------------------------------------
// Standardized JSON error response + exit
// ---------------------------------------------------------------
function apiError(string $message, int $code = 500): never
{
    // Mask internal errors in production
    if ($code === 500 && !DEBUG_MODE) {
        $message = 'An internal server error occurred.';
    }

    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'code' => $code,
    ]);
    exit;
}
