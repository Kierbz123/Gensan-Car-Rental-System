<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission gate BEFORE header
$authUser->requirePermission('reports.view');

$db = Database::getInstance();

// Filters & Pagination
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$type = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(v.brand LIKE ? OR v.model LIKE ? OR v.plate_number LIKE ? OR cr.compliance_type LIKE ?)";
    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s);
}

if ($type) {
    $where[] = "cr.compliance_type = ?";
    $params[] = $type;
}

if ($status) {
    if ($status === 'EXPIRED') {
        $where[] = "(cr.expiry_date IS NULL OR cr.expiry_date = '0000-00-00' OR DATEDIFF(cr.expiry_date, CURDATE()) < 0)";
    } elseif ($status === 'EXPIRING') {
        $where[] = "(cr.expiry_date != '0000-00-00' AND DATEDIFF(cr.expiry_date, CURDATE()) BETWEEN 0 AND 30)";
    } elseif ($status === 'VALID') {
        $where[] = "(cr.expiry_date != '0000-00-00' AND DATEDIFF(cr.expiry_date, CURDATE()) > 30)";
    }
}

$whereClause = implode(' AND ', $where);

// KPI Query
$kpiSql = "SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN cr.expiry_date IS NULL OR cr.expiry_date = '0000-00-00' OR DATEDIFF(cr.expiry_date, CURDATE()) < 0 THEN 1 ELSE 0 END) as total_expired,
    SUM(CASE WHEN cr.expiry_date != '0000-00-00' AND DATEDIFF(cr.expiry_date, CURDATE()) BETWEEN 0 AND 30 THEN 1 ELSE 0 END) as expiring_soon
FROM compliance_records cr
JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
WHERE {$whereClause}";
$kpis = $db->fetchOne($kpiSql, $params) ?: ['total_records' => 0, 'total_expired' => 0, 'expiring_soon' => 0];

$totalRecords = (int)$kpis['total_records'];
$totalPages = ceil($totalRecords / $limit);

// Data Query
$sql = "SELECT cr.*, v.plate_number, v.brand, v.model,
        CASE 
            WHEN cr.expiry_date IS NULL OR cr.expiry_date = '0000-00-00' THEN -999999
            ELSE DATEDIFF(cr.expiry_date, CURDATE()) 
        END as days_left
        FROM compliance_records cr
        JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
        WHERE {$whereClause}
        ORDER BY days_left ASC
        LIMIT $limit OFFSET $offset";
$records = $db->fetchAll($sql, $params) ?: [];

// Process records for display
foreach ($records as &$rec) {
    if ($rec['days_left'] == -999999 || $rec['days_left'] < 0) {
        $rec['_status'] = 'EXPIRED';
        $rec['_badge'] = 'danger';
    } elseif ($rec['days_left'] <= 30) {
        $rec['_status'] = 'EXPIRING';
        $rec['_badge'] = 'warning';
    } else {
        $rec['_status'] = 'VALID';
        $rec['_badge'] = 'success';
    }
}
unset($rec);

