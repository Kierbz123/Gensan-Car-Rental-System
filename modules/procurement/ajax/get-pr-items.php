<?php
/**
 * AJAX: Get PR Line Items
 * Path: modules/procurement/ajax/get-pr-items.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$prId = (int) ($_GET['pr_id'] ?? 0);

if (!$prId) {
    echo json_encode(['success' => false, 'message' => 'PR ID required']);
    exit;
}

$db = Database::getInstance();
$items = $db->fetchAll(
    "SELECT pri.*, v.plate_number, v.brand, v.model
     FROM procurement_request_items pri
     LEFT JOIN vehicles v ON pri.vehicle_id = v.vehicle_id
     WHERE pri.procurement_id = ?
     ORDER BY pri.item_id",
    [$prId]
);

$total = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));

echo json_encode(['success' => true, 'data' => $items, 'total' => round($total, 2)]);
