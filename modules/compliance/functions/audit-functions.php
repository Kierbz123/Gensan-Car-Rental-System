<?php
/**
 * Audit Functions - helpers for audit trail module
 * Path: modules/compliance/functions/audit-functions.php
 */

/**
 * Format an audit log entry for human-readable display
 */
function formatAuditEntry(array $log): string
{
    $user = htmlspecialchars($log['user_name'] ?? 'System');
    $action = htmlspecialchars($log['action']);
    $record = htmlspecialchars($log['record_description'] ?? $log['record_id']);
    $module = htmlspecialchars($log['module']);
    return "{$user} performed {$action} on [{$module}] {$record}";
}

/**
 * Decode and diff old vs new values for display
 */
function getAuditDiff(array $log): array
{
    $old = json_decode($log['old_values'] ?? '{}', true) ?? [];
    $new = json_decode($log['new_values'] ?? '{}', true) ?? [];
    $changed = json_decode($log['changed_fields'] ?? '[]', true) ?? [];

    $diff = [];
    foreach ($changed as $field) {
        $diff[$field] = [
            'old' => $old[$field] ?? null,
            'new' => $new[$field] ?? null,
        ];
    }
    return $diff;
}

/**
 * Get the CSS badge class for an audit action type
 */
function getAuditActionBadge(string $action): string
{
    return [
        'CREATE' => 'badge-success',
        'UPDATE' => 'badge-warning',
        'DELETE' => 'badge-danger',
        'LOGIN' => 'badge-info',
        'LOGOUT' => 'badge-info',
        'EXPORT' => 'badge-info',
    ][$action] ?? 'badge-info';
}
