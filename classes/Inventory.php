<?php
// classes/Inventory.php

/**
 * Parts Inventory Management Class
 * Bridges Procurement (receipts) and Maintenance (consumption)
 * Uses a double-entry ledger pattern via inventory_transactions
 */
class Inventory
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -------------------------------------------------------
    // Create a new inventory item
    // -------------------------------------------------------
    public function create(array $data, int $createdBy): int
    {
        // If no item_code was provided, auto-generate one using MAX to avoid collisions
        if (empty($data['item_code'])) {
            $maxId = (int) $this->db->fetchColumn("SELECT COALESCE(MAX(inventory_id), 0) FROM parts_inventory");
            $data['item_code'] = 'INV-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
        }

        // Final guard: ensure the code doesn't already exist (race condition safety)
        $existing = $this->db->fetchOne(
            "SELECT inventory_id FROM parts_inventory WHERE item_code = ?",
            [$data['item_code']]
        );
        if ($existing) {
            throw new Exception("Item Code '{$data['item_code']}' already exists. Please use a unique code.");
        }

        $id = (int) $this->db->insert(
            "INSERT INTO parts_inventory
             (item_code, item_name, item_category, unit, quantity_on_hand,
              reorder_level, unit_cost, supplier_id, storage_location, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['item_code'],
                $data['item_name'],
                $data['item_category'] ?? 'parts',
                $data['unit'] ?? 'pcs',
                (float) ($data['quantity_on_hand'] ?? 0),
                (float) ($data['reorder_level'] ?? 0),
                !empty($data['unit_cost']) ? (float) $data['unit_cost'] : null,
                !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                $data['storage_location'] ?? 'Main Garage',
                $data['notes'] ?? null,
                $createdBy
            ]
        );

        // Record opening stock as a receipt transaction if qty > 0
        $openingQty = (float) ($data['quantity_on_hand'] ?? 0);
        if ($openingQty > 0) {
            $this->_logTransaction(
                $id,
                'receipt',
                $openingQty,
                $openingQty,
                !empty($data['unit_cost']) ? (float) $data['unit_cost'] : null,
                'manual',
                null,
                'Opening stock',
                $createdBy
            );
        }

        return $id;
    }

    // -------------------------------------------------------
    // Receive stock (from procurement or manual)
    // -------------------------------------------------------
    public function receive(
        int $inventoryId,
        float $qty,
        ?float $unitCost,
        ?int $referenceId,
        string $refType,
        int $userId,
        ?string $notes = null
    ): bool {
        $this->db->beginTransaction();
        try {
            // Lock the row
            $item = $this->db->fetchOne(
                "SELECT * FROM parts_inventory WHERE inventory_id = ? FOR UPDATE",
                [$inventoryId]
            );
            if (!$item)
                throw new Exception("Inventory item #{$inventoryId} not found.");

            $newQty = $item['quantity_on_hand'] + $qty;

            $this->db->execute(
                "UPDATE parts_inventory SET quantity_on_hand = ?, unit_cost = COALESCE(?, unit_cost),
                 updated_at = NOW() WHERE inventory_id = ?",
                [$newQty, $unitCost, $inventoryId]
            );

            $this->_logTransaction(
                $inventoryId,
                'receipt',
                $qty,
                $newQty,
                $unitCost,
                $refType,
                $referenceId,
                $notes,
                $userId
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // -------------------------------------------------------
    // Consume stock (from maintenance)
    // -------------------------------------------------------
    public function consume(
        int $inventoryId,
        float $qty,
        ?int $referenceId,
        int $userId,
        ?string $notes = null
    ): bool {
        $this->db->beginTransaction();
        try {
            $item = $this->db->fetchOne(
                "SELECT * FROM parts_inventory WHERE inventory_id = ? FOR UPDATE",
                [$inventoryId]
            );
            if (!$item)
                throw new Exception("Inventory item not found.");
            if ($item['quantity_on_hand'] < $qty) {
                throw new Exception("Insufficient stock for '{$item['item_name']}'. Available: {$item['quantity_on_hand']} {$item['unit']}.");
            }

            $newQty = $item['quantity_on_hand'] - $qty;

            $this->db->execute(
                "UPDATE parts_inventory SET quantity_on_hand = ?, updated_at = NOW() WHERE inventory_id = ?",
                [$newQty, $inventoryId]
            );

            $this->_logTransaction(
                $inventoryId,
                'consumption',
                -$qty,
                $newQty,
                $item['unit_cost'],
                'maintenance',
                $referenceId,
                $notes,
                $userId
            );

            // Generate low-stock notification if at/below reorder level
            if ($newQty <= $item['reorder_level'] && $item['reorder_level'] > 0) {
                $this->_notifyLowStock($item, $newQty);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // -------------------------------------------------------
    // Manual adjustment (positive or negative)
    // -------------------------------------------------------
    public function adjust(int $inventoryId, float $delta, int $userId, string $notes = ''): bool
    {
        $this->db->beginTransaction();
        try {
            $item = $this->db->fetchOne(
                "SELECT * FROM parts_inventory WHERE inventory_id = ? FOR UPDATE",
                [$inventoryId]
            );
            $newQty = max(0, $item['quantity_on_hand'] + $delta);

            $this->db->execute(
                "UPDATE parts_inventory SET quantity_on_hand = ?, updated_at = NOW() WHERE inventory_id = ?",
                [$newQty, $inventoryId]
            );

            $this->_logTransaction(
                $inventoryId,
                'adjustment',
                $delta,
                $newQty,
                null,
                'manual',
                null,
                $notes ?: 'Manual adjustment',
                $userId
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // -------------------------------------------------------
    // Get single item
    // -------------------------------------------------------
    public function getById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT pi.*, s.company_name AS supplier_name, s.supplier_code
             FROM parts_inventory pi
             LEFT JOIN suppliers s ON pi.supplier_id = s.supplier_id
             WHERE pi.inventory_id = ?",
            [$id]
        ) ?: null;
    }

    // -------------------------------------------------------
    // Get products by supplier ID
    // -------------------------------------------------------
    public function getBySupplier(int $supplierId): array
    {
        return $this->db->fetchAll(
            "SELECT pi.inventory_id, pi.item_code, pi.item_name, pi.item_category, pi.unit, pi.unit_cost, pi.quantity_on_hand, pi.reorder_level
             FROM parts_inventory pi
             WHERE pi.supplier_id = ?
             ORDER BY pi.item_category, pi.item_name",
            [$supplierId]
        );
    }

    // -------------------------------------------------------
    // List all items (with optional filters & pagination)
    // -------------------------------------------------------
    public function getAll(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = 'pi.item_category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where[] = "(pi.item_name LIKE ? OR pi.item_code LIKE ?)";
            $params = array_merge($params, [$s, $s]);
        }
        if (!empty($filters['low_stock'])) {
            $where[] = 'pi.quantity_on_hand <= pi.reorder_level AND pi.reorder_level > 0';
        }
        if (!empty($filters['on_order'])) {
            $where[] = "pi.inventory_id IN (
                SELECT pinv.inventory_id
                FROM procurement_items pli 
                JOIN procurement_requests pr ON pli.pr_id = pr.pr_id 
                JOIN parts_inventory pinv ON pli.item_description = pinv.item_name
                WHERE pr.status IN ('ordered', 'approved', 'partially_received')
            )";
        }

        $whereClause = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM parts_inventory pi WHERE {$whereClause}",
            $params
        );

        $sortBy = 'pi.item_name';
        $sortOrder = 'ASC';

        if (!empty($filters['sort_by'])) {
            $allowedSorts = ['pi.item_code', 'pi.item_name', 'pi.item_category', 'pi.quantity_on_hand', 'pi.reorder_level', 'pi.unit_cost'];
            $sortByParam = $filters['sort_by'];
            if (strpos($sortByParam, '.') === false && in_array('pi.' . $sortByParam, $allowedSorts)) {
                $sortByParam = 'pi.' . $sortByParam;
            }
            if (in_array($sortByParam, $allowedSorts)) {
                $sortBy = $sortByParam;
            }
        }

        if (!empty($filters['sort_order']) && in_array(strtoupper($filters['sort_order']), ['ASC', 'DESC'])) {
            $sortOrder = strtoupper($filters['sort_order']);
        }

        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            "SELECT pi.*, s.company_name AS supplier_name,
                    IF(pi.reorder_level > 0 AND pi.quantity_on_hand <= pi.reorder_level, 1, 0) AS is_low_stock
             FROM parts_inventory pi
             LEFT JOIN suppliers s ON pi.supplier_id = s.supplier_id
             WHERE {$whereClause}
             ORDER BY {$sortBy} {$sortOrder}
             LIMIT " . (int)$perPage . " OFFSET " . (int)$offset,
            $params
        );

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    // -------------------------------------------------------
    // Count items at or below reorder level
    // -------------------------------------------------------
    public function getLowStockCount(): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM parts_inventory
             WHERE reorder_level > 0 AND quantity_on_hand <= reorder_level"
        );
    }

    // -------------------------------------------------------
    // Macro Analytics
    // -------------------------------------------------------
    public function getStats(): array
    {
        $stats = [
            'total_items' => 0,
            'low_stock' => 0,
            'stock_value' => 0,
            'on_order' => 0
        ];

        $stats['total_items'] = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM parts_inventory");
        $stats['low_stock'] = $this->getLowStockCount();
        $stats['stock_value'] = (float) $this->db->fetchColumn("SELECT SUM(quantity_on_hand * unit_cost) FROM parts_inventory");
        $stats['on_order'] = (int) $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT pinv.inventory_id) 
            FROM procurement_items pli 
            JOIN procurement_requests pr ON pli.pr_id = pr.pr_id 
            JOIN parts_inventory pinv ON pli.item_description = pinv.item_name
            WHERE pr.status IN ('ordered', 'approved', 'partially_received')"
        );

        return $stats;
    }

    // -------------------------------------------------------
    // Calculate outstanding on-order quantities for an item
    // -------------------------------------------------------
    public function getOnOrderQuantity(int $itemId): float
    {
        $itemName = $this->db->fetchColumn("SELECT item_name FROM parts_inventory WHERE inventory_id = ?", [$itemId]);
        if (!$itemName) return 0;

        return (float) $this->db->fetchColumn(
            "SELECT SUM(pli.quantity) 
             FROM procurement_items pli 
             JOIN procurement_requests pr ON pli.pr_id = pr.pr_id 
             WHERE pli.item_description = ? AND pr.status IN ('ordered', 'approved', 'partially_received')",
            [$itemName]
        );
    }

    // -------------------------------------------------------
    // Transaction history for one item
    // -------------------------------------------------------
    public function getTransactions(int $inventoryId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT t.*, CONCAT(u.first_name,' ',u.last_name) AS recorded_by
             FROM inventory_transactions t
             LEFT JOIN users u ON t.created_by = u.user_id
             WHERE t.inventory_id = ?
             ORDER BY t.created_at DESC
             LIMIT " . (int)$limit,
            [$inventoryId]
        );
    }

    // -------------------------------------------------------
    // Private: write ledger entry
    // -------------------------------------------------------
    private function _logTransaction(
        int $inventoryId,
        string $type,
        float $qty,
        float $balanceAfter,
        ?float $unitCost,
        string $refType,
        ?int $refId,
        ?string $notes,
        int $userId
    ): void {
        $this->db->execute(
            "INSERT INTO inventory_transactions
             (inventory_id, txn_type, quantity, balance_after, unit_cost,
              reference_type, reference_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $inventoryId,
                $type,
                $qty,
                $balanceAfter,
                $unitCost,
                $refType,
                $refId,
                $notes,
                $userId
            ]
        );
    }

    // -------------------------------------------------------
    // Private: push low-stock notifications
    // -------------------------------------------------------
    private function _notifyLowStock(array $item, float $newQty): void
    {
        try {
            $staffIds = $this->db->fetchAll(
                "SELECT user_id FROM users WHERE role IN ('system_admin','fleet_manager','procurement_officer') AND status = 'active'"
            );
            foreach ($staffIds as $staff) {
                $this->db->execute(
                    "INSERT INTO notifications (user_id, type, title, message, related_module, related_record_id)
                     VALUES (?, 'system_alert', ?, ?, 'inventory', ?)",
                    [
                        $staff['user_id'],
                        'Low Stock: ' . $item['item_name'],
                        "Stock for \"{$item['item_name']}\" is now {$newQty} {$item['unit']} (reorder level: {$item['reorder_level']}).",
                        $item['inventory_id']
                    ]
                );
            }
        } catch (Exception $e) {
            // Notification failure should not break main flow
            error_log('Inventory low-stock notification failed: ' . $e->getMessage());
        }
    }
}
