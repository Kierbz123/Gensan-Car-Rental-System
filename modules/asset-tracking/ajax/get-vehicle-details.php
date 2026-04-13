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

$vehicleId = trim($_GET['vehicle_id'] ?? '');
if (empty($vehicleId)) {
    echo json_encode(['success' => false, 'message' => 'Vehicle ID required']);
    exit;
}
// Sanitize: allow uppercase letters, digits and dashes only (GCR-XX-0000 format)
$vehicleId = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($vehicleId));

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
