<?php
/**
 * AJAX: Get Suppliers List (for dropdowns)
 * Path: modules/procurement/ajax/get-suppliers.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$search = $_GET['q'] ?? '';
$limit = min((int) ($_GET['limit'] ?? 50), 100);
$params = [];
$where = ["status = 'active'"];

if ($search) {
    $where[] = "(supplier_name LIKE ? OR contact_person LIKE ? OR supplier_code LIKE ?)";
    $s = "%{$search}%";
    $params = [$s, $s, $s];
}

$params[] = $limit;

$suppliers = $db->fetchAll(
    "SELECT supplier_id, supplier_code, supplier_name, contact_person, phone, email
     FROM suppliers WHERE " . implode(' AND ', $where) . " ORDER BY supplier_name LIMIT ?",
    $params
);

echo json_encode(['success' => true, 'data' => $suppliers]);
