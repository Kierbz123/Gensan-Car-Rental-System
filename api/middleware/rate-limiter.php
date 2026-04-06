<?php
// api/middleware/rate-limiter.php

/**
 * API Rate Limiter (General Traffic)
 * Prevents API abuse for authenticated endpoints.
 *
 * NOTE: This uses PHP session storage and is scoped per-session, not per-IP.
 * For login brute-force protection, the DB-backed checkRateLimit() in
 * includes/security.php is used instead (called by api/v1/auth/login.php).
 *
 * Function renamed to checkAPIRateLimit() to avoid a fatal "Cannot redeclare"
 * collision with checkRateLimit() in includes/security.php.
 *
 * For production, replace with Redis or a dedicated DB table.
 */

function checkAPIRateLimit(int $limit = 60, int $period = 60): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'api_rate:' . $ip;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $now = time();

    if (!isset($_SESSION[$key]) || ($now - $_SESSION[$key]['start_time']) > $period) {
        // Start a fresh window
        $_SESSION[$key] = [
            'count' => 1,
            'start_time' => $now,
        ];
    } else {
        $_SESSION[$key]['count']++;

        if ($_SESSION[$key]['count'] > $limit) {
            $resetAt = $_SESSION[$key]['start_time'] + $period;
            $retryAfter = max(0, $resetAt - $now);

            header('Content-Type: application/json');
            header("Retry-After: $retryAfter");
            http_response_code(429);
            echo json_encode([
                'status' => 'error',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
                'code' => 429,
            ]);
            exit;
        }
    }

    // Informational rate-limit headers
    $remaining = max(0, $limit - $_SESSION[$key]['count']);
    $resetAt = $_SESSION[$key]['start_time'] + $period;
    header("X-RateLimit-Limit: $limit");
    header("X-RateLimit-Remaining: $remaining");
    header("X-RateLimit-Reset: $resetAt");
}
