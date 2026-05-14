<?php
// classes/SupplierCatalog.php

/**
 * Supplier Catalog Management Class
 * Handles the items offered by suppliers, independent of local garage inventory.
 */
class SupplierCatalog
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $id = (int) $this->db->insert(
            "INSERT INTO supplier_catalogs
             (supplier_id, vendor_item_code, item_name, item_category, unit, unit_cost, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                (int) $data['supplier_id'],
                $data['vendor_item_code'] ?? null,
                trim($data['item_name']),
                $data['item_category'] ?? 'parts',
                $data['unit'] ?? 'pcs',
                (float) ($data['unit_cost'] ?? 0),
                $data['notes'] ?? null
            ]
        );

        return $id;
    }

    public function update(int $catalogId, array $data): bool
    {
        $this->db->execute(
            "UPDATE supplier_catalogs 
             SET vendor_item_code = ?, item_name = ?, item_category = ?, unit = ?, unit_cost = ?, notes = ?
             WHERE catalog_id = ?",
            [
                $data['vendor_item_code'] ?? null,
                trim($data['item_name']),
                $data['item_category'] ?? 'parts',
                $data['unit'] ?? 'pcs',
                (float) ($data['unit_cost'] ?? 0),
                $data['notes'] ?? null,
                $catalogId
            ]
        );

        return true;
    }

    public function delete(int $catalogId): bool
    {
        $this->db->execute(
            "DELETE FROM supplier_catalogs WHERE catalog_id = ?",
            [$catalogId]
        );

        return true;
    }

    public function getById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT c.*, s.company_name AS supplier_name
             FROM supplier_catalogs c
             JOIN suppliers s ON c.supplier_id = s.supplier_id
             WHERE c.catalog_id = ?",
            [$id]
        ) ?: null;
    }

    public function getBySupplier(int $supplierId): array
    {
        return $this->db->fetchAll(
            "SELECT catalog_id, vendor_item_code, item_name, item_category, unit, unit_cost, notes
             FROM supplier_catalogs
             WHERE supplier_id = ?
             ORDER BY item_category, item_name",
            [$supplierId]
        );
    }
}
