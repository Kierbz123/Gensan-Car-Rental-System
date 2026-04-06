<?php
/**
 * Maintenance Functions - module helpers
 * Path: modules/maintenance/functions/maintenance-functions.php
 */

/**
 * Get human-readable service type label
 * Keys match the maintenance_schedules.service_type ENUM exactly.
 */
function getServiceTypeLabel(string $type): string
{
    $labels = [
        'oil_change' => 'Oil Change',
        'tire_rotation' => 'Tire Rotation',
        'brake_inspection' => 'Brake Inspection',
        'engine_tuneup' => 'Engine Tune-Up',
        'transmission_service' => 'Transmission Service',
        'aircon_cleaning' => 'Aircon Cleaning',
        'battery_check' => 'Battery Check',
        'coolant_flush' => 'Coolant Flush',
        'timing_belt' => 'Timing Belt',
        'general_checkup' => 'General Checkup',
        'others' => 'Others',
    ];
    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

/**
 * Get maintenance status badge HTML
 * Status values match the maintenance_schedules.status ENUM exactly.
 */
function getMaintenanceStatusBadge(string $status): string
{
    $map = [
        'scheduled' => ['Scheduled', 'badge-info'],
        'active' => ['Active', 'badge-primary'],
        'in_progress' => ['In Progress', 'badge-warning'],
        'overdue' => ['Overdue', 'badge-danger'],
        'completed' => ['Completed', 'badge-success'],
        'paused' => ['Paused', 'badge-secondary'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'badge-secondary'];
    return "<span class=\"badge {$cls}\">" . htmlspecialchars($label) . "</span>";
}

/**
 * Determine if a vehicle should trigger a maintenance alert
 */
function shouldTriggerMaintenanceAlert(string $vehicleId): bool
{
    $db = Database::getInstance();
    $overdue = $db->fetchColumn(
        "SELECT COUNT(*)
         FROM maintenance_schedules
         WHERE vehicle_id = ? AND next_due_date < CURDATE() AND status IN ('scheduled','active')",
        [$vehicleId]
    );
    return $overdue > 0;
}
