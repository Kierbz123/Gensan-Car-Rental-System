<?php
/**
 * AJAX: Get Customer Rental History
 * Path: modules/customers/ajax/get-rental-history.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$customerId = (int) ($_GET['customer_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);

if (!$customerId) {
    echo json_encode(['success' => false, 'message' => 'Customer ID required']);
    exit;
}

$db = Database::getInstance();
$offset = ($page - 1) * $perPage;

$total = $db->fetchColumn(
    "SELECT COUNT(*) FROM rental_agreements WHERE customer_id = ?",
    [$customerId]
);

$rentals = $db->fetchAll(
    "SELECT ra.*, v.plate_number, v.brand, v.model, v.vehicle_code
     FROM rental_agreements ra
     JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
     WHERE ra.customer_id = ?
     ORDER BY ra.created_at DESC
     LIMIT ? OFFSET ?",
    [$customerId, $perPage, $offset]
);

echo json_encode([
    'success' => true,
    'data' => $rentals,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => ceil($total / $perPage),
]);
