<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();


// Filters
$search = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = 's.company_name LIKE ?';
    $params[] = "%{$search}%";
}

$whereClause = implode(' AND ', $where);

// Vendor stats from procurement line items
$vendorStats = $db->fetchAll(
    "SELECT
        s.company_name,
        COUNT(DISTINCT pi.pr_id) as total_requests,
        COALESCE(SUM(pi.actual_total_cost), 0) as total_value
     FROM suppliers s
     JOIN procurement_items pi ON s.supplier_id = pi.supplier_id
     WHERE {$whereClause}
     GROUP BY s.supplier_id, s.company_name
     ORDER BY total_value DESC",
    $params
) ?: [];

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_level())
        ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vendor-performance-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Vendor', 'Request Count', 'Procured Value (PHP)']);
    foreach ($vendorStats as $v) {
        fputcsv($out, [
            $v['company_name'] ?? 'Unknown Vendor',
            $v['total_requests'] ?? 0,
            number_format((float) ($v['total_value'] ?? 0), 2, '.', '')
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Vendor Performance";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Vendor Performance Analysis</h1>
        <p>Evaluating procurement throughput and supplier utilization.</p>
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
                <input type="text" name="search" class="form-control" placeholder="Search vendor name..."
                    value="<?= htmlspecialchars($search) ?>">
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="vendor-performance.php" class="btn btn-ghost btn-sm" title="Reset Filters">
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
                    <th>Vendor</th>
                    <th>Request Count</th>
                    <th style="text-align:right;">Procured Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendorStats)): ?>
                    <tr>
                        <td colspan="3" style="text-align:center;padding:2rem;color:var(--text-muted);">No vendor data
                            available.</td>
                    </tr>
                <?php else:
                    foreach ($vendorStats as $v): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($v['company_name'] ?? 'Unknown Vendor') ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($v['total_requests'] ?? 0) ?></span></td>
                            <td style="text-align:right;font-weight:600;">
                                ₱<?= number_format((float) ($v['total_value'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>