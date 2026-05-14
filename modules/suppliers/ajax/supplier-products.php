<?php
// modules/suppliers/ajax/supplier-products.php
// AJAX endpoint for supplier product (supplier_catalogs) CRUD
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

$authUser->requirePermission('suppliers.update');

$db     = Database::getInstance();
$catalog = new SupplierCatalog();
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
            $products = $catalog->getBySupplier($supplierId);
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
            $notes           = trim($_POST['notes'] ?? '');

            if (empty($itemName)) throw new Exception('Product name is required.');
            if (!in_array($itemCategory, ['parts','supplies','fuel','others'])) throw new Exception('Invalid category.');
            if ($unitCost < 0)  throw new Exception('Unit cost cannot be negative.');

            $newId = $catalog->create([
                'supplier_id'      => $supplierId,
                'item_name'        => $itemName,
                'item_category'    => $itemCategory,
                'unit'             => $unit,
                'unit_cost'        => $unitCost,
                'notes'            => $notes,
            ]);

            $product = $catalog->getById($newId);

            AuditLogger::log(
                $authUser->getId(), $userName, $userRole,
                'create', 'suppliers', 'supplier_catalogs', (string)$newId,
                "Added product '{$itemName}' to supplier #{$supplierId} catalog",
                null, json_encode(['item_name' => $itemName, 'unit_cost' => $unitCost, 'supplier_id' => $supplierId]),
                $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '',
                'POST', '/suppliers/ajax/supplier-products', 'info'
            );

            echo json_encode(['success' => true, 'message' => 'Product added successfully.', 'data' => $product]);
            break;

        // ── UPDATE an existing product ────────────────────────────────────
        case 'update':
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) throw new Exception('Invalid security token.');
            $catalogId = (int)($_POST['catalog_id'] ?? 0);
            if (!$catalogId) throw new Exception('Catalog ID required.');

            $existing = $catalog->getById($catalogId);
            if (!$existing) throw new Exception('Product not found.');

            $itemName        = trim($_POST['item_name'] ?? '');
            $itemCategory    = $_POST['item_category'] ?? 'parts';
            $unit            = trim($_POST['unit'] ?? 'pcs');
            $unitCost        = (float)($_POST['unit_cost'] ?? 0);
            $notes           = trim($_POST['notes'] ?? '');

            if (empty($itemName)) throw new Exception('Product name is required.');
            if (!in_array($itemCategory, ['parts','supplies','fuel','others'])) throw new Exception('Invalid category.');

            $catalog->update($catalogId, [
                'item_name'        => $itemName,
                'item_category'    => $itemCategory,
                'unit'             => $unit,
                'unit_cost'        => $unitCost,
                'notes'            => $notes,
            ]);

            $product = $catalog->getById($catalogId);

            AuditLogger::log(
                $authUser->getId(), $userName, $userRole,
                'update', 'suppliers', 'supplier_catalogs', (string)$catalogId,
                "Updated catalog product '{$itemName}'",
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
            $catalogId = (int)($_POST['catalog_id'] ?? 0);
            if (!$catalogId) throw new Exception('Catalog ID required.');

            $existing = $catalog->getById($catalogId);
            if (!$existing) throw new Exception('Product not found.');

            $catalog->delete($catalogId);

            echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
            break;

        // ── GET single product for edit modal ─────────────────────────────
        case 'get':
            $catalogId = (int)($_GET['catalog_id'] ?? 0);
            if (!$catalogId) throw new Exception('Catalog ID required.');
            $product = $catalog->getById($catalogId);
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
