<?php
/**
 * Audit Trail - Filter (AJAX endpoint)
 * Path: modules/compliance/audit-trail/filter-audit.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$filters = [
    'user_id' => $_GET['user_id'] ?? null,
    'action' => $_GET['action'] ?? null,
    'module' => $_GET['module'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null,
    'search' => $_GET['search'] ?? null,
    'severity' => $_GET['severity'] ?? null,
];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 50);

$result = AuditLogger::getAuditTrail($filters, $page, $perPage);

echo json_encode(['success' => true] + $result);
