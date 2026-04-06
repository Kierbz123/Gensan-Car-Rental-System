<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();


// Filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$type = trim($_GET['type'] ?? '');

$reportGen = new ReportGenerator();
$complianceAll = $reportGen->getComplianceStats() ?: [];

// Compute statuses and enrich records
$compliance = [];
foreach ($complianceAll as $rec) {
    if (empty($rec['expiry_date'])) {
        $daysLeft = 0; // Default if missing
    } else {
        $daysLeft = (strtotime($rec['expiry_date']) - time()) / 86400;
    }

    if ($daysLeft < 0) {
        $statusStr = 'EXPIRED';
        $badgeType = 'danger';
    } elseif ($daysLeft < 30) {
        $statusStr = 'EXPIRING';
        $badgeType = 'warning';
    } else {
        $statusStr = 'VALID';
        $badgeType = 'success';
    }
    $rec['_status'] = $statusStr;
    $rec['_badge'] = $badgeType;
    $rec['_days_left'] = $daysLeft;
    $compliance[] = $rec;
}

// Apply filters
if ($search || $status || $type) {
    $compliance = array_filter($compliance, function ($rec) use ($search, $status, $type) {
        if ($search) {
            $haystack = $rec['brand'] . ' ' . $rec['model'] . ' ' . $rec['plate_number'] . ' ' . $rec['compliance_type'];
            if (stripos($haystack, $search) === false)
                return false;
        }
        if ($status && $rec['_status'] !== strtoupper($status))
            return false;
        if ($type && $rec['compliance_type'] !== $type)
            return false;
        return true;
    });
}

// Unique compliance types for the Type dropdown
$allTypes = array_unique(array_column($complianceAll, 'compliance_type'));
sort($allTypes);

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_level())
        ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="compliance-status-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Vehicle', 'Permit/Type', 'Expiry Date', 'Days Remaining', 'Status']);
    foreach ($compliance as $rec) {
        $vehicle = trim(($rec['brand'] ?? '') . ' ' . ($rec['model'] ?? ''));
        fputcsv($out, [
            $vehicle . ' (' . ($rec['plate_number'] ?? 'N/A') . ')',
            ucwords(str_replace('_', ' ', $rec['compliance_type'] ?? '')),
            !empty($rec['expiry_date']) ? date('Y-m-d', strtotime($rec['expiry_date'])) : 'N/A',
            ceil($rec['_days_left'] ?? 0),
            $rec['_status'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Compliance Status Report";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Regulatory Compliance Audit</h1>
        <p>Tracking registration, insurance, and permit validity across the fleet.</p>
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
                <input type="text" name="search" class="form-control" placeholder="Search vehicle, plate, permit..."
                    value="<?= htmlspecialchars($search) ?>">
                <select name="status" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="EXPIRED" <?= $status === 'EXPIRED' ? 'selected' : '' ?>>Expired</option>
                    <option value="EXPIRING" <?= $status === 'EXPIRING' ? 'selected' : '' ?>>Expiring Soon</option>
                    <option value="VALID" <?= $status === 'VALID' ? 'selected' : '' ?>>Valid</option>
                </select>
                <select name="type" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Permit Types</option>
                    <?php foreach ($allTypes as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $type === $t ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $t))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="compliance-status.php" class="btn btn-ghost btn-sm" title="Reset Filters">
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
                    <th>Permit / Type</th>
                    <th>Expiry Date</th>
                    <th>Days Remaining</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($compliance)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No compliance
                            records found.</td>
                    </tr>
                <?php else:
                    foreach ($compliance as $rec): ?>
                        <tr>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars(trim(($rec['brand'] ?? '') . ' ' . ($rec['model'] ?? '')) . ' (' . ($rec['plate_number'] ?? 'N/A') . ')') ?>
                            </td>
                            <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $rec['compliance_type'] ?? ''))) ?></td>
                            <td><?= !empty($rec['expiry_date']) ? date('M d, Y', strtotime($rec['expiry_date'])) : 'N/A' ?></td>
                            <td><?= ceil($rec['_days_left'] ?? 0) ?> days</td>
                            <td><span
                                    class="badge badge-<?= htmlspecialchars($rec['_badge'] ?? 'secondary') ?>"><?= htmlspecialchars($rec['_status'] ?? '') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>