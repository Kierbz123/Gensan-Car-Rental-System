<?php
/**
 * AJAX: Get Vehicle Details
 * Returns a single vehicle's full details as JSON
 * Path: modules/asset-tracking/ajax/get-vehicle-details.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);
if (!$vehicleId) {
    echo json_encode(['success' => false, 'message' => 'Vehicle ID required']);
    exit;
}

$db = Database::getInstance();

$vehicle = $db->fetchOne(
    "SELECT v.*, vc.category_name
     FROM vehicles v
     JOIN vehicle_categories vc ON v.category_id = vc.category_id
     WHERE v.vehicle_id = ? AND v.deleted_at IS NULL",
    [$vehicleId]
);

if (!$vehicle) {
    echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
    exit;
}

// Fetch most recent maintenance log
$lastService = $db->fetchOne(
    "SELECT service_date, service_type, total_cost
     FROM maintenance_logs
     WHERE vehicle_id = ?
     ORDER BY service_date DESC LIMIT 1",
    [$vehicleId]
);

$vehicle['last_service'] = $lastService;

echo json_encode(['success' => true, 'data' => $vehicle]);
