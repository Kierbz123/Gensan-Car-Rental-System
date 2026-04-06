<?php
/**
 * AJAX: Search Vehicles (live search / autocomplete)
 * Path: modules/asset-tracking/ajax/search-vehicles.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$limit = min((int) ($_GET['limit'] ?? 10), 50);
$status = $_GET['status'] ?? null; // optional: restrict to 'available' etc.

if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$db = Database::getInstance();
$search = "%{$q}%";
$params = [$search, $search, $search, $search];
$extra = '';

if ($status) {
    $extra = 'AND v.current_status = ?';
    $params[] = $status;
}

$params[] = $limit;

$vehicles = $db->fetchAll(
    "SELECT v.vehicle_id, v.vehicle_code, v.plate_number, v.brand, v.model,
            v.year_model, v.current_status, vc.category_name
     FROM vehicles v
     JOIN vehicle_categories vc ON v.category_id = vc.category_id
     WHERE v.deleted_at IS NULL
       AND (v.plate_number LIKE ? OR v.vehicle_code LIKE ? OR v.brand LIKE ? OR v.model LIKE ?)
       {$extra}
     ORDER BY v.plate_number
     LIMIT ?",
    $params
);

echo json_encode(['success' => true, 'data' => $vehicles]);
