<?php
/**
 * Schedule Functions - maintenance scheduling helpers
 * Path: modules/maintenance/functions/schedule-functions.php
 */

/**
 * Calculate next service date based on current date and interval (months)
 */
function calculateNextServiceDate(string $lastServiceDate, int $intervalMonths): string
{
    $dt = new DateTime($lastServiceDate);
    $dt->modify("+{$intervalMonths} months");
    return $dt->format('Y-m-d');
}

/**
 * Calculate next service mileage based on current and interval
 */
function calculateNextServiceMileage(int $currentMileage, int $intervalKm): int
{
    return $currentMileage + $intervalKm;
}

/**
 * Get default interval settings for service types (months, km).
 * Keys match the maintenance_schedules.service_type ENUM exactly.
 */
function getServiceTypeDefaults(string $serviceType): array
{
    $defaults = [
        'oil_change' => ['months' => 3, 'km' => 5000],
        'tire_rotation' => ['months' => 6, 'km' => 10000],
        'brake_inspection' => ['months' => 12, 'km' => 20000],
        'engine_tuneup' => ['months' => 12, 'km' => 30000],
        'transmission_service' => ['months' => 24, 'km' => 40000],
        'aircon_cleaning' => ['months' => 12, 'km' => 20000],
        'battery_check' => ['months' => 12, 'km' => 20000],
        'coolant_flush' => ['months' => 24, 'km' => 40000],
        'timing_belt' => ['months' => 48, 'km' => 60000],
        'general_checkup' => ['months' => 6, 'km' => 10000],
        'others' => ['months' => 6, 'km' => 10000],
    ];
    return $defaults[$serviceType] ?? ['months' => 6, 'km' => 10000];
}

/**
 * Get all active/scheduled maintenance records for a vehicle, sorted by urgency.
 * Uses correct column (next_due_date) and valid status values.
 *
 * @param string $vehicleId  The vehicle_id (varchar in schema, not int)
 */
function getVehicleSchedules(string $vehicleId): array
{
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT *,
            DATEDIFF(next_due_date, CURDATE()) AS days_remaining
         FROM maintenance_schedules
         WHERE vehicle_id = ? AND status IN ('scheduled', 'active', 'overdue')
         ORDER BY next_due_date ASC",
        [$vehicleId]
    );
}
