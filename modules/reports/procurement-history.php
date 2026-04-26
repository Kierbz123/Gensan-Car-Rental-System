<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();

// Filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$filters = [
    'search' => $search,
    'status' => $status,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
];

$reportGen = new ReportGenerator();
$historyData = $reportGen->getProcurementHistory($filters, $page, $perPage);

$history = $historyData['data'] ?? [];
$totals = $historyData['totals'] ?? [];

$statuses = [
    'draft' => 'Draft',
    'pending_approval' => 'Pending Approval',
    'approved' => 'Approved',
    'fully_received' => 'Fully Received',
    'rejected' => 'Rejected',
    'closed' => 'Closed',
    'cancelled' => 'Cancelled',
    'ordered' => 'Ordered',
    'partially_received' => 'Partially Received',
];

// Fallback status badge helper if getBadge doesn't exist globally
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $map = [
            'approved' => 'badge-success',
            'fully_received' => 'badge-success',
            'ordered' => 'badge-info',
            'pending_approval' => 'badge-warning',
            'partially_received' => 'badge-warning',
            'rejected' => 'badge-danger',
            'cancelled' => 'badge-danger',
            'closed' => 'badge-secondary',
            'draft' => 'badge-secondary'
        ];
        $class = $map[$status] ?? 'badge-secondary';
        $label = ucwords(str_replace('_', ' ', $status));
        return "<span class=\"badge {$class}\">{$label}</span>";
    }
} else {
    function getStatusBadge($status) {
        return getBadge($status, strtoupper(str_replace('_', ' ', $status)));
    }
}

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Re-fetch all data for export without pagination limit
    $exportData = $reportGen->getProcurementHistory($filters, 1, 999999);
    $historyExport = $exportData['data'];

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="procurement-history-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'PR Number', 'Summary', 'Requested By', 'Cost (Est) PHP', 'Status']);
    foreach ($historyExport as $item) {
        $rawDate = !empty($item['request_date']) ? $item['request_date'] : ($item['created_at'] ?? 'now');
        $ts = strtotime($rawDate);
        if (!$ts) $ts = time(); // Fallback if database has '0000-00-00'
        
        // The ultimate Excel trick: format as a formula string so Excel NEVER parses it as a date
        $cleanDate = '="' . date('Y-m-d', $ts) . '"';
        
        fputcsv($out, [
            $cleanDate,
            $item['pr_number'] ?? 'N/A',
            $item['purpose_summary'] ?? 'N/A',
            $item['requester'] ?? 'System',
            number_format((float) ($item['total_estimated_cost'] ?? 0), 2, '.', ''),
            strtoupper(str_replace('_', ' ', $item['status'] ?? ''))
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Procurement History & Expense Tracking";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Historical Procurement & Expenses</h1>
        <p>Complete audit of all requisition activities, expense tracking, and historical cost analysis.</p>
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

<!-- Expense Tracking & Auditing Summary Widgets -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Historical Expenses (Est.)</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                ₱<?= number_format((float)($totals['total_spent'] ?? 0), 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Total value of filtered records</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Procurement Volume</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= number_format((int)($totals['total_records'] ?? 0)) ?> Records
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">In selected date range</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <input type="text" name="search" class="form-control" placeholder="Search PR #, summary, requester..."
                    value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
                
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" title="Start Date">
                <span style="color:var(--text-muted); font-size: 0.875rem;">to</span>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" title="End Date">

                <select name="status" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                    <a href="procurement-history.php" class="btn btn-ghost btn-sm" title="Reset Filters">
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
                    <th>PR #</th>
                    <th>Summary</th>
                    <th>Requested By</th>
                    <th style="text-align:right;">Cost (Est)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">No procurement
                            history recorded for these filters.</td>
                    </tr>
                <?php else:
                    foreach ($history as $item): ?>
                        <tr>
                            <td style="white-space: nowrap; color: var(--text-muted); font-size: 0.875rem;">
                                <?= date('M d, Y', strtotime($item['request_date'] ?? $item['created_at'])) ?>
                            </td>
                            <td style="font-weight:700;">
                                <a href="../procurement/pr-view.php?id=<?= $item['pr_id'] ?>" style="color:var(--primary); text-decoration:none;">
                                    <?= htmlspecialchars($item['pr_number'] ?? 'N/A') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($item['purpose_summary'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['requester'] ?? 'System') ?></td>
                            <td style="text-align:right;font-weight:600;">
                                ₱<?= number_format((float) ($item['total_estimated_cost'] ?? 0), 2) ?>
                            </td>
                            <td><?= getStatusBadge($item['status'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($historyData['total_pages'] > 1): ?>
        <div class="card-footer" style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid var(--border-color); padding: 1rem;">
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                Showing <?= (($page - 1) * $perPage) + 1 ?> to <?= min($page * $perPage, $historyData['total']) ?> of <?= $historyData['total'] ?> entries
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
                // Simple pagination (show all pages for now, could be improved to sliding window if needed)
                for ($i = 1; $i <= $historyData['total_pages']; $i++):
                    $queryParams['page'] = $i;
                ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm" style="padding: 4px 8px;"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $historyData['total_pages']):
                    $queryParams['page'] = $page + 1;
                ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-ghost btn-sm" style="padding: 4px 8px;">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>