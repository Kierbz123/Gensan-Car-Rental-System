<?php
// modules/inventory/ajax/store-procured-item.php
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

$authUser->requirePermission('inventory.update');

$db = Database::getInstance();
$action = $_POST['action'] ?? '';

try {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token.');
    }

    $itemId = (int)($_POST['item_id'] ?? 0);
    if (!$itemId) throw new Exception('Procurement Item ID is required.');

    // Fetch the procurement item
    $pItem = $db->fetchOne(
        "SELECT pi.*, pr.pr_number, pr.requestor_id 
         FROM procurement_items pi
         JOIN procurement_requests pr ON pi.pr_id = pr.pr_id
         WHERE pi.item_id = ?",
        [$itemId]
    );

    if (!$pItem) throw new Exception('Procurement item not found.');
    if ($pItem['inventory_status'] !== 'pending') {
        throw new Exception("Item is already marked as {$pItem['inventory_status']}.");
    }

    if ($action === 'skip') {
        // Mark as skipped (e.g. food, office supplies not tracked in garage)
        $db->execute(
            "UPDATE procurement_items SET inventory_status = 'skipped' WHERE item_id = ?",
            [$itemId]
        );
        
        AuditLogger::log(
            $authUser->getId(), $authUser->getFullName(), $authUser->getData()['role'] ?? '',
            'update', 'inventory', 'procurement_items', (string)$itemId,
            "Skipped inventory storage for '{$pItem['item_description']}'",
            json_encode(['inventory_status' => 'pending']), json_encode(['inventory_status' => 'skipped']),
            $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '',
            'POST', '/inventory/ajax/store-procured-item', 'info'
        );

        echo json_encode(['success' => true, 'message' => 'Item skipped successfully.']);
        exit;
    }

    if ($action === 'store') {
        $inv = new Inventory();
        
        // 1. Check if exact item already exists
        $existingInv = $db->fetchOne(
            "SELECT inventory_id FROM parts_inventory WHERE item_name = ?",
            [$pItem['item_description']]
        );

        $invId = null;
        $qtyToStore = (float)$pItem['quantity_received'];
        if ($qtyToStore <= 0) $qtyToStore = (float)$pItem['quantity']; // Fallback

        if ($existingInv) {
            // Add stock to existing
            $invId = $existingInv['inventory_id'];
            $inv->receive(
                $invId, 
                $qtyToStore, 
                (float)$pItem['actual_unit_cost'] ?: (float)$pItem['estimated_unit_cost'], 
                $pItem['pr_id'], 
                'procurement', 
                $authUser->getId(), 
                "Received from PR"
            );
        } else {
            // Create new inventory tracker
            $invId = $inv->create([
                'item_name' => $pItem['item_description'],
                'item_category' => $pItem['item_category'] === 'services' ? 'others' : $pItem['item_category'],
                'unit' => $pItem['unit'],
                'quantity_on_hand' => $qtyToStore, // Inventory::create handles the opening stock receipt
                'unit_cost' => (float)$pItem['actual_unit_cost'] ?: (float)$pItem['estimated_unit_cost'],
                'supplier_id' => $pItem['supplier_id'],
                'storage_location' => 'Main Garage', // Default
                'notes' => 'Auto-created from PR'
            ], $authUser->getId());
        }

        // Link and mark as stored
        $db->execute(
            "UPDATE procurement_items SET inventory_status = 'stored', inventory_id = ? WHERE item_id = ?",
            [$invId, $itemId]
        );

        AuditLogger::log(
            $authUser->getId(), $authUser->getFullName(), $authUser->getData()['role'] ?? '',
            'update', 'inventory', 'procurement_items', (string)$itemId,
            "Stored '{$pItem['item_description']}' to inventory",
            json_encode(['inventory_status' => 'pending']), json_encode(['inventory_status' => 'stored', 'inventory_id' => $invId]),
            $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '',
            'POST', '/inventory/ajax/store-procured-item', 'info'
        );

        // Notify the Requester
        if (!empty($pItem['requestor_id'])) {
            $db->execute(
                "INSERT INTO notifications (user_id, type, title, message, related_module, related_record_id, related_url)
                 VALUES (?, 'inventory_stored', 'Item Ready for Use', ?, 'inventory', ?, 'modules/inventory/index.php')",
                [
                    $pItem['requestor_id'], 
                    "Your requested item '{$pItem['item_description']}' has been stored and is now ready for use.",
                    $invId
                ]
            );
        }

        echo json_encode(['success' => true, 'message' => 'Item successfully stored in inventory.']);
        exit;
    }

    throw new Exception('Invalid action specified.');

} catch (Exception $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
