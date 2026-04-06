<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();



// Filters
$search = trim($_GET['search'] ?? '');
$vehicle = trim($_GET['vehicle'] ?? '');

// Fetch all vehicles that have fuel procurement items (for dropdown)
$vehicleOptions = $db->fetchAll(
    "SELECT DISTINCT v.vehicle_id, v.brand, v.model, v.plate_number
     FROM vehicles v
     JOIN procurement_items pi ON pi.vehicle_id = v.vehicle_id
     WHERE pi.item_category = 'fuel'
     ORDER BY v.brand, v.model"
);

// Build query with filters
$where = ["pi.item_category = 'fuel'"];
$params = [];

if ($vehicle) {
    $where[] = 'v.vehicle_id = ?';
    $params[] = $vehicle;
}
if ($search) {
    $where[] = '(v.brand LIKE ? OR v.model LIKE ? OR v.plate_number LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s]);
}

$whereClause = implode(' AND ', $where);

$fuelLogs = $db->fetchAll(
    "SELECT pi.*, v.plate_number, v.brand, v.model, pr.request_date
     FROM procurement_items pi
     JOIN vehicles v ON pi.vehicle_id = v.vehicle_id
     JOIN procurement_requests pr ON pi.pr_id = pr.pr_id
     WHERE {$whereClause}
     ORDER BY pr.request_date DESC",
    $params
);

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fuel-consumption-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Vehicle', 'Quantity', 'Unit', 'Unit Cost', 'Total Cost']);
    foreach ($fuelLogs as $log) {
        fputcsv($out, [
            date('Y-m-d', strtotime($log['request_date'])),
            $log['brand'] . ' ' . $log['model'] . ' (' . $log['plate_number'] . ')',
            (float) ($log['quantity'] ?? 0),
            $log['unit'] ?? '',
            (float) ($log['actual_unit_cost'] ?? $log['estimated_unit_cost'] ?? 0),
            (float) ($log['actual_total_cost'] ?? $log['estimated_total_cost'] ?? 0)
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Fuel Consumption Report";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Fuel Consumption Analysis</h1>
        <p>Monitoring fleet efficiency via fuel procurement records.</p>
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

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form">
                <input type="text" name="search" class="form-control" placeholder="Search vehicle, plate..."
                    value="<?= htmlspecialchars($search) ?>">
                <select name="vehicle" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Vehicles</option>
                    <?php foreach ($vehicleOptions as $v): ?>
                        <option value="<?= htmlspecialchars($v['vehicle_id']) ?>" <?= $vehicle == $v['vehicle_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['brand'] . ' ' . $v['model'] . ' (' . $v['plate_number'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="fuel-consumption.php" class="btn btn-ghost btn-sm">
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
                    <th>Qty (Liters)</th>
                    <th style="text-align:right;">Unit Cost</th>
                    <th style="text-align:right;">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fuelLogs)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No fuel records
                            found in procurement.</td>
                    </tr>
                <?php else:
                    foreach ($fuelLogs as $log): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($log['request_date'])) ?></td>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($log['brand'] . ' ' . $log['model'] . ' (' . $log['plate_number'] . ')') ?>
                            </td>
                            <td><?= number_format((float) ($log['quantity'] ?? 0), 2) ?>
                                <?= htmlspecialchars($log['unit'] ?? '') ?></td>
                            <td style="text-align:right;">
                                ₱<?= number_format((float) ($log['actual_unit_cost'] ?? $log['estimated_unit_cost'] ?? 0), 2) ?>
                            </td>
                            <td style="text-align:right;font-weight:600;">
                                ₱<?= number_format((float) ($log['actual_total_cost'] ?? $log['estimated_total_cost'] ?? 0), 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>