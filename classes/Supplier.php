<?php
// classes/Supplier.php

/**
 * Supplier / Vendor Management Class
 * Handles CRUD and directory logic for fleet suppliers
 */
class Supplier
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -------------------------------------------------------
    // Create new supplier
    // -------------------------------------------------------
    public function create(array $data, int $createdBy): int
    {
        $maxId = (int) $this->db->fetchColumn("SELECT COALESCE(MAX(supplier_id), 0) FROM suppliers");
        $code = 'SUP-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);

        $id = $this->db->insert(
            "INSERT INTO suppliers
             (supplier_code, company_name, business_type, tax_id, category,
              address, city, province, zip_code,
              contact_person, position, phone_primary, phone_secondary, email, website,
              payment_terms, credit_limit, lead_time_days,
              is_accredited, accreditation_date, is_active, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $code,
                $data['company_name'],
                $data['business_type'] ?? 'sole_proprietor',
                $data['tax_id'] ?? null,
                $data['category'] ?? 'auto_parts',
                $data['address'] ?? '',
                $data['city'] ?? 'General Santos City',
                $data['province'] ?? 'South Cotabato',
                $data['zip_code'] ?? null,
                $data['contact_person'] ?? null,
                $data['position'] ?? null,
                $data['phone_primary'] ?? '',
                $data['phone_secondary'] ?? null,
                $data['email'] ?? null,
                $data['website'] ?? null,
                $data['payment_terms'] ?? null,
                !empty($data['credit_limit']) ? (float) $data['credit_limit'] : null,
                !empty($data['lead_time_days']) ? (int) $data['lead_time_days'] : 1,
                !empty($data['is_accredited']) ? 1 : 0,
                !empty($data['accreditation_date']) ? $data['accreditation_date'] : null,
                isset($data['is_active']) ? (int) $data['is_active'] : 1,
                $data['notes'] ?? null,
                $createdBy,
            ]
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $createdBy, null, null, 'create', 'suppliers', 'suppliers', $id,
                "Created supplier {$code} — {$data['company_name']}",
                null, json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST', '/suppliers/add', 'info'
            );
        }

        return (int) $id;
    }

    // -------------------------------------------------------
    // Update supplier record
    // -------------------------------------------------------
    public function update(int $supplierId, array $data, int $updatedBy): bool
    {
        $old = $this->getById($supplierId);

        $this->db->execute(
            "UPDATE suppliers SET
                company_name = ?, business_type = ?, tax_id = ?, category = ?,
                address = ?, city = ?, province = ?, zip_code = ?,
                contact_person = ?, position = ?, phone_primary = ?, phone_secondary = ?,
                email = ?, website = ?,
                payment_terms = ?, credit_limit = ?, lead_time_days = ?,
                is_accredited = ?, accreditation_date = ?, is_active = ?,
                notes = ?, updated_at = NOW()
             WHERE supplier_id = ?",
            [
                $data['company_name'],
                $data['business_type'] ?? 'sole_proprietor',
                $data['tax_id'] ?? null,
                $data['category'] ?? 'auto_parts',
                $data['address'] ?? '',
                $data['city'] ?? 'General Santos City',
                $data['province'] ?? 'South Cotabato',
                $data['zip_code'] ?? null,
                $data['contact_person'] ?? null,
                $data['position'] ?? null,
                $data['phone_primary'] ?? '',
                $data['phone_secondary'] ?? null,
                $data['email'] ?? null,
                $data['website'] ?? null,
                $data['payment_terms'] ?? null,
                !empty($data['credit_limit']) ? (float) $data['credit_limit'] : null,
                !empty($data['lead_time_days']) ? (int) $data['lead_time_days'] : 1,
                !empty($data['is_accredited']) ? 1 : 0,
                !empty($data['accreditation_date']) ? $data['accreditation_date'] : null,
                isset($data['is_active']) ? (int) $data['is_active'] : 1,
                $data['notes'] ?? null,
                $supplierId,
            ]
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $updatedBy, null, null, 'update', 'suppliers', 'suppliers', $supplierId,
                "Updated supplier #{$supplierId}",
                json_encode($old), json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST', '/suppliers/edit', 'info'
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Soft-delete
    // -------------------------------------------------------
    public function delete(int $supplierId, int $deletedBy): bool
    {
        $this->db->execute(
            "UPDATE suppliers SET deleted_at = NOW(), is_active = 0 WHERE supplier_id = ?",
            [$supplierId]
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $deletedBy, null, null, 'delete', 'suppliers', 'suppliers', $supplierId,
                "Deleted supplier #{$supplierId}",
                null, null,
                $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST', '/suppliers/delete', 'warning'
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Get single supplier (with procurement stats)
    // -------------------------------------------------------
    public function getById(int $supplierId): ?array
    {
        return $this->db->fetchOne(
            "SELECT s.*,
                    (SELECT COUNT(DISTINCT pi.pr_id)
                     FROM procurement_items pi WHERE pi.supplier_id = s.supplier_id) AS total_orders,
                    (SELECT COUNT(*)
                     FROM parts_inventory inv WHERE inv.supplier_id = s.supplier_id) AS linked_items
             FROM suppliers s
             WHERE s.supplier_id = ? AND s.deleted_at IS NULL",
            [$supplierId]
        ) ?: null;
    }

    // -------------------------------------------------------
    // Paginated list with filters
    // -------------------------------------------------------
    public function getAll(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['s.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where[] = "(s.company_name LIKE ? OR s.supplier_code LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ?)";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }
        if (!empty($filters['category'])) {
            $where[] = 's.category = ?';
            $params[] = $filters['category'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 's.is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        $whereClause = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM suppliers s WHERE {$whereClause}",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            "SELECT s.*
             FROM suppliers s
             WHERE {$whereClause}
             ORDER BY s.company_name
             LIMIT " . (int) $perPage . " OFFSET " . (int) $offset,
            $params
        );

        return [
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? ceil($total / $perPage) : 1,
        ];
    }

    // -------------------------------------------------------
    // Simple active list for dropdowns
    // -------------------------------------------------------
    public function getActiveList(): array
    {
        return $this->db->fetchAll(
            "SELECT supplier_id, supplier_code, company_name, category
             FROM suppliers
             WHERE deleted_at IS NULL AND is_active = 1
             ORDER BY company_name"
        );
    }

    // -------------------------------------------------------
    // Procurement history for a supplier
    // -------------------------------------------------------
    public function getProcurementHistory(int $supplierId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT pi.item_id, pi.item_description, pi.quantity, pi.unit,
                    pi.estimated_unit_cost, pi.estimated_total_cost, pi.status,
                    pr.pr_number, pr.request_date, pr.status AS pr_status
             FROM procurement_items pi
             JOIN procurement_requests pr ON pi.pr_id = pr.pr_id
             WHERE pi.supplier_id = ?
             ORDER BY pr.request_date DESC
             LIMIT ?",
            [$supplierId, $limit]
        );
    }
}
