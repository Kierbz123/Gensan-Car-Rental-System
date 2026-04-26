<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('compliance.view');

$db = Database::getInstance();

// Filters
$filterAction = trim($_GET['action'] ?? '');
$filterModule = trim($_GET['module'] ?? '');
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo = trim($_GET['date_to'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

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
if ($filterDateFrom) {
    $where[] = 'al.action_timestamp >= ?';
    $params[] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo) {
    $where[] = 'al.action_timestamp <= ?';
    $params[] = $filterDateTo . ' 23:59:59';
}
if ($search) {
    $where[] = '(al.record_description LIKE ? OR al.record_id LIKE ? OR al.ip_address LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s, $s);
}

$whereClause = implode(' AND ', $where);

// Count for pagination
$totalRecords = (int) ($db->fetchColumn("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id WHERE {$whereClause}", $params) ?? 0);
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// KPI Totals
$totalsQuery = "
    SELECT 
        COUNT(*) as total_events,
        SUM(CASE WHEN al.severity IN ('critical', 'warning') THEN 1 ELSE 0 END) as security_alerts,
        COUNT(DISTINCT al.user_id) as active_users
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    WHERE {$whereClause}
";
$totals = $db->fetchOne($totalsQuery, $params);
$totalEvents = (int)($totals['total_events'] ?? 0);
$securityAlerts = (int)($totals['security_alerts'] ?? 0);
$activeUsers = (int)($totals['active_users'] ?? 0);

// Data Query
$dataParams = array_merge($params, [$perPage, $offset]);
$logs = $db->fetchAll(
    "SELECT al.*,
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), al.user_name) as user_display_name,
            COALESCE(u.role, al.user_role) as user_display_role
     FROM audit_logs al
     LEFT JOIN users u ON al.user_id = u.user_id
     WHERE {$whereClause}
     ORDER BY al.action_timestamp DESC
     LIMIT ? OFFSET ?",
    $dataParams
) ?: [];

// Distinct modules and action types for filter dropdowns
$modules = $db->fetchAll("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL AND module != '' ORDER BY module") ?: [];
$actionTypes = $db->fetchAll("SELECT DISTINCT action FROM audit_logs WHERE action IS NOT NULL AND action != '' ORDER BY action") ?: [];

