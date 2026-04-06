<?php
/**
 * AJAX Regenerate QR Code
 * Path: modules/asset-tracking/ajax/regenerate-qr.php
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/session-manager.php';
require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

if (!$authUser->hasPermission('vehicles.update')) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$vehicleId = $_POST['vehicle_id'] ?? '';

if (empty($vehicleId)) {
    echo json_encode(['success' => false, 'message' => 'Vehicle ID is required.']);
    exit;
}

try {
    $vehicleObj = new Vehicle();
    $vehicleData = $vehicleObj->getById($vehicleId);

    if (!$vehicleData) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
        exit;
    }

    $vehicleObj->generateQRCode($vehicleId);

    echo json_encode(['success' => true, 'message' => 'QR Code regenerated successfully.']);
} catch (Exception $e) {
    error_log("Failed to regenerate QR for vehicle {$vehicleId}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to regenerate QR code. Check error logs.']);
}
exit;
