<?php
/**
 * AJAX: Search Customers (live search / autocomplete)
 * Path: modules/customers/ajax/search-customers.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$limit = min((int) ($_GET['limit'] ?? 10), 50);

if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$db = Database::getInstance();
$search = "%{$q}%";

$customers = $db->fetchAll(
    "SELECT customer_id, customer_code, first_name, last_name,
            phone_number, email, customer_type, status
     FROM customers
     WHERE deleted_at IS NULL
       AND (first_name LIKE ? OR last_name LIKE ? OR customer_code LIKE ?
            OR phone_number LIKE ? OR email LIKE ?)
     ORDER BY last_name, first_name
     LIMIT ?",
    [$search, $search, $search, $search, $search, $limit]
);

echo json_encode(['success' => true, 'data' => $customers]);
