<?php
/**
 * Procurement Functions - module helpers
 * Path: modules/procurement/functions/procurement-functions.php
 */

/**
 * Generate a new unique PR number
 */
function generatePRNumber(): string
{
    $prefix = 'PR-' . date('Ym') . '-';
    $db = Database::getInstance();
    $last = $db->fetchColumn(
        "SELECT pr_number FROM procurement_requests WHERE pr_number LIKE ? ORDER BY pr_id DESC LIMIT 1",
        [$prefix . '%']
    );

    $seq = $last ? ((int) substr($last, -4)) + 1 : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Get procurement category label
 */
function getProcurementCategoryLabel(string $category): string
{
    $labels = [
        'spare_parts' => 'Spare Parts',
        'consumables' => 'Consumables',
        'vehicle_unit' => 'Vehicle Unit',
        'accessories' => 'Accessories',
        'tools' => 'Tools & Equipment',
        'office' => 'Office Supplies',
        'other' => 'Other',
    ];
    return $labels[$category] ?? ucwords(str_replace('_', ' ', $category));
}

/**
 * Calculate totals for a set of PR line items
 */
function calculatePRTotals(array $items): array
{
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
    }
    $tax = round($subtotal * 0.12, 2);
    $total = round($subtotal + $tax, 2);

    return compact('subtotal', 'tax', 'total');
}

/**
 * Get open (pending/approved) PRs for dashboard summary
 */
function getOpenPRSummary(): array
{
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT status, COUNT(*) AS count, SUM(total_estimated_cost) AS total_value
         FROM procurement_requests
         WHERE status NOT IN ('received', 'cancelled')
         GROUP BY status
         ORDER BY FIELD(status,'pending_approval','approved','ordered','draft')"
    );
}
