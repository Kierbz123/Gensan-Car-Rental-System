<?php
/**
 * Dashboard Data - AJAX data provider for all dashboard widgets
 * Path: modules/dashboard/dashboard-data.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

$db = Database::getInstance();

// 1. Fleet Summary
$fleetSummary = $db->fetchOne(
    "SELECT
        COUNT(*)                                                      AS total,
        SUM(current_status = 'available')                            AS available,
        SUM(current_status = 'rented')                               AS rented,
        SUM(current_status = 'maintenance')                          AS maintenance,
        SUM(current_status = 'reserved')                             AS reserved,
        SUM(current_status = 'cleaning')                             AS cleaning,
        SUM(current_status IN ('out_of_service','retired'))          AS inactive
     FROM vehicles WHERE deleted_at IS NULL"
);

// 2. Pending Procurement Requests
$pendingPR = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM procurement_requests WHERE status = 'pending_approval'"
);

// 3. Overdue Maintenance
$overdueMaintenanceCount = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM maintenance_schedules
     WHERE next_service_date < CURDATE() AND status = 'pending'"
);

// 4. Recent Rentals (today)
$recentRentals = $db->fetchAll(
    "SELECT ra.agreement_number, ra.status, ra.rental_end_date,
            CONCAT(c.first_name,' ',c.last_name) AS customer_name,
            v.plate_number, v.brand, v.model
     FROM rental_agreements ra
     JOIN customers c ON ra.customer_id = c.customer_id
     JOIN vehicles v  ON ra.vehicle_id  = v.vehicle_id
     WHERE DATE(ra.created_at) = CURDATE()
     ORDER BY ra.created_at DESC
     LIMIT 5"
);

// 5. Priority Alerts
$alerts = [];

if ($overdueMaintenanceCount > 0) {
    $alerts[] = [
        'severity' => 'critical',
        'message' => "{$overdueMaintenanceCount} vehicle(s) have overdue maintenance",
        'module' => 'maintenance',
    ];
}

$expiringCompliance = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM compliance_records
     WHERE status = 'renewal_pending' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
);
if ($expiringCompliance > 0) {
    $alerts[] = [
        'severity' => 'warning',
        'message' => "{$expiringCompliance} compliance document(s) expiring within 7 days",
        'module' => 'compliance',
    ];
}

if ($pendingPR > 0) {
    $alerts[] = [
        'severity' => 'info',
        'message' => "{$pendingPR} procurement request(s) awaiting approval",
        'module' => 'procurement',
    ];
}

echo json_encode([
    'success' => true,
    'fleet' => $fleetSummary,
    'pending_pr' => $pendingPR,
    'overdue_maint' => $overdueMaintenanceCount,
    'recent_rentals' => $recentRentals,
    'alerts' => $alerts,
]);
