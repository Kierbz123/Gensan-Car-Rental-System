<?php
/**
 * AJAX: Get Overdue Maintenance
 * Path: modules/maintenance/ajax/get-overdue.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$schedule = new MaintenanceSchedule();
$data = $schedule->getOverdue();

echo json_encode(['success' => true, 'data' => $data, 'count' => count($data)]);
