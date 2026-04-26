<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();

// Filters
$search = trim($_GET['search'] ?? '');
$year = trim($_GET['year'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24; // Show 2 years per page for a monthly report

// Available Years for filter
$availableYears = $db->fetchAll("SELECT DISTINCT YEAR(created_at) as yr FROM rental_agreements WHERE status != 'cancelled' ORDER BY yr DESC") ?: [];

$where = ["status != 'cancelled'"];
$params = [];

if ($year) {
    $where[] = "YEAR(created_at) = ?";
    $params[] = $year;
}

$whereClause = implode(' AND ', $where);

// Base Grouping Query
$baseQuery = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month, 
        SUM(total_amount) as total_revenue,
        COUNT(*) as rental_count
    FROM rental_agreements 
    WHERE {$whereClause}
    GROUP BY month
";

// If search is active, we have to filter the grouped results.
$searchWhere = "";
if ($search) {
    $searchWhere = " WHERE DATE_FORMAT(STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d'), '%M %Y') LIKE ? ";
    $s = "%{$search}%";
    $params[] = $s;
}

$finalQuery = "
    SELECT * FROM ({$baseQuery}) as grouped_data
    {$searchWhere}
";

// Pagination
$countQuery = "SELECT COUNT(*) FROM ({$finalQuery}) as count_tbl";
$totalRecords = $db->fetchColumn($countQuery, $params);
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// KPI Totals
$totalsQuery = "SELECT SUM(total_revenue) as grand_revenue, SUM(rental_count) as grand_count FROM ({$finalQuery}) as totals_tbl";
$totals = $db->fetchOne($totalsQuery, $params);
$grandRevenue = (float)($totals['grand_revenue'] ?? 0);
$grandCount = (int)($totals['grand_count'] ?? 0);
$avgMonthlyRevenue = $totalRecords > 0 ? $grandRevenue / $totalRecords : 0;

// Data Query
$dataParams = array_merge($params, [$perPage, $offset]);
$revenueMonthly = $db->fetchAll("{$finalQuery} ORDER BY month DESC LIMIT ? OFFSET ?", $dataParams) ?: [];

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportData = $db->fetchAll("{$finalQuery} ORDER BY month DESC", $params) ?: [];
    
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue-summary-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Billing Month', 'Transaction Count', 'Gross Revenue (PHP)']);
    
    foreach ($exportData as $row) {
        $ts = strtotime($row['month'] . '-01');
        $cleanDate = '="' . date('Y-m-d', $ts) . '"'; 
        
        fputcsv($out, [
            $cleanDate,
            (int) ($row['rental_count'] ?? 0),
            number_format((float) ($row['total_revenue'] ?? 0), 2, '.', '')
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Revenue & Operations Summary";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Revenue &amp; Operations Summary</h1>
        <p>Consolidated view of rental income, transaction volume, and operational growth.</p>
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
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Gross Revenue</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                ₱<?= number_format($grandRevenue, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Across filtered months</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #10b981;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Average Monthly Revenue</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                ₱<?= number_format($avgMonthlyRevenue, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Based on <?= $totalRecords ?> active months</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #f59e0b;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Transactions</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= number_format($grandCount) ?> <span style="font-size:1rem; font-weight:600; color:var(--text-muted);">rentals</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Total successful bookings</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <input type="text" name="search" class="form-control" placeholder="Search month (e.g. April 2026)..."
                    value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
                <select name="year" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    <?php foreach ($availableYears as $y): ?>
                        <option value="<?= htmlspecialchars($y['yr']) ?>" <?= $year == $y['yr'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($y['yr']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                    <a href="revenue-summary.php" class="btn btn-ghost btn-sm" title="Reset Filters">
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
                    <th>Billing Month</th>
                    <th style="text-align:center;">Transaction Count</th>
                    <th style="text-align:right;">Gross Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($revenueMonthly)): ?>
                    <tr>
                        <td colspan="3" style="text-align:center;padding:2rem;color:var(--text-muted);">No revenue data
                            available for the selected filters.</td>
                    </tr>
                <?php else:
                    foreach ($revenueMonthly as $row): ?>
                        <tr>
                            <td style="font-weight:600; color:var(--text-main);">
                                <?= date('F Y', strtotime($row['month'] . '-01')) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-info" style="font-size:0.875rem; padding: 0.25rem 0.75rem;">
                                    <?= (int) ($row['rental_count'] ?? 0) ?>
                                </span>
                            </td>
                            <td style="text-align:right;font-weight:700; color:var(--text-main);">
                                ₱<?= number_format((float) ($row['total_revenue'] ?? 0), 2) ?>
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
                Showing <?= (($page - 1) * $perPage) + 1 ?> to <?= min($page * $perPage, $totalRecords) ?> of <?= $totalRecords ?> months
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