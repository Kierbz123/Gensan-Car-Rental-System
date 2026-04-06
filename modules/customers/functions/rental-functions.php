<?php
/**
 * Rental Functions - helpers for rental agreements
 * Path: modules/customers/functions/rental-functions.php
 */

/**
 * Get rental status badge HTML
 */
function getRentalStatusBadge(string $status): string
{
    $map = [
        'draft' => ['Draft', 'badge-info'],
        'reserved' => ['Reserved', 'badge-info'],
        'active' => ['Active', 'badge-success'],
        'returned' => ['Returned', 'badge-warning'],
        'completed' => ['Completed', 'badge-success'],
        'cancelled' => ['Cancelled', 'badge-danger'],
        'overdue' => ['Overdue', 'badge-danger'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'badge-info'];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

/**
 * Calculate total rental cost
 *
 * @param float  $dailyRate
 * @param string $startDate  Y-m-d
 * @param string $endDate    Y-m-d
 * @param array  $addons     ['insurance_fee' => X, ...]
 * @param float  $taxRate    e.g. 0.12 for 12%
 */
function calculateRentalTotal(
    float $dailyRate,
    string $startDate,
    string $endDate,
    array $addons = [],
    float $taxRate = 0.12
): array {
    $days = max(1, (int) (new DateTime($startDate))->diff(new DateTime($endDate))->days);
    $baseAmount = $dailyRate * $days;
    $addonTotal = array_sum($addons);
    $subtotal = $baseAmount + $addonTotal;
    $tax = round($subtotal * $taxRate, 2);
    $total = $subtotal + $tax;

    return compact('days', 'baseAmount', 'addonTotal', 'subtotal', 'tax', 'total');
}

/**
 * Format a rental agreement number as a human-readable link
 */
function getRentalLink(array $rental): string
{
    $num = htmlspecialchars($rental['agreement_number']);
    $id = (int) $rental['agreement_id'];
    return "<a href=\"" . BASE_URL . "modules/rentals/view.php?id={$id}\" class=\"text-blue-600 hover:underline\">{$num}</a>";
}

