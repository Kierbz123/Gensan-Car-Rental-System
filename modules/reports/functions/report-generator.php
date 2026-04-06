<?php
/**
 * Report Generator Helper (module-level facade)
 * Wraps the ReportGenerator class with report-module specific defaults
 * Path: modules/reports/functions/report-generator.php
 */

/**
 * Get fleet utilization data for a date range
 */
function getFleetUtilizationData(string $dateFrom, string $dateTo): array
{
    $gen = new ReportGenerator();
    return $gen->getFleetUtilization($dateFrom, $dateTo);
}

/**
 * Get maintenance cost analysis
 */
function getMaintenanceCostData(string $dateFrom, string $dateTo, string $groupBy = 'vehicle'): array
{
    $gen = new ReportGenerator();
    return $gen->getMaintenanceCosts($dateFrom, $dateTo, $groupBy);
}

/**
 * Get revenue report data
 */
function getRevenueData(string $dateFrom, string $dateTo, string $period = 'daily'): array
{
    $gen = new ReportGenerator();
    return $gen->getRevenueReport($dateFrom, $dateTo, $period);
}

/**
 * Get customer analytics data
 */
function getCustomerAnalyticsData(string $dateFrom, string $dateTo): array
{
    $gen = new ReportGenerator();
    return $gen->getCustomerAnalytics($dateFrom, $dateTo);
}

/**
 * Get procurement summary data
 */
function getProcurementSummaryData(string $dateFrom, string $dateTo): array
{
    $db = Database::getInstance();

    $summary = $db->fetchAll(
        "SELECT status, COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS total
         FROM procurement_requests
         WHERE created_at BETWEEN ? AND ?
         GROUP BY status",
        [$dateFrom, $dateTo]
    );

    $topItems = $db->fetchAll(
        "SELECT pri.item_name, SUM(pri.quantity) AS total_qty,
                SUM(pri.quantity * pri.unit_price) AS total_value
         FROM procurement_request_items pri
         JOIN procurement_requests pr ON pri.procurement_id = pr.procurement_id
         WHERE pr.created_at BETWEEN ? AND ? AND pr.status NOT IN ('cancelled','draft')
         GROUP BY pri.item_name
         ORDER BY total_value DESC
         LIMIT 10",
        [$dateFrom, $dateTo]
    );

    return ['summary' => $summary, 'top_items' => $topItems];
}
