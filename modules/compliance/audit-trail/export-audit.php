<?php
/**
 * Audit Trail - Export to CSV
 * Path: modules/compliance/audit-trail/export-audit.php
 */
require_once '../../../config/config.php';

require_once '../../../includes/session-manager.php';

/** @var User $authUser */
$authUser->requirePermission('compliance.view');

$filters = [
    'user_id' => $_GET['user_id'] ?? null,
    'action' => $_GET['action'] ?? null,
    'module' => $_GET['module'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null,
    'search' => $_GET['search'] ?? null,
    'severity' => $_GET['severity'] ?? null,
];

AuditLogger::export($filters, 'csv');
