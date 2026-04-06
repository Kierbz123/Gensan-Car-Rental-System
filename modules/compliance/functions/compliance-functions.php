<?php
/**
 * Compliance Functions - helper functions for compliance module
 * Path: modules/compliance/functions/compliance-functions.php
 */

/**
 * Get compliance status label and CSS class
 */
function getComplianceStatusBadge(string $status): array
{
    return [
        'active' => ['label' => 'Active', 'class' => 'badge-success'],
        'renewal_pending' => ['label' => 'Renewal Pending', 'class' => 'badge-warning'],
        'expired' => ['label' => 'Expired', 'class' => 'badge-danger'],
        'renewed' => ['label' => 'Renewed', 'class' => 'badge-info'],
    ][$status] ?? ['label' => ucfirst($status), 'class' => 'badge-info'];
}

/**
 * Returns how many days until expiry (negative if already expired)
 */
function daysUntilExpiry(string $expiryDate): int
{
    $today = new DateTime('today');
    $expiry = new DateTime($expiryDate);
    $diff = $today->diff($expiry);
    return $expiry >= $today ? (int) $diff->days : -(int) $diff->days;
}

/**
 * Build a summary of compliance status for a vehicle
 */
function getVehicleComplianceSummary(int $vehicleId): array
{
    $db = Database::getInstance();
    $rows = $db->fetchAll(
        "SELECT compliance_type, status, expiry_date,
                DATEDIFF(expiry_date, CURDATE()) AS days_remaining
         FROM compliance_records
         WHERE vehicle_id = ?
         ORDER BY compliance_type, expiry_date DESC",
        [$vehicleId]
    );

    // Keep only the newest per type
    $summary = [];
    foreach ($rows as $row) {
        if (!isset($summary[$row['compliance_type']])) {
            $summary[$row['compliance_type']] = $row;
        }
    }

    return array_values($summary);
}
