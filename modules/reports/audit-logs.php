<?php
// modules/reports/audit-logs.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('compliance.view');

$db = Database::getInstance();

// Filters
$filterAction = $_GET['action'] ?? '';
$filterModule = $_GET['module'] ?? '';
$filterUser = $_GET['user_id'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($filterAction) {
    $where[] = 'al.action = ?';
    $params[] = $filterAction;
}
if ($filterModule) {
    $where[] = 'al.module = ?';
    $params[] = $filterModule;
}
if ($filterUser) {
    $where[] = 'al.user_id = ?';
    $params[] = $filterUser;
}
if ($filterDateFrom) {
    $where[] = 'al.action_timestamp >= ?';
    $params[] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo) {
    $where[] = 'al.action_timestamp <= ?';
    $params[] = $filterDateTo . ' 23:59:59';
}
if ($search) {
    $where[] = '(al.record_description LIKE ? OR al.record_id LIKE ? OR al.ip_address LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s]);
}

$whereClause = implode(' AND ', $where);

try {
    $totalCount = (int) ($db->fetchColumn("SELECT COUNT(*) FROM audit_logs al WHERE {$whereClause}", $params) ?? 0);
    $logs = $db->fetchAll(
        "
        SELECT al.*,
               COALESCE(CONCAT(u.first_name, ' ', u.last_name), al.user_name) as user_display_name,
               COALESCE(u.role, al.user_role) as user_display_role
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE {$whereClause}
        ORDER BY al.action_timestamp DESC
        LIMIT {$perPage} OFFSET {$offset}",
        $params
    ) ?: [];

    // Distinct modules and action types for filter dropdowns
    $modules = $db->fetchAll("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module") ?: [];
    $actionTypes = $db->fetchAll("SELECT DISTINCT action FROM audit_logs WHERE action IS NOT NULL ORDER BY action") ?: [];

} catch (Exception $e) {
    error_log("Audit log error: " . $e->getMessage());
    $logs = [];
    $totalCount = 0;
    $modules = [];
    $actionTypes = [];
}

$totalPages = $totalCount > 0 ? ceil($totalCount / $perPage) : 1;

// ── CSV EXPORT — early exit before any HTML output ──────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Re-fetch without LIMIT so we export ALL matching rows, not just current page
    try {
        $allLogs = $db->fetchAll(
            "SELECT al.*,
                    COALESCE(CONCAT(u.first_name, ' ', u.last_name), al.user_name) as user_display_name,
                    COALESCE(u.role, al.user_role) as user_display_role
             FROM audit_logs al
             LEFT JOIN users u ON al.user_id = u.user_id
             WHERE {$whereClause}
             ORDER BY al.action_timestamp DESC",
            $params
        ) ?: [];
    } catch (Exception $e) {
        $allLogs = $logs; // fallback to already-fetched page
    }
    if (ob_get_level())
        ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Timestamp', 'User', 'Role', 'Action', 'Module', 'Record ID', 'Description', 'IP Address', 'Severity']);
    foreach ($allLogs as $log) {
        fputcsv($out, [
            $log['action_timestamp'],
            $log['user_display_name'] ?? 'System',
            $log['user_display_role'] ?? '',
            $log['action'] ?? '',
            $log['module'] ?? '',
            $log['record_id'] ?? '',
            $log['record_description'] ?? '',
            $log['ip_address'] ?? '',
            $log['severity'] ?? 'info',
        ]);
    }
    fclose($out);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle = "Audit Log — Event Forensics";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Event Forensics — Audit Trail</h1>
        <p>Complete immutable log of all system interactions and data mutations.</p>
    </div>
    <div class="page-actions">
        <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>"
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
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control"
                    placeholder="Description, record ID, IP...">
                <select name="action" class="form-control form-control--inline">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $at): ?>
                        <option value="<?php echo $at['action']; ?>" <?php echo $filterAction === $at['action'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $at['action'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="module" class="form-control form-control--inline">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?php echo $m['module']; ?>" <?php echo $filterModule === $m['module'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $m['module'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>"
                    class="form-control form-control--inline">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>"
                    class="form-control form-control--inline">
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="audit-logs.php" class="btn btn-ghost btn-sm" title="Reset Filters">
                        <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <?php if ($totalCount > 0): ?>
            <div style="padding:0.5rem 1rem 0;font-size:0.75rem;color:var(--text-muted);font-weight:600;">
                <?php echo number_format($totalCount); ?> event<?php echo $totalCount !== 1 ? 's' : ''; ?> found
                <?php if ($filterAction || $filterModule || $search || $filterDateFrom): ?>
                    &middot; <span style="color:var(--primary);">Filtered</span>
                <?php endif; ?>
                &middot; Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </div>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Record</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th style="text-align:center;">Severity</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:3rem;color:var(--text-muted);">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;">
                                <i data-lucide="shield-check" style="width:48px;height:48px;opacity:0.3;"></i>
                                <div style="font-weight:600;font-size:1rem;">No audit events found</div>
                                <div style="font-size:0.875rem;">Try adjusting your filter criteria.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($logs as $log): ?>
                    <?php
                    $severityClass = match ($log['severity'] ?? 'info') {
                        'critical' => 'badge-danger',
                        'warning' => 'badge-warning',
                        'info' => 'badge-info',
                        default => 'badge-secondary'
                    };
                    $actionColorMap = [
                        'create' => 'var(--success)',
                        'delete' => 'var(--danger)',
                        'update' => 'var(--warning)',
                        'login' => 'var(--accent)',
                        'logout' => 'var(--accent)',
                        'approve' => 'var(--success)',
                        'reject' => 'var(--danger)',
                        'cancel' => 'var(--text-muted)',
                        'export' => 'var(--info)',
                        'complete' => 'var(--success)',
                    ];
                    $actionColor = $actionColorMap[$log['action'] ?? ''] ?? 'var(--text-muted)';
                    $initials = !empty($log['user_display_name']) ? strtoupper(substr($log['user_display_name'], 0, 1)) : '?';
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:0.8rem;">
                                <?php echo date('M d, Y', strtotime($log['action_timestamp'])); ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--text-muted);font-family:monospace;">
                                <?php echo date('H:i:s', strtotime($log['action_timestamp'])); ?>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <div
                                    style="width:28px;height:28px;border-radius:8px;background:var(--secondary-light);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:900;color:var(--text-secondary);flex-shrink:0;">
                                    <?php echo $initials; ?>
                                </div>
                                <div>
                                    <div style="font-size:0.8rem;font-weight:700;">
                                        <?php echo htmlspecialchars($log['user_display_name'] ?? 'System'); ?>
                                    </div>
                                    <div
                                        style="font-size:0.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">
                                        <?php echo htmlspecialchars($log['user_display_role'] ?? ''); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span
                                style="color:<?php echo $actionColor; ?>;font-weight:800;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;">
                                <?php echo htmlspecialchars($log['action'] ?? ''); ?>
                            </span>
                        </td>
                        <td>
                            <span
                                style="font-size:0.65rem;font-weight:800;color:var(--text-secondary);background:var(--secondary-light);padding:2px 8px;border-radius:4px;border:1px solid var(--border-color);text-transform:uppercase;letter-spacing:0.05em;">
                                <?php echo htmlspecialchars(str_replace('_', ' ', $log['module'] ?? '—')); ?>
                            </span>
                        </td>
                        <td style="font-size:0.75rem;font-family:monospace;color:var(--text-muted);">
                            <?php echo htmlspecialchars($log['record_id'] ?? '—'); ?>
                        </td>
                        <td style="max-width:240px;">
                            <div style="font-size:0.8rem;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px;"
                                title="<?php echo htmlspecialchars($log['record_description'] ?? ''); ?>">
                                <?php echo htmlspecialchars($log['record_description'] ?? '—'); ?>
                            </div>
                        </td>
                        <td style="font-size:0.7rem;font-family:monospace;color:var(--text-muted);">
                            <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge <?php echo $severityClass; ?>"
                                style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:900;">
                                <?php echo ucfirst($log['severity'] ?? 'info'); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div
            style="padding:1rem 1.5rem;background:var(--secondary-light);border-top:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <div
                style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;">
                Showing
                <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $totalCount)); ?>
                of <?php echo number_format($totalCount); ?>
            </div>
            <div style="display:flex;gap:4px;align-items:center;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>"
                        class="btn btn-ghost btn-sm">Prev</a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                    ?>
                    <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))); ?>"
                        class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-ghost'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>"
                        class="btn btn-ghost btn-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>