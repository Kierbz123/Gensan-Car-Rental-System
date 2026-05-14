<?php
/**
 * Export Driver Earnings to CSV
 * Path: modules/drivers/export-earnings.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('drivers.view');

$driverId = (int)($_GET['id'] ?? 0);
if (!$driverId) {
    die("Driver ID is required.");
}

$db = Database::getInstance();
$driverObj = new Driver();
$d = $driverObj->getById($driverId);

if (!$d) {
    die("Driver not found.");
}

// Fetch all completed/returned assignments for earnings
$history = $db->fetchAll(
    "SELECT ra.agreement_number, 
            ra.rental_start_date, 
            ra.rental_end_date, 
            ra.status, 
            ra.chauffeur_fee,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
            YEAR(ra.rental_end_date) as yr,
            MONTH(ra.rental_end_date) as mo,
            DATE_FORMAT(ra.rental_end_date, '%M') as month_name
     FROM rental_agreements ra
     JOIN customers c ON ra.customer_id = c.customer_id
     WHERE ra.driver_id = ? AND ra.status IN ('completed', 'returned')
     ORDER BY ra.rental_end_date DESC",
    [$driverId]
);

// Group by Year and Month
$summary = [];
$grandTotal = 0;
foreach ($history as $h) {
    $yr = $h['yr'];
    $moStr = $h['month_name'];
    $key = $yr . '-' . str_pad($h['mo'], 2, '0', STR_PAD_LEFT);
    
    if (!isset($summary[$key])) {
        $summary[$key] = [
            'year' => $yr,
            'month' => $moStr,
            'trips' => 0,
            'earnings' => 0
        ];
    }
    $summary[$key]['trips']++;
    $summary[$key]['earnings'] += (float)$h['chauffeur_fee'];
    $grandTotal += (float)$h['chauffeur_fee'];
}

// Sort summary descending by key
krsort($summary);

$filename = "Driver_Earnings_" . preg_replace('/[^a-zA-Z0-9]/', '_', $d['full_name']) . "_" . date('Ymd') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Title
fputcsv($output, ['Driver Earnings Report']);
fputcsv($output, ['Driver Name:', $d['full_name']]);
fputcsv($output, ['Employee Code:', $d['employee_code']]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, []);

// Summary Section
fputcsv($output, ['--- MONTHLY & YEARLY SUMMARY ---']);
fputcsv($output, ['Year', 'Month', 'Total Trips', 'Total Earnings']);

foreach ($summary as $s) {
    fputcsv($output, [
        $s['year'],
        $s['month'],
        $s['trips'],
        number_format($s['earnings'], 2, '.', '')
    ]);
}
fputcsv($output, ['GRAND TOTAL', '', array_sum(array_column($summary, 'trips')), number_format($grandTotal, 2, '.', '')]);
fputcsv($output, []);

// Detail Section
fputcsv($output, ['--- DETAILED TRIPS ---']);
fputcsv($output, ['Agreement No.', 'Customer', 'Start Date', 'End Date', 'Status', 'Earnings']);

foreach ($history as $h) {
    fputcsv($output, [
        $h['agreement_number'],
        $h['customer_name'],
        date('Y-m-d', strtotime($h['rental_start_date'])),
        date('Y-m-d', strtotime($h['rental_end_date'])),
        ucfirst($h['status']),
        number_format((float)$h['chauffeur_fee'], 2, '.', '')
    ]);
}

fclose($output);
exit;
