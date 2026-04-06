<?php
// api/v1/procurement/get-supplier-items.php
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

try {
    // Only allow logged in users with procurement create permissions
    $authUser->requirePermission('procurement.create');

    $supplierId = isset($_GET['supplier_id']) ? (int) $_GET['supplier_id'] : 0;
    
    if ($supplierId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid supplier ID.']);
        exit;
    }

    $inventory = new Inventory();
    $items = $inventory->getBySupplier($supplierId);

    echo json_encode([
        'success' => true,
        'data' => $items
    ]);
} catch (Exception $e) {
    error_log("Failed to fetch supplier products: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error while fetching supplier products.']);
}
