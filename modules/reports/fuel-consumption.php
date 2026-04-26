<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();

// Filters
$search = trim($_GET['search'] ?? '');
$vehicle = trim($_GET['vehicle'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

// Vehicle Options (Only vehicles that have actual fuel logs)
$vehicleOptions = $db->fetchAll(
    "SELECT DISTINCT v.vehicle_id, v.brand, v.model, v.plate_number
     FROM vehicles v
     JOIN fuel_logs fl ON v.vehicle_id = fl.vehicle_id
     ORDER BY v.brand, v.model"
);

// Where Clauses
$where = ["1=1"];
$params = [];

if ($vehicle) {
    $where[] = "fl.vehicle_id = ?";
    $params[] = $vehicle;
}
if ($search) {
    $where[] = "(v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR fl.station_name LIKE ?)";
    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s);
}
if ($dateFrom) {
    $where[] = "fl.log_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "fl.log_date <= ?";
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $where);

// Count for pagination
$totalRecords = $db->fetchColumn(
    "SELECT COUNT(*) 
     FROM fuel_logs fl
     JOIN vehicles v ON fl.vehicle_id = v.vehicle_id
     WHERE {$whereClause}",
    $params
);
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// Totals for Widgets
$totals = $db->fetchOne(
    "SELECT 
        SUM(fl.total_fuel_cost) as grand_total_cost,
        SUM(fl.liters_added) as grand_total_liters,
        COUNT(*) as total_logs
     FROM fuel_logs fl
     JOIN vehicles v ON fl.vehicle_id = v.vehicle_id
     WHERE {$whereClause}",
    $params
);
$grandCost = (float)($totals['grand_total_cost'] ?? 0);
$grandLiters = (float)($totals['grand_total_liters'] ?? 0);

// Data Query with Subquery for Previous Odometer (To calculate distance and Km/L)
$dataParams = array_merge($params, [$perPage, $offset]);
$rawLogs = $db->fetchAll(
    "SELECT 
        fl.*, 
        v.plate_number, v.brand, v.model,
        (SELECT odometer_reading 
         FROM fuel_logs prev 
         WHERE prev.vehicle_id = fl.vehicle_id 
           AND (prev.log_date < fl.log_date OR (prev.log_date = fl.log_date AND prev.fuel_log_id < fl.fuel_log_id))
         ORDER BY prev.log_date DESC, prev.fuel_log_id DESC 
         LIMIT 1) as prev_odo
     FROM fuel_logs fl
     JOIN vehicles v ON fl.vehicle_id = v.vehicle_id
     WHERE {$whereClause}
     ORDER BY fl.log_date DESC, fl.fuel_log_id DESC
     LIMIT ? OFFSET ?",
    $dataParams
) ?: [];

// Process calculations (Km/L, Distance, Cost/Km)
$fuelLogs = [];
$totalTrackedDistance = 0;
$totalTrackedLiters = 0;

foreach ($rawLogs as $log) {
    $distance = null;
    $kml = null;
    $costPerKm = null;
    
    if (!empty($log['prev_odo']) && $log['odometer_reading'] > $log['prev_odo']) {
        $distance = $log['odometer_reading'] - $log['prev_odo'];
        
        if ($log['liters_added'] > 0) {
            $kml = $distance / $log['liters_added'];
            // Accumulate for global average
            $totalTrackedDistance += $distance;
            $totalTrackedLiters += $log['liters_added'];
        }
        if ($distance > 0) {
            $costPerKm = $log['total_fuel_cost'] / $distance;
        }
    }
    
    $log['distance'] = $distance;
    $log['kml'] = $kml;
    $log['cost_per_km'] = $costPerKm;
    $fuelLogs[] = $log;
}

$avgKml = ($totalTrackedLiters > 0) ? ($totalTrackedDistance / $totalTrackedLiters) : 0;

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportLogs = $db->fetchAll(
        "SELECT 
            fl.*, v.plate_number, v.brand, v.model,
            (SELECT odometer_reading FROM fuel_logs prev 
             WHERE prev.vehicle_id = fl.vehicle_id 
               AND (prev.log_date < fl.log_date OR (prev.log_date = fl.log_date AND prev.fuel_log_id < fl.fuel_log_id))
             ORDER BY prev.log_date DESC, prev.fuel_log_id DESC LIMIT 1) as prev_odo
         FROM fuel_logs fl
         JOIN vehicles v ON fl.vehicle_id = v.vehicle_id
         WHERE {$whereClause}
         ORDER BY fl.log_date DESC, fl.fuel_log_id DESC",
        $params
    ) ?: [];

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fuel-consumption-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Vehicle', 'Station', 'Odometer', 'Distance (km)', 'Liters', 'Cost (PHP)', 'Km/L', 'Cost/Km']);
    
    foreach ($exportLogs as $exp) {
        $ts = strtotime($exp['log_date']);
        if (!$ts) $ts = time();
        $cleanDate = '="' . date('Y-m-d', $ts) . '"'; 
        
        $dist = null; $kmlVal = null; $cpkVal = null;
        if (!empty($exp['prev_odo']) && $exp['odometer_reading'] > $exp['prev_odo']) {
            $dist = $exp['odometer_reading'] - $exp['prev_odo'];
            if ($exp['liters_added'] > 0) $kmlVal = $dist / $exp['liters_added'];
            if ($dist > 0) $cpkVal = $exp['total_fuel_cost'] / $dist;
        }

        fputcsv($out, [
            $cleanDate,
            $exp['brand'] . ' ' . $exp['model'] . ' (' . $exp['plate_number'] . ')',
            $exp['station_name'] ?? 'Unknown',
            $exp['odometer_reading'],
            $dist !== null ? $dist : 'N/A',
            (float) ($exp['liters_added'] ?? 0),
            (float) ($exp['total_fuel_cost'] ?? 0),
            $kmlVal !== null ? number_format($kmlVal, 2, '.', '') : 'N/A',
            $cpkVal !== null ? number_format($cpkVal, 2, '.', '') : 'N/A'
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Fuel Consumption & Efficiency";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Fuel Consumption & Efficiency</h1>
        <p>Analyze fuel logs, calculate Km/L metrics, and monitor fleet energy expenditures.</p>
    </div>
    <div class="page-actions">
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"
            class="btn btn-secondary">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV
        </a>
        <a href="index.php" class="btn btn-outline-primary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Reports Hub
        </a>
    </div>
</div>

<!-- KPI Dashboard Widgets -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Fuel Spend</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                ₱<?= number_format($grandCost, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Across filtered records</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #10b981;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Average Efficiency</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= $avgKml > 0 ? number_format($avgKml, 2) . ' <span style="font-size:1rem; font-weight:600; color:var(--text-muted);">Km/L</span>' : 'N/A' ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Based on calculable distances</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #f59e0b;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Volume Consumed</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= number_format($grandLiters, 1) ?> <span style="font-size:1rem; font-weight:600; color:var(--text-muted);">Liters</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;"><?= number_format($totals['total_logs']) ?> fuel events logged</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <input type="text" name="search" class="form-control" placeholder="Search plate, brand, station..."
                    value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
                
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" title="Start Date">
                <span style="color:var(--text-muted); font-size: 0.875rem;">to</span>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" title="End Date">

                <select name="vehicle" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Vehicles</option>
                    <?php foreach ($vehicleOptions as $v): ?>
                        <option value="<?= htmlspecialchars($v['vehicle_id']) ?>" <?= $vehicle === $v['vehicle_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['plate_number'] . ' - ' . $v['brand'] . ' ' . $v['model']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                    <a href="fuel-consumption.php" class="btn btn-ghost btn-sm" title="Reset Filters">
                        <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Vehicle</th>
                    <th style="text-align:right;">Odometer</th>
                    <th style="text-align:right;">Dist (km)</th>
                    <th style="text-align:right;">Liters</th>
                    <th style="text-align:right;">Total Cost</th>
                    <th style="text-align:right; border-left: 2px solid var(--bg-body);">Km/L</th>
                    <th style="text-align:right;">Cost/Km</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fuelLogs)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted);">No fuel logs
                            recorded for these filters. Note: Efficiency metrics require at least two logs per vehicle.</td>
                    </tr>
                <?php else:
                    foreach ($fuelLogs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; color: var(--text-muted); font-size: 0.875rem;">
                                <?= date('M d, Y', strtotime($log['log_date'])) ?>
                            </td>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($log['brand'] . ' ' . $log['model']) ?> 
                                <span style="display:block; font-size:0.75rem; color:var(--text-muted); font-weight:400;">
                                    <?= htmlspecialchars($log['plate_number']) ?>
                                </span>
                            </td>
                            <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                <?= number_format($log['odometer_reading']) ?>
                            </td>
                            <td style="text-align:right; font-variant-numeric: tabular-nums; color: <?= $log['distance'] !== null ? 'var(--text-main)' : 'var(--text-muted)' ?>;">
                                <?= $log['distance'] !== null ? '+' . number_format($log['distance']) : '--' ?>
                            </td>
                            <td style="text-align:right; font-weight:500;">
                                <?= number_format((float)$log['liters_added'], 1) ?>
                            </td>
                            <td style="text-align:right; font-weight:700;">
                                ₱<?= number_format((float)$log['total_fuel_cost'], 2) ?>
                            </td>
                            
                            <!-- Efficiency Metrics Highlighted -->
                            <td style="text-align:right; font-weight:800; border-left: 2px solid var(--bg-body); color: <?= $log['kml'] > 0 ? '#10b981' : 'var(--text-muted)' ?>;">
                                <?= $log['kml'] !== null ? number_format($log['kml'], 2) : 'N/A' ?>
                            </td>
                            <td style="text-align:right; font-weight:600; color: <?= $log['cost_per_km'] > 0 ? '#f59e0b' : 'var(--text-muted)' ?>;">
                                <?= $log['cost_per_km'] !== null ? '₱' . number_format($log['cost_per_km'], 2) : 'N/A' ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid var(--border-color); padding: 1rem;">
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                Showing <?= (($page - 1) * $perPage) + 1 ?> to <?= min($page * $perPage, $totalRecords) ?> of <?= $totalRecords ?> entries
            </div>
            <div style="display:flex; gap: 5px;">
                <?php
                $queryParams = $_GET;
                if ($page > 1):
                    $queryParams['page'] = $page - 1;
                ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-ghost btn-sm" style="padding: 4px 8px;">Previous</a>
                <?php endif; ?>
                
                <?php
                for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++):
                    $queryParams['page'] = $i;
                ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm" style="padding: 4px 8px;"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages):
                    $queryParams['page'] = $page + 1;
                ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-ghost btn-sm" style="padding: 4px 8px;">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>