// ── CSV EXPORT ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
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
    
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Timestamp', 'User', 'Role', 'Action', 'Module', 'Record ID', 'Severity']);
    
    foreach ($allLogs as $log) {
        $ts = strtotime($log['action_timestamp']);
        $cleanDate = '="' . date('Y-m-d H:i:s', $ts) . '"'; 
        
        fputcsv($out, [
            $cleanDate,
            $log['user_display_name'] ?? 'System',
            $log['user_display_role'] ?? '',
            $log['action'] ?? '',
            $log['module'] ?? '',
            $log['record_id'] ?? '',
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
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Events Recorded</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= number_format($totalEvents) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Matching current filters</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #ef4444;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Critical/Warning Alerts</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= number_format($securityAlerts) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Require administrative review</div>
        </div>
    </div>
    <div class="card" style="border-left: 4px solid #10b981;">
        <div class="card-body">
            <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Unique Users Active</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem;">
                <?= number_format($activeUsers) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Triggered actions in this period</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form">
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" class="form-control"
                    placeholder="Description, record ID, IP...">
                
                <select name="action" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $at): ?>
                        <option value="<?= htmlspecialchars($at['action']); ?>" <?= $filterAction === $at['action'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $at['action']))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="module" class="form-control form-control--inline" onchange="this.form.submit()">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= htmlspecialchars($m['module']); ?>" <?= $filterModule === $m['module'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $m['module']))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom); ?>" class="form-control form-control--inline" title="Start Date">
                <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo); ?>" class="form-control form-control--inline" title="End Date">
                
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
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Record</th>
                    <th style="text-align:center;">Severity</th>
                    <th style="text-align:center;">Details</th>
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
                        'create' => '#10b981',
                        'delete' => '#ef4444',
                        'update' => '#f59e0b',
                        'login' => '#3b82f6',
                        'logout' => '#6b7280',
                        'approve' => '#10b981',
                        'reject' => '#ef4444',
                        'cancel' => '#6b7280',
                        'export' => '#3b82f6',
                        'complete' => '#10b981',
                    ];
                    $actionColor = $actionColorMap[$log['action'] ?? ''] ?? 'var(--text-muted)';
                    $initials = !empty($log['user_display_name']) ? strtoupper(substr($log['user_display_name'], 0, 1)) : '?';
                    ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <div style="font-weight:600;font-size:0.85rem; color:var(--text-main);">
                                <?= date('M d, Y', strtotime($log['action_timestamp'])); ?>
                            </div>
                            <div style="font-size:0.75rem;color:var(--text-muted);font-family:monospace;">
                                <?= date('H:i:s', strtotime($log['action_timestamp'])); ?>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <div style="width:32px;height:32px;border-radius:8px;background:var(--secondary-light);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:800;color:var(--text-main);flex-shrink:0;">
                                    <?= $initials; ?>
                                </div>
                                <div>
                                    <div style="font-size:0.875rem;font-weight:700; color:var(--text-main);">
                                        <?= htmlspecialchars($log['user_display_name'] ?? 'System'); ?>
                                    </div>
                                    <div style="font-size:0.7rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;">
                                        <?= htmlspecialchars($log['user_display_role'] ?? ''); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="color:<?= $actionColor; ?>;font-weight:800;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;">
                                <?= htmlspecialchars($log['action'] ?? ''); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-size:0.7rem;font-weight:800;color:var(--text-secondary);background:var(--bg-body);padding:2px 8px;border-radius:4px;border:1px solid var(--border-color);text-transform:uppercase;letter-spacing:0.05em; white-space:nowrap;">
                                <?= htmlspecialchars(str_replace('_', ' ', $log['module'] ?? '—')); ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-size:0.7rem;font-weight:800;color:var(--text-secondary);background:var(--bg-body);padding:2px 8px;border-radius:4px;border:1px solid var(--border-color);text-transform:uppercase;letter-spacing:0.05em; white-space:nowrap;">
                                <?= htmlspecialchars($log['record_id'] ?? '—'); ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge <?= $severityClass; ?>"
                                style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:800; padding: 4px 8px;">
                                <?= htmlspecialchars(ucfirst($log['severity'] ?? 'info')); ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleDetails(<?= $log['log_id'] ?>)" title="View Event Details">
                                <i data-lucide="file-text" style="width:16px;height:16px; color: var(--primary);"></i>
                            </button>
                        </td>
                    </tr>
                    
                    <?php 
                        $old = json_decode($log['old_values'] ?? '{}', true) ?: [];
                        $new = json_decode($log['new_values'] ?? '{}', true) ?: [];
                        $changed = json_decode($log['changed_fields'] ?? '[]', true) ?: [];
                        $hasDiff = !empty($changed);
                        $hasNewData = !empty($new) && empty($changed);
                        $hasOldData = !empty($old) && empty($changed);
                    ?>
                    <tr id="details-<?= $log['log_id'] ?>" style="display:none; background: #f8fafc;">
                        <td colspan="8" style="padding: 1.5rem; border-bottom: 2px solid var(--border-color);">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                                <!-- Context Section -->
                                <div>
                                    <div style="font-weight: 700; font-size: 0.8rem; margin-bottom: 0.5rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.05em;">
                                        Event Context
                                    </div>
                                    <div style="background: #fff; padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 0.8rem;">
                                        <div style="margin-bottom: 0.5rem;"><strong>Description:</strong> <?= htmlspecialchars($log['record_description'] ?? 'No description provided') ?></div>
                                        <div style="margin-bottom: 0.5rem;"><strong>IP Address:</strong> <span style="font-family: monospace; color: var(--text-secondary);"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></span></div>
                                        <div style="margin-bottom: 0.5rem;"><strong>User Agent:</strong> <span style="color: var(--text-muted);"><?= htmlspecialchars($log['user_agent'] ?? 'Unknown') ?></span></div>
                                        <div><strong>HTTP Request:</strong> <span style="font-family: monospace; color: var(--primary); font-weight: 600;"><?= htmlspecialchars($log['request_method'] ?? 'N/A') ?></span> <?= htmlspecialchars($log['request_url'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                                
                                <!-- Payload Section -->
                                <div>
                                    <?php if ($hasDiff): ?>
                                        <div style="font-weight: 700; font-size: 0.8rem; margin-bottom: 0.5rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.05em;">
                                            Data Mutations Applied
                                        </div>
                                        <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
                                            <table style="width: 100%; margin: 0; background: #fff; border: none;">
                                                <thead>
                                                    <tr style="background: var(--secondary-light); border-bottom: 1px solid var(--border-color);">
                                                        <th style="padding: 0.5rem; font-size: 0.75rem;">Field Name</th>
                                                        <th style="padding: 0.5rem; font-size: 0.75rem; color: #ef4444;">Previous Value</th>
                                                        <th style="padding: 0.5rem; font-size: 0.75rem; color: #10b981;">New Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($changed as $field): ?>
                                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                                            <td style="padding: 0.5rem; font-size: 0.75rem; font-family: monospace; font-weight: 700; color: var(--text-secondary);">
                                                                <?= htmlspecialchars($field) ?>
                                                            </td>
                                                            <td style="padding: 0.5rem; font-size: 0.75rem; color: #b91c1c; background: #fef2f2;">
                                                                <del><?= htmlspecialchars(is_array($old[$field] ?? '') ? json_encode($old[$field]) : (string)($old[$field] ?? '—')) ?></del>
                                                            </td>
                                                            <td style="padding: 0.5rem; font-size: 0.75rem; color: #047857; background: #ecfdf5; font-weight: 600;">
                                                                <?= htmlspecialchars(is_array($new[$field] ?? '') ? json_encode($new[$field]) : (string)($new[$field] ?? '—')) ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php elseif ($hasNewData || $hasOldData): ?>
                                        <div style="font-weight: 700; font-size: 0.8rem; margin-bottom: 0.5rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.05em;">
                                            <?= $hasNewData ? 'New Record Payload' : 'Deleted Record Payload' ?>
                                        </div>
                                        <div style="background: #1e293b; padding: 1rem; border-radius: var(--radius-md); overflow-x: auto;">
                                            <pre style="margin: 0; font-family: monospace; font-size: 0.75rem; color: #f8fafc;"><?= htmlspecialchars(json_encode($hasNewData ? $new : $old, JSON_PRETTY_PRINT)) ?></pre>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-weight: 700; font-size: 0.8rem; margin-bottom: 0.5rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.05em;">
                                            Payload Data
                                        </div>
                                        <div style="background: #fff; padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 0.8rem; color: var(--text-muted); font-style: italic;">
                                            No additional structured payload data attached to this event.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid var(--border-color); padding: 1rem;">
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                Showing <?= (($page - 1) * $perPage) + 1 ?> to <?= min($page * $perPage, $totalRecords) ?> of <?= $totalRecords ?> events
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

<script>
function toggleDetails(logId) {
    const detailsRow = document.getElementById('details-' + logId);
    if (detailsRow) {
        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = 'table-row';
        } else {
            detailsRow.style.display = 'none';
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>