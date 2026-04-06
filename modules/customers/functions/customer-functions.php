<?php
/**
 * Customer Functions - core helpers for customer module
 * Path: modules/customers/functions/customer-functions.php
 */

/**
 * Get customer full name from array
 */
function getCustomerFullName(array $customer): string
{
    return trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
}

/**
 * Get customer type badge HTML
 */
function getCustomerTypeBadge(string $type): string
{
    $map = [
        'walk_in' => ['Walk-in', 'badge-info'],
        'corporate' => ['Corporate', 'badge-success'],
        'online' => ['Online', 'badge-warning'],
        'repeat' => ['Repeat', 'badge-success'],
        'blacklisted' => ['Blacklisted', 'badge-danger'],
    ];
    [$label, $cls] = $map[$type] ?? [ucfirst($type), 'badge-info'];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

/**
 * Check if a customer is currently eligible to rent
 */
function isCustomerEligible(int $customerId): array
{
    $db = Database::getInstance();
    $customer = $db->fetchOne(
        "SELECT status, is_blacklisted FROM customers WHERE customer_id = ?",
        [$customerId]
    );

    if (!$customer) {
        return ['eligible' => false, 'reason' => 'Customer not found'];
    }

    if ($customer['is_blacklisted']) {
        return ['eligible' => false, 'reason' => 'Customer is blacklisted'];
    }

    if ($customer['status'] !== 'active') {
        return ['eligible' => false, 'reason' => 'Customer account is not active'];
    }

    // Check for any overdue active rentals
    $overdue = $db->fetchColumn(
        "SELECT COUNT(*) FROM rental_agreements
         WHERE customer_id = ? AND status = 'active' AND rental_end_date < CURDATE()",
        [$customerId]
    );

    if ($overdue > 0) {
        return ['eligible' => false, 'reason' => 'Customer has overdue rentals'];
    }

    return ['eligible' => true, 'reason' => null];
}
