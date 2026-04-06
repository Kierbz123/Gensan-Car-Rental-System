<?php
// modules/rentals/ajax/get-available-drivers.php
// Returns JSON list of drivers available in a given date window.
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}


$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if (!$startDate || !$endDate) {
    echo json_encode(['success' => false, 'message' => 'start_date and end_date are required.']);
    exit;
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $startDate) || !DateTime::createFromFormat('Y-m-d', $endDate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit;
}

try {
    $driver = new Driver();
    $drivers = $driver->getAvailable($startDate, $endDate);
    echo json_encode(['success' => true, 'drivers' => $drivers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
