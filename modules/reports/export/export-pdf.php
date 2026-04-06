<?php
/**
 * Export Reports - PDF (placeholder - requires a PDF library like DomPDF or TCPDF)
 * Path: modules/reports/export/export-pdf.php
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/session-manager.php';

// Validate permissions
if (!isset($authUser) || !$authUser->hasPermission('reports.view')) {
    die("Access Denied");
}

$type = htmlspecialchars($_GET['type'] ?? '');
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$generator = new ReportGenerator();

$reportTitle = [
    'fleet_utilization' => 'Fleet Utilization Report',
    'maintenance_costs' => 'Maintenance Cost Analysis',
    'revenue' => 'Revenue Report',
    'customer_analytics' => 'Customer Analytics Report',
][$type] ?? 'Report';

$data = match ($type) {
    'fleet_utilization' => $generator->getFleetUtilization($dateFrom, $dateTo),
    'maintenance_costs' => $generator->getMaintenanceCosts($dateFrom, $dateTo),
    'revenue' => $generator->getRevenueReport($dateFrom, $dateTo),
    'customer_analytics' => $generator->getCustomerAnalytics($dateFrom, $dateTo),
    default => [],
};

// If DomPDF is available
if (class_exists('Dompdf\Dompdf')) {
    $dompdf = new \Dompdf\Dompdf();
    ob_start();
    include __DIR__ . '/../../../includes/report-pdf-template.php';
    $html = ob_get_clean();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($reportTitle . ' ' . date('Y-m-d') . '.pdf');
} else {
    // Fallback: render HTML version
    header('Content-Type: text/html');
    include __DIR__ . '/../../../includes/report-pdf-template.php';
}
exit;
