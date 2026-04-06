<?php
/**
 * AJAX: Update Vehicle Location / Branch
 * Path: modules/asset-tracking/ajax/update-location.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$vehicleId = (int) ($input['vehicle_id'] ?? 0);
$location = trim($input['location'] ?? '');

if (!$vehicleId || !$location) {
    echo json_encode(['success' => false, 'message' => 'Vehicle ID and location are required']);
    exit;
}

$db = Database::getInstance();

$db->execute(
    "UPDATE vehicles SET current_location = ?, updated_at = NOW() WHERE vehicle_id = ?",
    [$location, $vehicleId]
);

echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
