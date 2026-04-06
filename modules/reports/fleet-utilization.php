<?php
/**
 * Fleet Utilization Report
 * Path: modules/reports/fleet-utilization.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('reports.view');
$db = Database::getInstance();

$month = $_GET['month'] ?? date('Y-m');
$search = trim($_GET['search'] ?? '');

if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
[$year, $mon] = explode('-', $month);
$start = "$year-$mon-01";
$end = date('Y-m-t', strtotime($start));
$endFull = "$end 23:59:59";
$days = (int) date('t', strtotime($start));

try {
    $vehicles = $db->fetchAll("SELECT v.vehicle_id, v.plate_number, v.brand, v.model, v.current_status,
        COUNT(ra.agreement_id) AS total_rentals,
        COALESCE(SUM(DATEDIFF(LEAST(ra.rental_end_date, ?),GREATEST(ra.rental_start_date, ?))+1),0) AS rented_days,
        COALESCE(SUM(ra.total_amount),0) AS revenue
        FROM vehicles v
        LEFT JOIN rental_agreements ra ON ra.vehicle_id=v.vehicle_id
            AND ra.rental_start_date <= ? AND ra.rental_end_date >= ?
            AND ra.status IN ('active','completed','returned')
        WHERE v.deleted_at IS NULL
        GROUP BY v.vehicle_id ORDER BY revenue DESC",
        [$end, $start, $endFull, $start]
    );
} catch (Exception $e) {
    $vehicles = [];
}

// Apply search filter
if ($search) {
    $vehicles = array_filter($vehicles, function ($v) use ($search) {
        $haystack = $v['brand'] . ' ' . $v['model'] . ' ' . $v['plate_number'];
        return stripos($haystack, $search) !== false;
    });
}

$totalRev = array_sum(array_column($vehicles, 'revenue'));
$avgUtil = count($vehicles) > 0
    ? array_sum(array_map(fn($v) => min(100, round($v['rented_days'] / $days * 100)), $vehicles)) / count($vehicles)
    : 0;

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fleet-utilization-' . $month . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Vehicle', 'Rentals', 'Rented Days', 'Utilization (%)', 'Revenue']);
    foreach ($vehicles as $v) {
        $util = min(100, round($v['rented_days'] / $days * 100));
        fputcsv($out, [
            $v['brand'] . ' ' . $v['model'] . ' (' . $v['plate_number'] . ')',
            $v['total_rentals'],
            $v['rented_days'],
            $util,
            $v['revenue']
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = 'Fleet Utilization Report';
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Fleet Utilization Report</h1>
        <p>Revenue and occupancy rate per vehicle for <?= date('F Y', strtotime($start)) ?>.</p>
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

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon success"><i data-lucide="banknote"></i></div>
        <div class="stat-value"><?= formatCurrency($totalRev) ?></div>
        <div class="stat-label">Total Revenue</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon primary"><i data-lucide="car"></i></div>
        <div class="stat-value"><?= count($vehicles) ?> units</div>
        <div class="stat-label">Fleet Size</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon warning"><i data-lucide="activity"></i></div>
        <div class="stat-value"><?= round($avgUtil) ?>%</div>
        <div class="stat-label">Avg Utilization</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form">
                <input type="text" name="search" class="form-control" placeholder="Search vehicle, plate..."
                    value="<?= htmlspecialchars($search) ?>">
                <input type="month" name="month" value="<?= htmlspecialchars($month) ?>"
                    class="form-control form-control--inline">
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="fleet-utilization.php" class="btn btn-ghost btn-sm">
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
                    <th>Vehicle</th>
                    <th style="text-align:center;">Rentals</th>
                    <th style="text-align:center;">Rented Days</th>
                    <th>Utilization</th>
                    <th style="text-align:right;">Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vehicles)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No vehicles found.
                        </td>
                    </tr>
                <?php else:
                    foreach ($vehicles as $v):
                        $util = min(100, round($v['rented_days'] / $days * 100));
                        $barColor = $util >= 70 ? 'var(--success)' : ($util >= 40 ? 'var(--primary)' : 'var(--text-muted)');
                        $textColor = $util >= 70 ? 'var(--success)' : ($util >= 40 ? 'var(--primary)' : 'var(--text-muted)');
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($v['brand'] . ' ' . $v['model']) ?></div>
                                <div
                                    style="font-size:0.7rem;font-weight:800;color:var(--primary);text-transform:uppercase;letter-spacing:0.05em;">
                                    <?= htmlspecialchars($v['plate_number']) ?>
                                </div>
                            </td>
                            <td style="text-align:center;font-weight:700;"><?= $v['total_rentals'] ?></td>
                            <td style="text-align:center;font-weight:700;"><?= $v['rented_days'] ?> / <?= $days ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div
                                        style="flex:1;height:6px;background:var(--secondary-light);border-radius:999px;overflow:hidden;">
                                        <div
                                            style="height:100%;background:<?= $barColor ?>;border-radius:999px;width:<?= $util ?>%;transition:width 0.3s ease;">
                                        </div>
                                    </div>
                                    <span
                                        style="font-size:0.75rem;font-weight:900;color:<?= $textColor ?>;min-width:36px;text-align:right;"><?= $util ?>%</span>
                                </div>
                            </td>
                            <td style="text-align:right;font-weight:900;color:var(--success);">
                                <?= formatCurrency($v['revenue']) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>