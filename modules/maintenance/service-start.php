<?php
/**
 * Service Start (Initiate Maintenance)
 * Path: modules/maintenance/service-start.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('maintenance.update');

$db = Database::getInstance();
$scheduleId = (int) ($_GET['id'] ?? 0);

if (!$scheduleId) {
    redirect('modules/maintenance/', 'Schedule ID missing.', 'error');
}

try {
    // Check if schedule exists
    $schedule = $db->fetchOne("SELECT * FROM maintenance_schedules WHERE schedule_id = ?", [$scheduleId]);
    if (!$schedule) {
        throw new Exception("Schedule not found.");
    }

    // Check if schedule is in a valid state to be initiated
    if (!in_array($schedule['status'], ['scheduled', 'active', 'overdue'])) {
        throw new Exception("This service schedule is " . htmlspecialchars($schedule['status']) . " and cannot be initiated.");
    }

    $db->beginTransaction();

    try {
        // Update schedule status to in_progress
        $db->execute(
            "UPDATE maintenance_schedules SET status = 'in_progress' WHERE schedule_id = ?",
            [$scheduleId]
        );

        // Also update vehicle status to 'maintenance'
        $db->execute(
            "UPDATE vehicles SET current_status = 'maintenance' WHERE vehicle_id = ?",
            [$schedule['vehicle_id']]
        );

        $db->commit();

        $_SESSION['success_message'] = 'Maintenance initiated. Vehicle ' . htmlspecialchars($schedule['vehicle_id']) . ' is now in workshop.';
        header('Location: service-view.php?id=' . $scheduleId);
        exit;

    } catch (Exception $txException) {
        $db->rollback();
        throw $txException;
    }

} catch (Exception $e) {
    redirect('modules/maintenance/', $e->getMessage(), 'error');
}
