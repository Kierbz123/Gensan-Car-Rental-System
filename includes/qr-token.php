<?php
/**
 * QR Scan Token Utilities
 * Path: includes/qr-token.php
 *
 * Generates and validates permanent HMAC-SHA256 URL tokens for the public
 * vehicle-scan.php page. Completely stateless — no DB lookup on every scan.
 *
 * Security model:
 *   - Token = HMAC-SHA256(vehicle_id, QR_HMAC_SECRET)
 *   - Deterministic: same vehicle_id always produces the same token
 *   - Changing QR_HMAC_SECRET in .env invalidates all existing tokens (force re-print)
 *   - Validated with hash_equals() to prevent timing-attack enumeration
 */

if (!defined('GCR_SYSTEM')) {
    require_once __DIR__ . '/../config/config.php';
}

/**
 * Generate a permanent HMAC token for a given vehicle ID.
 *
 * @param  string $vehicleId  e.g. "GCR-HB-0006"
 * @return string             64-char lowercase hex HMAC-SHA256
 */
function generateScanToken(string $vehicleId): string
{
    return hash_hmac('sha256', $vehicleId, QR_HMAC_SECRET);
}

/**
 * Validate a scan token against a vehicle ID.
 * Uses hash_equals() to prevent timing attacks.
 *
 * @param  string $vehicleId  Vehicle ID from the URL
 * @param  string $token      Token from the URL
 * @return bool               True if valid
 */
function validateScanToken(string $vehicleId, string $token): bool
{
    if (empty($vehicleId) || empty($token)) {
        return false;
    }
    return hash_equals(generateScanToken($vehicleId), $token);
}

/**
 * Build the full public scan URL for a vehicle, including the HMAC token.
 *
 * @param  string $vehicleId  e.g. "GCR-HB-0006"
 * @return string             Full absolute URL scannable by a phone camera
 */
function buildScanUrl(string $vehicleId): string
{
    return SCAN_URL
        . '?id=' . urlencode($vehicleId)
        . '&t='  . generateScanToken($vehicleId);
}
