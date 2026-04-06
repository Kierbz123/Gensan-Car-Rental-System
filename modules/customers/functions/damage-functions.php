<?php
/**
 * Damage Functions - damage report helpers
 * Path: modules/customers/functions/damage-functions.php
 */

/**
 * Severity level labels and CSS classes
 */
function getDamageSeverityBadge(int $severity): string
{
    $map = [
        1 => ['Minor', 'badge-info'],
        2 => ['Low', 'badge-info'],
        3 => ['Moderate', 'badge-warning'],
        4 => ['Severe', 'badge-danger'],
        5 => ['Critical', 'badge-danger'],
    ];
    [$label, $cls] = $map[$severity] ?? ['Unknown', 'badge-info'];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

/**
 * Get all damage reports for a rental agreement
 */
function getDamageReports(int $agreementId): array
{
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT * FROM damage_reports WHERE agreement_id = ? ORDER BY created_at DESC",
        [$agreementId]
    );
}

/**
 * Calculate total estimated repair cost for a rental
 */
function getTotalDamageCost(int $agreementId): float
{
    $db = Database::getInstance();
    return (float) $db->fetchColumn(
        "SELECT COALESCE(SUM(repair_cost_estimate), 0)
         FROM damage_reports WHERE agreement_id = ?",
        [$agreementId]
    );
}
