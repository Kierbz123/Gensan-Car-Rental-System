<?php
// modules/suppliers/ajax/supplier-products.php
// AJAX endpoint for supplier product (parts_inventory) CRUD
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

$authUser->requirePermission('suppliers.update');

$db     = Database::getInstance();
$inv    = new Inventory();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper: get user display name safely
$userName = $authUser->getFullName() ?: ('User #' . $authUser->getId());
$userRole = $authUser->getData()['role'] ?? 'unknown';

try {
    switch ($action) {

        // ── LIST products for a supplier ─────────────────────────────────
        case 'list':
            $supplierId = (int)($_GET['supplier_id'] ?? 0);
            if (!$supplierId) throw new Exception('Supplier ID required.');
            $products = $inv->getBySupplier($supplierId);
            // getBySupplier returns subset of columns; fetch full rows for the CRUD table
            $products = $db->fetchAll(
                "SELECT * FROM parts_inventory WHERE supplier_id = ? ORDER BY item_name ASC",
                [$supplierId]
            );
            echo json_encode(['success' => true, 'data' => $products]);
            break;

        // ── ADD a new product ─────────────────────────────────────────────
        case 'add':
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) throw new Exception('Invalid security token.');
            $supplierId = (int)($_POST['supplier_id'] ?? 0);
            if (!$supplierId) throw new Exception('Supplier ID required.');

            $itemName        = trim($_POST['item_name'] ?? '');
            $itemCategory    = $_POST['item_category'] ?? 'parts';
            $unit            = trim($_POST['unit'] ?? 'pcs');
            $unitCost        = (float)($_POST['unit_cost'] ?? 0);
            $qtyOnHand       = (float)($_POST['quantity_on_hand'] ?? 0);
            $reorderLevel    = (float)($_POST['reorder_level'] ?? 0);
            $storageLocation = trim($_POST['storage_location'] ?? 'Main Garage');
            $notes           = trim($_POST['notes'] ?? '');

            if (empty($itemName)) throw new Exception('Product name is required.');
            if (!in_array($itemCategory, ['parts','supplies','fuel','others'])) throw new Exception('Invalid category.');
            if ($unitCost < 0)  throw new Exception('Unit cost cannot be negative.');
            if ($qtyOnHand < 0) throw new Exception('Quantity cannot be negative.');

            // Use Inventory::create() — handles auto item_code + opening stock transaction
            $newId = $inv->create([
                'item_name'        => $itemName,
                'item_category'    => $itemCategory,
                'unit'             => $unit,
                'unit_cost'        => $unitCost,
                'quantity_on_hand' => $qtyOnHand,
                'reorder_level'    => $reorderLevel,
                'supplier_id'      => $supplierId,
                'storage_location' => $storageLocation,
                'notes'            => $notes,
            ], $authUser->getId());

            $product = $db->fetchOne("SELECT * FROM parts_inventory WHERE inventory_id = ?", [$newId]);

            AuditLogger::log(
                $authUser->getId(), $userName, $userRole,
                'create', 'suppliers', 'parts_inventory', $product['item_code'],
                "Added product '{$itemName}' to supplier #{$supplierId}",
                null, json_encode(['item_name' => $itemName, 'unit_cost' => $unitCost, 'supplier_id' => $supplierId]),
                $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '',
                'POST', '/suppliers/ajax/supplier-products', 'info'
            );

            echo json_encode(['success' => true, 'message' => 'Product added successfully.', 'data' => $product]);
            break;

        // ── UPDATE an existing product ────────────────────────────────────
        case 'update':
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) throw new Exception('Invalid security token.');
            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            if (!$inventoryId) throw new Exception('Inventory ID required.');

            $existing = $db->fetchOne("SELECT * FROM parts_inventory WHERE inventory_id = ?", [$inventoryId]);
            if (!$existing) throw new Exception('Product not found.');

            $itemName        = trim($_POST['item_name'] ?? '');
            $itemCategory    = $_POST['item_category'] ?? 'parts';
            $unit            = trim($_POST['unit'] ?? 'pcs');
            $unitCost        = (float)($_POST['unit_cost'] ?? 0);
            $qtyOnHand       = (float)($_POST['quantity_on_hand'] ?? 0);
            $reorderLevel    = (float)($_POST['reorder_level'] ?? 0);
            $storageLocation = trim($_POST['storage_location'] ?? 'Main Garage');
            $notes           = trim($_POST['notes'] ?? '');

            if (empty($itemName)) throw new Exception('Product name is required.');
            if (!in_array($itemCategory, ['parts','supplies','fuel','others'])) throw new Exception('Invalid category.');

            $db->execute(
                "UPDATE parts_inventory
                 SET item_name=?, item_category=?, unit=?, unit_cost=?,
                     quantity_on_hand=?, reorder_level=?, storage_location=?, notes=?, updated_at=NOW()
                 WHERE inventory_id=?",
                [$itemName, $itemCategory, $unit, $unitCost, $qtyOnHand, $reorderLevel, $storageLocation, $notes, $inventoryId]
            );
            $product = $db->fetchOne("SELECT * FROM parts_inventory WHERE inventory_id = ?", [$inventoryId]);

            AuditLogger::log(
                $authUser->getId(), $userName, $userRole,
                'update', 'suppliers', 'parts_inventory', $existing['item_code'],
                "Updated product '{$itemName}'",
                json_encode($existing), json_encode($product),
                $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '',
                'POST', '/suppliers/ajax/supplier-products', 'info'
            );

            echo json_encode(['success' => true, 'message' => 'Product updated successfully.', 'data' => $product]);
            break;

        // ── DELETE a product ──────────────────────────────────────────────
        case 'delete':
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) throw new Exception('Invalid security token.');
            if (!$authUser->hasPermission('suppliers.delete')) throw new Exception('Insufficient permissions to delete products.');
            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            if (!$inventoryId) throw new Exception('Inventory ID required.');

            $existing = $db->fetchOne("SELECT * FROM parts_inventory WHERE inventory_id = ?", [$inventoryId]);
            if (!$existing) throw new Exception('Product not found.');

            // Safety: block if referenced in any procurement item
            $usedInPR = (int)$db->fetchColumn(
                "SELECT COUNT(*) FROM procurement_items WHERE item_description = ?",
                [$existing['item_name']]
            );
            if ($usedInPR > 0) {
                throw new Exception("Cannot delete: '{$existing['item_name']}' is referenced in {$usedInPR} procurement request(s).");
            }

            $inv->delete($inventoryId, $authUser->getId());

            echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
            break;

        // ── GET single product for edit modal ─────────────────────────────
        case 'get':
            $inventoryId = (int)($_GET['inventory_id'] ?? 0);
            if (!$inventoryId) throw new Exception('Inventory ID required.');
            $product = $db->fetchOne("SELECT * FROM parts_inventory WHERE inventory_id = ?", [$inventoryId]);
            if (!$product) throw new Exception('Product not found.');
            echo json_encode(['success' => true, 'data' => $product]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    http_response_code(200); // keep 200 so fetch().json() doesn't fail
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
