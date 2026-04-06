<?php
/**
 * AJAX: Filter Vehicles
 * Returns filtered vehicle list as JSON
 * Path: modules/asset-tracking/ajax/filter-vehicles.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$db = Database::getInstance();

$status = $_GET['status'] ?? null;
$categoryId = $_GET['category_id'] ?? null;
$search = $_GET['search'] ?? null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? ITEMS_PER_PAGE);

$where = ['v.deleted_at IS NULL'];
$params = [];

if ($status) {
    $where[] = 'v.current_status = ?';
    $params[] = $status;
}
if ($categoryId) {
    $where[] = 'v.category_id = ?';
    $params[] = $categoryId;
}
if ($search) {
    $where[] = '(v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.vehicle_code LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

$whereClause = implode(' AND ', $where);
$offset = ($page - 1) * $perPage;

$total = $db->fetchColumn(
    "SELECT COUNT(*) FROM vehicles v WHERE {$whereClause}",
    $params
);

$vehicles = $db->fetchAll(
    "SELECT v.*, vc.category_name
     FROM vehicles v
     JOIN vehicle_categories vc ON v.category_id = vc.category_id
     WHERE {$whereClause}
     ORDER BY v.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

echo json_encode([
    'success' => true,
    'data' => $vehicles,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => ceil($total / $perPage),
]);
