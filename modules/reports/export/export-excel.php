<?php
/**
 * Export Reports - Excel
 * Path: modules/reports/export/export-excel.php
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

switch ($type) {
    case 'fleet_utilization':
        $report = $generator->getFleetUtilization($dateFrom, $dateTo);
        break;
    case 'maintenance_costs':
        $report = $generator->getMaintenanceCosts($dateFrom, $dateTo, $groupBy);
        break;
    case 'revenue':
        $report = $generator->getRevenueReport($dateFrom, $dateTo);
        break;
    case 'customer_analytics':
        $report = $generator->getCustomerAnalytics($dateFrom, $dateTo);
        break;
    default:
        http_response_code(400);
        die('Invalid report type');
}

$headers = [];
if (!empty($report)) {
    $headers = array_keys((array) $report[0]);
    $headers = array_map(function ($key) {
        return ucwords(str_replace('_', ' ', $key));
    }, $headers);
} else {
    $headers = ['Message'];
    $report = [['No Data Available']];
}

$filename = 'report_' . $type . '_' . date('Ymd');
$generator->exportToExcel($report, $headers, $filename);
