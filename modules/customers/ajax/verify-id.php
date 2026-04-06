<?php
/**
 * AJAX: Verify Customer ID Document
 * Path: modules/customers/ajax/verify-id.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$customerId = (int) ($_POST['customer_id'] ?? 0);
$idType = sanitize($_POST['id_type'] ?? '');
$idNumber = sanitize($_POST['id_number'] ?? '');
$expiryDate = $_POST['expiry_date'] ?? null;

if (!$customerId || !$idType || !$idNumber) {
    echo json_encode(['success' => false, 'message' => 'Customer ID, ID type, and number are required']);
    exit;
}

$db = Database::getInstance();

$db->execute(
    "UPDATE customers
     SET id_type = ?, id_number = ?, id_expiry_date = ?, updated_at = NOW()
     WHERE customer_id = ?",
    [$idType, $idNumber, $expiryDate, $customerId]
);

echo json_encode(['success' => true, 'message' => 'ID verified and saved']);
