<?php
/**
 * Chart Data - AJAX endpoint for report chart datasets
 * Path: modules/reports/functions/chart-data.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($authUser) || !$authUser->hasPermission('reports.view')) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$chartType = $_GET['chart'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$db = Database::getInstance();

switch ($chartType) {
    case 'fleet_status':
        $rows = $db->fetchAll(
            "SELECT current_status AS label, COUNT(*) AS value
             FROM vehicles WHERE deleted_at IS NULL GROUP BY current_status"
        );
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    case 'revenue_trend':
        $rows = $db->fetchAll(
            "SELECT DATE(created_at) AS label, SUM(total_amount) AS value
             FROM rental_agreements
             WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'
             GROUP BY DATE(created_at) ORDER BY label",
            [$dateFrom, $dateTo]
        );
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    case 'maintenance_by_type':
        $rows = $db->fetchAll(
            "SELECT service_type AS label, COUNT(*) AS value, SUM(total_cost) AS total
             FROM maintenance_logs
             WHERE service_date BETWEEN ? AND ?
             GROUP BY service_type ORDER BY total DESC",
            [$dateFrom, $dateTo]
        );
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    case 'procurement_monthly':
        $rows = $db->fetchAll(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS label,
                    COUNT(*) AS value, SUM(total_amount) AS total
             FROM procurement_requests
             WHERE created_at BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY label",
            [$dateFrom, $dateTo]
        );
        echo json_encode(['success' => true, 'data' => $rows]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown chart type']);
}
