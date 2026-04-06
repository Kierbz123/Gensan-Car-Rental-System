<?php
/**
 * PO Functions - Purchase Order helpers
 * Path: modules/procurement/functions/po-functions.php
 */

/**
 * Generate a unique Purchase Order number
 */
function generatePONumber(): string
{
    $prefix = 'PO-' . date('Y') . '-';
    $db = Database::getInstance();
    $last = $db->fetchColumn(
        "SELECT po_number FROM purchase_orders WHERE po_number LIKE ? ORDER BY po_id DESC LIMIT 1",
        [$prefix . '%']
    );

    $seq = $last ? ((int) substr($last, -5)) + 1 : 1;
    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

/**
 * Create a Purchase Order from an approved PR
 *
 * @param int    $prId
 * @param int    $createdBy  user_id
 * @return int   New PO ID
 */
function createPOFromPR(int $prId, int $createdBy): int
{
    $db = Database::getInstance();
    $pr = $db->fetchOne("SELECT * FROM procurement_requests WHERE pr_id = ?", [$prId]);
    $prItems = $db->fetchAll("SELECT * FROM procurement_items WHERE pr_id = ?", [$prId]);

    if (!$pr || $pr['status'] !== 'approved') {
        throw new Exception('PR must be approved before generating a PO');
    }

    $poNumber = generatePONumber();

    $db->execute(
        "INSERT INTO purchase_orders (po_number, pr_id, supplier_id, po_date, delivery_date,
          total_amount, status, created_by, created_at)
         VALUES (?, ?, ?, CURDATE(), ?, ?, 'pending', ?, NOW())",
        [$poNumber, $prId, $pr['supplier_id'], $pr['required_date'], $pr['total_estimated_cost'], $createdBy]
    );

    $poId = (int) $db->lastInsertId();

    foreach ($prItems as $item) {
        $db->execute(
            "INSERT INTO purchase_order_items (po_id, item_name, quantity, unit_price, total_price)
             VALUES (?, ?, ?, ?, ?)",
            [
                $poId,
                $item['item_name'],
                $item['quantity'],
                $item['unit_price'],
                $item['quantity'] * $item['unit_price']
            ]
        );
    }

    $db->execute(
        "UPDATE procurement_requests SET status = 'ordered', updated_at = NOW() WHERE pr_id = ?",
        [$prId]
    );

    return $poId;
}
