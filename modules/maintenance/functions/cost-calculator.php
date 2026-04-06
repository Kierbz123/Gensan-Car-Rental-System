<?php
/**
 * Cost Calculator - maintenance cost helpers
 * Path: modules/maintenance/functions/cost-calculator.php
 */

/**
 * Calculate total cost of a maintenance service record
 */
function calculateMaintenanceCost(float $laborCost, float $partsCost, float $otherCosts): array
{
    $total = $laborCost + $partsCost + $otherCosts;
    return [
        'labor_cost' => round($laborCost, 2),
        'parts_cost' => round($partsCost, 2),
        'other_costs' => round($otherCosts, 2),
        'total_cost' => round($total, 2),
    ];
}

/**
 * Get total maintenance spending per vehicle in a date range
 */
function getVehicleMaintenanceTotal(int $vehicleId, string $dateFrom, string $dateTo): float
{
    $db = Database::getInstance();
    return (float) $db->fetchColumn(
        "SELECT COALESCE(SUM(total_cost), 0) FROM maintenance_logs
         WHERE vehicle_id = ? AND service_date BETWEEN ? AND ?",
        [$vehicleId, $dateFrom, $dateTo]
    );
}

/**
 * Get average cost per service type across the fleet
 */
function getAverageCostByServiceType(): array
{
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT service_type,
                COUNT(*) AS service_count,
                ROUND(AVG(total_cost),2) AS avg_cost,
                ROUND(SUM(total_cost),2) AS total_cost
         FROM maintenance_logs
         GROUP BY service_type
         ORDER BY total_cost DESC"
    );
}

/**
 * Estimate next service cost based on vehicle category and service type
 */
function estimateServiceCost(string $serviceType, int $categoryId): float
{
    // Basic heuristics - can be replaced with actual pricing table lookup
    $baseCosts = [
        'oil_change' => 1500.00,
        'brake_service' => 3500.00,
        'tire_rotation' => 800.00,
        'major_service' => 8000.00,
        'engine_overhaul' => 25000.00,
        'ac_service' => 2500.00,
    ];
    return $baseCosts[$serviceType] ?? 2000.00;
}
