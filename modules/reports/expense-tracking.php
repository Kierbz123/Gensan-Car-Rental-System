<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();


// Filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// Build WHERE clauses for UNION query
$mWhere = ["ml.status = 'completed'"];
$pWhere = ["pr.status IN ('fully_received', 'closed')"];
$mParams = [];
$pParams = [];

if ($category === 'Maintenance') {
    $pWhere[] = '1=0'; // exclude procurement rows from UNION
} elseif ($category === 'Procurement') {
    $mWhere[] = '1=0'; // exclude maintenance rows from UNION
}

if ($search) {
    $s = "%{$search}%";
    $mWhere[] = 'ml.service_description LIKE ?';
    $mParams[] = $s;
    $pWhere[] = 'pr.purpose_summary LIKE ?';
    $pParams[] = $s;
}

$mWhereClause = implode(' AND ', $mWhere);
$pWhereClause = implode(' AND ', $pWhere);

$expenses = $db->fetchAll(
    "SELECT 'Maintenance' as type, ml.service_description as description, ml.total_cost as actual_cost, ml.service_date as date
     FROM maintenance_logs ml
     WHERE {$mWhereClause}
     UNION ALL
     SELECT 'Procurement' as type, pr.purpose_summary as description, pr.total_actual_cost as actual_cost, pr.request_date as date
     FROM procurement_requests pr
     WHERE {$pWhereClause}
     ORDER BY date DESC",
    array_merge($mParams, $pParams)
) ?: [];

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_level())
        ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="expense-tracking-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Category', 'Description', 'Amount (PHP)']);
    foreach ($expenses as $exp) {
        fputcsv($out, [
            date('Y-m-d', strtotime($exp['date'])),
            $exp['type'],
            $exp['description'] ?? 'N/A',
            number_format((float) ($exp['actual_cost'] ?? 0), 2, '.', '')
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Expense Tracking";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Expense Tracking</h1>
        <p>Audit of all fleet maintenance and operational procurement costs.</p>
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
                <input type="text" name="search" class="form-control" placeholder="Search description..."
                    value="<?= htmlspecialchars($search) ?>">
                <select name="category" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <option value="Maintenance" <?= $category === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    <option value="Procurement" <?= $category === 'Procurement' ? 'selected' : '' ?>>Procurement</option>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
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
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center;padding:2rem;color:var(--text-muted);">No expenses
                            recorded.</td>
                    </tr>
                <?php else:
                    foreach ($expenses as $exp): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($exp['date'])) ?></td>
                            <td>
                                <span class="badge <?= $exp['type'] === 'Maintenance' ? 'badge-warning' : 'badge-info' ?>">
                                    <?= htmlspecialchars($exp['type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($exp['description'] ?? 'N/A') ?></td>
                            <td style="text-align:right;font-weight:600;">
                                ₱<?= number_format((float) ($exp['actual_cost'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>