// Unique compliance types for the Type dropdown
$allTypes = $db->fetchAll("SELECT DISTINCT compliance_type FROM compliance_records ORDER BY compliance_type") ?: [];

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="compliance-status-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Vehicle', 'Permit/Type', 'Expiry Date', 'Days Remaining', 'Status']);
    
    // Fetch all records for export (ignore pagination)
    $exportSql = "SELECT cr.*, v.plate_number, v.brand, v.model,
                  CASE WHEN cr.expiry_date IS NULL OR cr.expiry_date = '0000-00-00' THEN -999999 ELSE DATEDIFF(cr.expiry_date, CURDATE()) END as days_left
                  FROM compliance_records cr
                  JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
                  WHERE {$whereClause} ORDER BY days_left ASC";
    $exportRecords = $db->fetchAll($exportSql, $params) ?: [];

    foreach ($exportRecords as $rec) {
        $days = ($rec['days_left'] == -999999) ? 'N/A' : $rec['days_left'];
        $statusStr = ($rec['days_left'] == -999999 || $rec['days_left'] < 0) ? 'EXPIRED' : (($rec['days_left'] <= 30) ? 'EXPIRING' : 'VALID');
        $cleanDate = ($rec['expiry_date'] && $rec['expiry_date'] !== '0000-00-00') ? '="' . date('Y-m-d', strtotime($rec['expiry_date'])) . '"' : '="N/A"';
        
        $vehicle = trim(($rec['brand'] ?? '') . ' ' . ($rec['model'] ?? ''));
        fputcsv($out, [
            $vehicle . ' (' . ($rec['plate_number'] ?? 'N/A') . ')',
            ucwords(str_replace('_', ' ', $rec['compliance_type'] ?? '')),
            $cleanDate,
            $days,
            $statusStr
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
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="btn btn-secondary">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV
        </a>
        <a href="index.php" class="btn btn-outline-primary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Reports Hub
        </a>
    </div>
</div>

<!-- KPI Dashboard -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Monitored</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= number_format($kpis['total_records']) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Compliance documents tracked</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #ef4444;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Currently Expired</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444; margin-top: 0.5rem;">
                <?= number_format($kpis['total_expired']) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Requires immediate renewal</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #f59e0b;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Expiring Soon (30 Days)</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b; margin-top: 0.5rem;">
                <?= number_format($kpis['expiring_soon']) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Action required shortly</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form">
                <input type="text" name="search" class="form-control" placeholder="Search vehicle, plate, permit..." value="<?= htmlspecialchars($search) ?>">
                <select name="status" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="EXPIRED" <?= $status === 'EXPIRED' ? 'selected' : '' ?>>Expired</option>
                    <option value="EXPIRING" <?= $status === 'EXPIRING' ? 'selected' : '' ?>>Expiring Soon</option>
                    <option value="VALID" <?= $status === 'VALID' ? 'selected' : '' ?>>Valid</option>
                </select>
                <select name="type" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Permit Types</option>
                    <?php foreach ($allTypes as $t): ?>
                        <option value="<?= htmlspecialchars($t['compliance_type']) ?>" <?= $type === $t['compliance_type'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $t['compliance_type']))) ?>
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
                    <th style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No compliance records found matching your filters.</td>
                    </tr>
                <?php else:
                    foreach ($records as $rec): ?>
                        <tr>
                            <td style="font-weight:600;">
                                <a href="../asset-tracking/vehicle-details.php?id=<?= urlencode($rec['vehicle_id']) ?>" style="color:var(--text-main); text-decoration:none; display:flex; align-items:center; gap:0.5rem;">
                                    <?= htmlspecialchars(trim(($rec['brand'] ?? '') . ' ' . ($rec['model'] ?? ''))) ?> 
                                    <span style="font-family:monospace; font-size:0.75rem; color:var(--text-muted);">
                                        (<?= htmlspecialchars($rec['plate_number'] ?? 'N/A') ?>)
                                    </span>
                                </a>
                            </td>
                            <td>
                                <span style="font-size:0.7rem;font-weight:800;color:var(--text-secondary);background:var(--bg-body);padding:2px 8px;border-radius:4px;border:1px solid var(--border-color);text-transform:uppercase;letter-spacing:0.05em; white-space:nowrap;">
                                    <?= htmlspecialchars(str_replace('_', ' ', $rec['compliance_type'] ?? '')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($rec['days_left'] == -999999): ?>
                                    <span style="color:var(--text-muted); font-style:italic;">Not Specified</span>
                                <?php else: ?>
                                    <?= date('M d, Y', strtotime($rec['expiry_date'])) ?>
                                <?php endif; ?>
                            </td>
                            <td style="font-family:monospace;">
                                <?php if ($rec['days_left'] == -999999): ?>
                                    N/A
                                <?php elseif ($rec['days_left'] < 0): ?>
                                    <span style="color:var(--danger-color);"><?= abs($rec['days_left']) ?> days ago</span>
                                <?php else: ?>
                                    <?= $rec['days_left'] ?> days
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-<?= htmlspecialchars($rec['_badge']) ?>" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:800; padding: 4px 8px;">
                                    <?= htmlspecialchars($rec['_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding: 1rem 1.5rem;">
            <div class="text-sm text-muted">
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRecords) ?> of <?= $totalRecords ?> records
            </div>
            <div class="pagination" style="display: flex; gap: 0.25rem;">
                <?php if ($page > 1): ?>
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>" class="btn btn-outline-primary btn-sm">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))) ?>" 
                       class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>" class="btn btn-outline-primary btn-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>