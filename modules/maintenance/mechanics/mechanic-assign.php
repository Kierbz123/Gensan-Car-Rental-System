<?php
/**
 * Assign Mechanic to Maintenance Schedule
 * Path: modules/maintenance/mechanics/mechanic-assign.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$scheduleId = (int) ($input['schedule_id'] ?? 0);
$mechanicId = (int) ($input['mechanic_id'] ?? 0);

if (!$scheduleId || !$mechanicId) {
    echo json_encode(['success' => false, 'message' => 'Schedule and mechanic IDs required']);
    exit;
}

$db = Database::getInstance();

$db->execute(
    "UPDATE maintenance_schedules SET assigned_mechanic_id = ?, updated_at = NOW() WHERE schedule_id = ?",
    [$mechanicId, $scheduleId]
);

echo json_encode(['success' => true, 'message' => 'Mechanic assigned successfully']);
