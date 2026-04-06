<?php
/**
 * Approval Workflow Helper
 * Path: modules/procurement/functions/approval-workflow.php
 */

/**
 * Get current approval level for a PR based on amount
 */
function getRequiredApprovalLevel(float $amount): string
{
    if ($amount <= 5000)
        return 'supervisor';
    if ($amount <= 25000)
        return 'manager';
    if ($amount <= 100000)
        return 'director';
    return 'executive';
}

/**
 * Check if the current user can approve a given PR
 */
function canUserApprovePR(int $prId, int $userId): bool
{
    $db = Database::getInstance();
    $pr = $db->fetchOne(
        "SELECT total_amount, status FROM procurement_requests WHERE procurement_id = ?",
        [$prId]
    );

    if (!$pr || $pr['status'] !== 'pending_approval') {
        return false;
    }

    $user = $db->fetchOne("SELECT role FROM users WHERE user_id = ?", [$userId]);
    if (!$user)
        return false;

    $requiredLevel = getRequiredApprovalLevel((float) $pr['total_amount']);

    $roleMap = [
        'supervisor' => ['ROLE_SUPERVISOR', 'ROLE_FLEET_MANAGER', 'ROLE_SYSTEM_ADMIN'],
        'manager' => ['ROLE_FLEET_MANAGER', 'ROLE_GENERAL_MANAGER', 'ROLE_SYSTEM_ADMIN'],
        'director' => ['ROLE_GENERAL_MANAGER', 'ROLE_SYSTEM_ADMIN'],
        'executive' => ['ROLE_SYSTEM_ADMIN'],
    ];

    return in_array($user['role'], $roleMap[$requiredLevel] ?? []);
}

/**
 * Get PR status label and CSS badge class
 */
function getPRStatusBadge(string $status): string
{
    $map = [
        'draft' => ['Draft', 'badge-info'],
        'pending_approval' => ['Pending Approval', 'badge-warning'],
        'approved' => ['Approved', 'badge-success'],
        'rejected' => ['Rejected', 'badge-danger'],
        'ordered' => ['Ordered', 'badge-info'],
        'received' => ['Received', 'badge-success'],
        'cancelled' => ['Cancelled', 'badge-danger'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'badge-info'];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}
