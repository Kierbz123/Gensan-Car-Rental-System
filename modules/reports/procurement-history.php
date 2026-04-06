<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();


// Filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$reportGen = new ReportGenerator();
$history = $reportGen->getProcurementHistory();

// PHP-level filtering (ReportGenerator returns all rows; filter client-side)
if ($search || $status) {
    $history = array_filter($history, function ($item) use ($search, $status) {
        if ($search) {
            $haystack = ($item['pr_number'] ?? '') . ' ' . ($item['purpose_summary'] ?? '') . ' ' . ($item['requester'] ?? '');
            if (stripos($haystack, $search) === false)
                return false;
        }
        if ($status && ($item['status'] ?? '') !== $status)
            return false;
        return true;
    });
}

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

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_level())
        ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="procurement-history-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['PR Number', 'Summary', 'Requested By', 'Cost (Est) PHP', 'Status']);
    foreach ($history as $item) {
        fputcsv($out, [
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

$pageTitle = "Procurement History";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Historical Procurement Records</h1>
        <p>Complete audit of all requisition and acquisition activities.</p>
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
                <input type="text" name="search" class="form-control" placeholder="Search PR #, summary, requester..."
                    value="<?= htmlspecialchars($search) ?>">
                <select name="status" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
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
                        <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No procurement
                            history recorded.</td>
                    </tr>
                <?php else:
                    foreach ($history as $item): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($item['pr_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['purpose_summary'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['requester'] ?? 'System') ?></td>
                            <td style="text-align:right;font-weight:600;">
                                ₱<?= number_format((float) ($item['total_estimated_cost'] ?? 0), 2) ?></td>
                            <td><?= getBadge($item['status'] ?? '', strtoupper(str_replace('_', ' ', $item['status'] ?? ''))) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>