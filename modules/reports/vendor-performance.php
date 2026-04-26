<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();

// Filters
$search = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = 's.company_name LIKE ?';
    $params[] = "%{$search}%";
}
if ($dateFrom) {
    $where[] = 'DATE(pr.request_date) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'DATE(pr.request_date) <= ?';
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $where);

// Base Join String
$joins = "
    FROM suppliers s
    JOIN procurement_items pi ON s.supplier_id = pi.supplier_id
    JOIN procurement_requests pr ON pi.pr_id = pr.pr_id
";

// Count for pagination
$countQuery = "
    SELECT COUNT(DISTINCT s.supplier_id) 
    {$joins}
    WHERE {$whereClause}
";
$totalRecords = $db->fetchColumn($countQuery, $params);
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// KPI Totals
$totalsQuery = "
    SELECT 
        COUNT(DISTINCT s.supplier_id) as active_vendors,
        COUNT(DISTINCT pi.pr_id) as total_transactions,
        COALESCE(SUM(COALESCE(pi.actual_total_cost, pi.estimated_total_cost)), 0) as grand_total_value
    {$joins}
    WHERE {$whereClause}
";
$totals = $db->fetchOne($totalsQuery, $params);
$activeVendors = (int)($totals['active_vendors'] ?? 0);
$grandTotalValue = (float)($totals['grand_total_value'] ?? 0);
$totalTransactions = (int)($totals['total_transactions'] ?? 0);

// Data Query
$dataParams = array_merge($params, [$perPage, $offset]);
$vendorStats = $db->fetchAll(
    "SELECT
        s.supplier_id,
        s.company_name,
        COUNT(DISTINCT pi.pr_id) as total_requests,
        COALESCE(SUM(COALESCE(pi.actual_total_cost, pi.estimated_total_cost)), 0) as total_value
     {$joins}
     WHERE {$whereClause}
     GROUP BY s.supplier_id, s.company_name
     ORDER BY total_value DESC
     LIMIT ? OFFSET ?",
    $dataParams
) ?: [];

// Identify Top Vendor
$topVendorName = !empty($vendorStats) ? $vendorStats[0]['company_name'] : 'N/A';

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportData = $db->fetchAll(
        "SELECT
            s.company_name,
            COUNT(DISTINCT pi.pr_id) as total_requests,
            COALESCE(SUM(COALESCE(pi.actual_total_cost, pi.estimated_total_cost)), 0) as total_value
         {$joins}
         WHERE {$whereClause}
         GROUP BY s.supplier_id, s.company_name
         ORDER BY total_value DESC",
        $params
    ) ?: [];

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vendor-performance-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Vendor', 'Request Count', 'Procured Value (PHP)']);
    
    foreach ($exportData as $v) {
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
        <p>Evaluating procurement throughput, supplier utilization, and vendor spending.</p>
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

<!-- Dynamic Summary Widgets -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Procured Value</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                ₱<?= number_format($grandTotalValue, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Across filtered records</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #10b981;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Active Vendors</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= number_format($activeVendors) ?> <span style="font-size:1rem; font-weight:600; color:var(--text-muted);">Suppliers</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">With fulfilled requests</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #f59e0b;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Top Value Vendor</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-top: 0.8rem; line-height: 1.2;">
                <?= htmlspecialchars($topVendorName) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Highest total spend</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <input type="text" name="search" class="form-control" placeholder="Search vendor name..."
                    value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
                
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" title="Start Date">
                <span style="color:var(--text-muted); font-size: 0.875rem;">to</span>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" title="End Date">

                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
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
                    <th style="text-align:center;">Request Count</th>
                    <th style="text-align:right;">Procured Value</th>
                    <th style="text-align:right; width: 60px;">Profile</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendorStats)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center;padding:2rem;color:var(--text-muted);">
                            No vendor data available for the selected filters. Note: Only procured items with assigned suppliers are tracked here.
                        </td>
                    </tr>
                <?php else:
                    foreach ($vendorStats as $v): ?>
                        <tr>
                            <td style="font-weight:600; color:var(--text-main);">
                                <?= htmlspecialchars($v['company_name'] ?? 'Unknown Vendor') ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-info" style="font-size:0.875rem; padding: 0.25rem 0.75rem;">
                                    <?= htmlspecialchars($v['total_requests'] ?? 0) ?>
                                </span>
                            </td>
                            <td style="text-align:right;font-weight:700; color:var(--text-main);">
                                ₱<?= number_format((float) ($v['total_value'] ?? 0), 2) ?>
                            </td>
                            <td style="text-align:right;">
                                <a href="../suppliers/supplier-view.php?id=<?= urlencode($v['supplier_id']) ?>" class="btn btn-ghost btn-sm" title="View Vendor Profile">
                                    <i data-lucide="external-link" style="width:16px;height:16px;"></i>
                                </a>
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
                Showing <?= (($page - 1) * $perPage) + 1 ?> to <?= min($page * $perPage, $totalRecords) ?> of <?= $totalRecords ?> vendors
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