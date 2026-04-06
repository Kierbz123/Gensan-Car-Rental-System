<?php
/**
 * AJAX: Get Upcoming Maintenance
 * Path: modules/maintenance/ajax/get-upcoming.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

$days = (int) ($_GET['days'] ?? 7);
$schedule = new MaintenanceSchedule();
$data = $schedule->getUpcoming($days);

echo json_encode(['success' => true, 'data' => $data, 'days' => $days]);
