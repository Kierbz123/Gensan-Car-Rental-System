<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();

// Filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

// Build WHERE clauses for UNION query
$mWhere = ["ml.status = 'completed'"];
$pWhere = ["pr.status IN ('fully_received', 'closed')"];
$mParams = [];
$pParams = [];

if ($category === 'Maintenance') {
    $pWhere[] = '1=0'; // exclude procurement
} elseif ($category === 'Procurement') {
    $mWhere[] = '1=0'; // exclude maintenance
}

if ($search) {
    $s = "%{$search}%";
    $mWhere[] = 'ml.service_description LIKE ?';
    $mParams[] = $s;
    $pWhere[] = 'pr.purpose_summary LIKE ?';
    $pParams[] = $s;
}

if ($dateFrom) {
    $mWhere[] = 'DATE(ml.service_date) >= ?';
    $mParams[] = $dateFrom;
    $pWhere[] = 'DATE(pr.request_date) >= ?';
    $pParams[] = $dateFrom;
}

if ($dateTo) {
    $mWhere[] = 'DATE(ml.service_date) <= ?';
    $mParams[] = $dateTo;
    $pWhere[] = 'DATE(pr.request_date) <= ?';
    $pParams[] = $dateTo;
}

$mWhereClause = implode(' AND ', $mWhere);
$pWhereClause = implode(' AND ', $pWhere);

$unionParams = array_merge($mParams, $pParams);

// ── PAGINATION & TOTALS COUNT ──────────────────────────────────────────────
$countQuery = "
    SELECT COUNT(*) FROM (
        SELECT ml.log_id as id FROM maintenance_logs ml WHERE {$mWhereClause}
        UNION ALL
        SELECT pr.pr_id as id FROM procurement_requests pr WHERE {$pWhereClause}
    ) as combined_count
";
$totalRecords = $db->fetchColumn($countQuery, $unionParams);
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// ── WIDGET TOTALS ──────────────────────────────────────────────────────────
$totalsQuery = "
    SELECT 
        SUM(CASE WHEN type = 'Maintenance' THEN actual_cost ELSE 0 END) as total_maintenance,
        SUM(CASE WHEN type = 'Procurement' THEN actual_cost ELSE 0 END) as total_procurement
    FROM (
        SELECT 'Maintenance' as type, ml.total_cost as actual_cost FROM maintenance_logs ml WHERE {$mWhereClause}
        UNION ALL
        SELECT 'Procurement' as type, pr.total_estimated_cost as actual_cost FROM procurement_requests pr WHERE {$pWhereClause}
    ) as combined_totals
";
$totals = $db->fetchOne($totalsQuery, $unionParams);
$totalMaintenance = (float)($totals['total_maintenance'] ?? 0);
$totalProcurement = (float)($totals['total_procurement'] ?? 0);
$grandTotal = $totalMaintenance + $totalProcurement;

// ── MAIN DATA QUERY ────────────────────────────────────────────────────────
$dataParams = array_merge($unionParams, [$perPage, $offset]);
$expenses = $db->fetchAll(
    "SELECT 
        'Maintenance' as type, 
        ml.log_id as source_id,
        ml.service_description as description, 
        ml.total_cost as actual_cost, 
        ml.service_date as date,
        '../maintenance/view-log.php?id=' as link_base
     FROM maintenance_logs ml
     WHERE {$mWhereClause}
     UNION ALL
     SELECT 
        'Procurement' as type, 
        pr.pr_id as source_id,
        pr.purpose_summary as description, 
        pr.total_estimated_cost as actual_cost, 
        pr.request_date as date,
        '../procurement/pr-view.php?id=' as link_base
     FROM procurement_requests pr
     WHERE {$pWhereClause}
     ORDER BY date DESC
     LIMIT ? OFFSET ?",
    $dataParams
) ?: [];

// ── CSV EXPORT ─────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportExpenses = $db->fetchAll(
        "SELECT 
            'Maintenance' as type, ml.service_description as description, ml.total_cost as actual_cost, ml.service_date as date
         FROM maintenance_logs ml WHERE {$mWhereClause}
         UNION ALL
         SELECT 
            'Procurement' as type, pr.purpose_summary as description, pr.total_estimated_cost as actual_cost, pr.request_date as date
         FROM procurement_requests pr WHERE {$pWhereClause}
         ORDER BY date DESC",
        $unionParams
    ) ?: [];

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="expense-tracking-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Category', 'Description', 'Amount (PHP)']);
    
    foreach ($exportExpenses as $exp) {
        $ts = strtotime($exp['date']);
        if (!$ts) $ts = time();
        $cleanDate = '="' . date('Y-m-d', $ts) . '"'; // Fix for Excel ######## bug

        fputcsv($out, [
            $cleanDate,
            $exp['type'],
            $exp['description'] ?? 'N/A',
            number_format((float) ($exp['actual_cost'] ?? 0), 2, '.', '')
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Expense Tracking & Auditing";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Expense Tracking & Auditing</h1>
        <p>Comprehensive dashboard for monitoring fleet maintenance and operational procurement costs.</p>
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
    <div class="card" style="border-left: 4px solid #10b981;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Grand Total Expenses</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                ₱<?= number_format($grandTotal, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Across all filtered records</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #f59e0b;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Maintenance Spend</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                ₱<?= number_format($totalMaintenance, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Vehicle repairs & servicing</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #3b82f6;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Procurement Spend</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                ₱<?= number_format($totalProcurement, 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Operational acquisitions</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <input type="text" name="search" class="form-control" placeholder="Search description..."
                    value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
                
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" title="Start Date">
                <span style="color:var(--text-muted); font-size: 0.875rem;">to</span>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" title="End Date">

                <select name="category" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <option value="Maintenance" <?= $category === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    <option value="Procurement" <?= $category === 'Procurement' ? 'selected' : '' ?>>Procurement</option>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                    <a href="expense-tracking.php" class="btn btn-ghost btn-sm" title="Reset Filters">
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
                    <th>Category</th>
                    <th>Description</th>
                    <th style="text-align:right;">Amount</th>
                    <th style="text-align:right; width: 60px;">Audit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No expenses
                            recorded for these filters.</td>
                    </tr>
                <?php else:
                    foreach ($expenses as $exp): ?>
                        <tr>
                            <td style="white-space: nowrap; color: var(--text-muted); font-size: 0.875rem;">
                                <?= date('M d, Y', strtotime($exp['date'])) ?>
                            </td>
                            <td>
                                <span class="badge <?= $exp['type'] === 'Maintenance' ? 'badge-warning' : 'badge-info' ?>">
                                    <?= htmlspecialchars($exp['type']) ?>
                                </span>
                            </td>
                            <td style="font-weight: 500;"><?= htmlspecialchars($exp['description'] ?? 'N/A') ?></td>
                            <td style="text-align:right;font-weight:700; color: var(--text-main);">
                                ₱<?= number_format((float) ($exp['actual_cost'] ?? 0), 2) ?>
                            </td>
                            <td style="text-align:right;">
                                <a href="<?= htmlspecialchars($exp['link_base'] . $exp['source_id']) ?>" class="btn btn-ghost btn-sm" title="Investigate">
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