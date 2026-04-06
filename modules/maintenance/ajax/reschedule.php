<?php
/**
 * AJAX: Reschedule a Maintenance Event
 * Path: modules/maintenance/ajax/reschedule.php
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
$newDate = $input['new_date'] ?? '';
$reason = sanitize($input['reason'] ?? '');

if (!$scheduleId || !$newDate) {
    echo json_encode(['success' => false, 'message' => 'Schedule ID and new date are required']);
    exit;
}

$db = Database::getInstance();

$db->execute(
    "UPDATE maintenance_schedules
     SET next_due_date = ?, notes = CONCAT(COALESCE(notes,''), '\nRescheduled: ', ?), updated_at = NOW()
     WHERE schedule_id = ?",
    [$newDate, $reason, $scheduleId]
);

echo json_encode(['success' => true, 'message' => 'Schedule updated to ' . $newDate]);
