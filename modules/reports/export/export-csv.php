<?php
/**
 * Export Reports - CSV
 * Path: modules/reports/export/export-csv.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$authUser->requirePermission('reports.view');

$type = sanitize($_GET['type'] ?? '');
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$groupBy = sanitize($_GET['group_by'] ?? 'vehicle');

$generator = new ReportGenerator();
$data = [];
$headers = [];
$filename = 'report_' . $type . '_' . date('Ymd') . '.csv';

switch ($type) {
    case 'fleet_utilization':
        $report = $generator->getFleetUtilization($dateFrom, $dateTo);
        $data = $report['vehicles'];
        $headers = ['Vehicle Code', 'Plate', 'Brand', 'Model', 'Category', 'Rental Days', 'Total Days', 'Utilization %', 'Revenue'];
        break;

    case 'maintenance_costs':
        $report = $generator->getMaintenanceCosts($dateFrom, $dateTo, $groupBy);
        $data = $report['data'];
        $headers = ['Group', 'Number of Services', 'Total Cost', 'Avg Cost Per Service'];
        break;

    case 'revenue':
        $report = $generator->getRevenueReport($dateFrom, $dateTo);
        $data = $report['daily'];
        $headers = ['Date', 'Number of Rentals', 'Revenue'];
        break;

    case 'customer_analytics':
        $report = $generator->getCustomerAnalytics($dateFrom, $dateTo);
        $data = $report['top_customers'];
        $headers = ['Name', 'Total Rentals', 'Total Days', 'Total Spent', 'Avg Rental Duration'];
        break;

    default:
        http_response_code(400);
        die('Invalid report type');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, $headers);
foreach ($data as $row) {
    fputcsv($out, array_values($row));
}
fclose($out);
exit;
