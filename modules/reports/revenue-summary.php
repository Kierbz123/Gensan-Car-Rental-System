<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();


$reportGen = new ReportGenerator();

// Filters
$search = trim($_GET['search'] ?? '');
$year = trim($_GET['year'] ?? '');

// Fetch all data once
$allData = $reportGen->getMonthlyRevenueSummary();

// Build year options from raw data
$years = array_unique(array_map(fn($r) => substr($r['month'], 0, 4), $allData));
rsort($years);

// Apply filters
$revenueMonthly = $allData;
if ($search || $year) {
    $revenueMonthly = array_filter($revenueMonthly, function ($row) use ($search, $year) {
        $monthLabel = date('F Y', strtotime($row['month'] . '-01'));
        if ($search && stripos($monthLabel, $search) === false)
            return false;
        if ($year && !str_starts_with($row['month'], $year))
            return false;
        return true;
    });
}

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue-summary-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Billing Month', 'Transaction Count', 'Gross Revenue']);
    foreach ($revenueMonthly as $row) {
        fputcsv($out, [
            date('Y-m-d', strtotime($row['month'] . '-01')),
            (int) ($row['rental_count'] ?? 0),
            (float) ($row['total_revenue'] ?? 0)
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Revenue Summary";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Revenue &amp; Operations Summary</h1>
        <p>Consolidated view of rental income and transaction volume.</p>
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
                <input type="text" name="search" class="form-control" placeholder="Search month..."
                    value="<?= htmlspecialchars($search) ?>">
                <select name="year" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= htmlspecialchars($y) ?>" <?= $year === $y ? 'selected' : '' ?>>
                            <?= htmlspecialchars($y) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="revenue-summary.php" class="btn btn-ghost btn-sm">
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
                    <th>Trans. Count</th>
                    <th style="text-align:right;">Gross Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($revenueMonthly)): ?>
                    <tr>
                        <td colspan="3" style="text-align:center;padding:2rem;color:var(--text-muted);">No revenue data
                            available.</td>
                    </tr>
                <?php else:
                    foreach ($revenueMonthly as $row): ?>
                        <tr>
                            <td style="font-weight:600;"><?= date('F Y', strtotime($row['month'] . '-01')) ?></td>
                            <td><span class="badge badge-info"><?= (int) ($row['rental_count'] ?? 0) ?></span></td>
                            <td style="text-align:right;font-weight:600;">
                                ₱<?= number_format((float) ($row['total_revenue'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>