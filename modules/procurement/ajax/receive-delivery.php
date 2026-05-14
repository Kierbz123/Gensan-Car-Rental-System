<?php
/**
 * AJAX: Record PO Delivery + Sync Inventory
 * Path: modules/procurement/ajax/receive-delivery.php
 *
 * POST body (JSON):
 * {
 *   "csrf_token": "...",
 *   "pr_id": 5,
 *   "deliveries": [
 *     { "item_id": 12, "qty_received": 3, "inventory_id": 7, "unit_cost": 250.00 }
 *   ]
 * }
 *
 * Returns:
 * { "success": true, "results": [ { "item_id": 12, "receipt_ok": true, "inventory_ok": true, "warning": null } ] }
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

if (!isset($authUser) || !$authUser->hasPermission('procurement.receive')) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!validateCsrfToken($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$prId = (int) ($input['pr_id'] ?? 0);
$deliveries = $input['deliveries'] ?? [];

if (!$prId || empty($deliveries) || !is_array($deliveries)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload.']);
    exit;
}

$db = Database::getInstance();
$procObj = new ProcurementRequest();
$invObj = new Inventory();
$userId = $authUser->getId();
$results = [];
$anySuccess = false;

foreach ($deliveries as $d) {
    $itemId = (int) ($d['item_id'] ?? 0);
    $qtyReceived = (float) ($d['qty_received'] ?? 0);
    $inventoryId = !empty($d['inventory_id']) ? (int) $d['inventory_id'] : null;
    $unitCost = isset($d['unit_cost']) && $d['unit_cost'] !== '' ? (float) $d['unit_cost'] : null;

    if (!$itemId || $qtyReceived <= 0) {
        $results[] = ['item_id' => $itemId, 'receipt_ok' => false, 'inventory_ok' => null, 'warning' => 'Skipped: qty must be > 0'];
        continue;
    }

    // --- Step 1: Record the physical receipt in procurement_items ---
    $receiptOk = false;
    $inventoryOk = null;
    $warning = null;

    try {
        $procObj->recordReceipt($itemId, $qtyReceived, null, $userId);
        $receiptOk = true;
        $anySuccess = true;
    } catch (Exception $e) {
        $warning = 'Receipt failed: ' . $e->getMessage();
        $results[] = ['item_id' => $itemId, 'receipt_ok' => false, 'inventory_ok' => null, 'warning' => $warning];
        continue;
    }

    // --- Step 2: Flag for Staging (Decoupled Inventory Sync) ---
    try {
        // We just update the item to link the chosen inventory ID (if any), and mark as pending storage
        $db->execute("UPDATE procurement_items SET inventory_id = ?, inventory_status = 'pending' WHERE item_id = ?", [$inventoryId, $itemId]);
        $inventoryOk = true; // Signifies it successfully staged

        // Notify Inventory Managers / Admins
        $invManagers = $db->fetchAll("SELECT user_id FROM users WHERE role IN ('system_admin', 'fleet_manager') AND status = 'active'");
        foreach ($invManagers as $mgr) {
            $db->execute(
                "INSERT INTO notifications (user_id, type, title, message, related_module, related_record_id, related_url)
                 VALUES (?, 'inventory_pending', 'New Items Awaiting Storage', ?, 'inventory', ?, 'modules/inventory/index.php')",
                [$mgr['user_id'], "A delivery for PR #{$prId} has been recorded. Items are waiting to be put away.", $itemId]
            );
        }
    } catch (Exception $e) {
        $inventoryOk = false;
        $warning = 'Staging link failed: ' . $e->getMessage();
    }

    $results[] = [
        'item_id' => $itemId,
        'receipt_ok' => $receiptOk,
        'inventory_ok' => $inventoryOk,
        'warning' => $warning,
    ];
}

echo json_encode([
    'success' => $anySuccess,
    'results' => $results,
    'message' => $anySuccess ? 'Delivery recorded.' : 'No items were recorded.',
]);
