<?php
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

/** @var User $authUser */
$authUser->requirePermission('compliance.view');

$filters = [
    'user_id' => $_GET['user_id'] ?? null,
    'action' => $_GET['action'] ?? null,
    'module' => $_GET['module'] ?? null,
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'search' => $_GET['search'] ?? null,
    'severity' => $_GET['severity'] ?? null,
];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$result = AuditLogger::getAuditTrail($filters, $page, $perPage);
$logs = $result['data'] ?? [];
$total = $result['total'] ?? 0;

$pageTitle = 'Audit Trail Intelligence';
require_once '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Audit Trail</h1>
        <p>Historical records of system interactions and state changes.</p>
    </div>
    <div class="page-actions">
        <a href="../../settings/index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Settings
        </a>
        <a href="export-audit.php?<?= htmlspecialchars(http_build_query($filters)) ?>" class="btn btn-secondary">
            <i data-lucide="download" style="width:16px;height:16px;"></i> Export Log
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form">
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>"
                    class="form-control">
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>"
                    class="form-control">
                <select name="action" class="form-control form-control--inline">
                    <option value="">All Actions</option>
                    <option value="CREATE" <?= $filters['action'] === 'CREATE' ? 'selected' : '' ?>>CREATE</option>
                    <option value="UPDATE" <?= $filters['action'] === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                    <option value="DELETE" <?= $filters['action'] === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                </select>
                <input type="text" name="search" class="form-control" placeholder="Description, user, ref..."
                    value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="index.php" class="btn btn-ghost btn-sm" title="Reset Filters"><i data-lucide="rotate-ccw"
                            style="width:14px;height:14px;"></i></a>
                </div>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Stakeholder</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                    <th>Severity</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)):
                    foreach ($logs as $log):
                        $sevCls = match ($log['severity'] ?? 'info') {
                            'critical' => 'badge-danger',
                            'warning' => 'badge-warning',
                            default => 'badge-info'
                        };
                        $actCls = match ($log['action'] ?? '') {
                            'CREATE' => 'badge-success',
                            'DELETE' => 'badge-danger',
                            default => 'badge-secondary'
                        };
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;">
                                    <?= !empty($log['action_timestamp']) ? date('M d, Y', strtotime($log['action_timestamp'])) : '—' ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    <?= !empty($log['action_timestamp']) ? date('H:i:s', strtotime($log['action_timestamp'])) : '' ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                </div>
                            </td>
                            <td><span
                                    class="badge <?= htmlspecialchars($actCls) ?>"><?= htmlspecialchars($log['action'] ?? '') ?></span>
                            </td>
                            <td><span
                                    class="badge badge-secondary"><?= htmlspecialchars(strtoupper($log['module'] ?? '')) ?></span>
                            </td>
                            <td style="max-width:300px; font-size:0.875rem; color:var(--text-muted);">
                                <?= htmlspecialchars($log['record_description'] ?? '') ?>
                            </td>
                            <td><span
                                    class="badge <?= htmlspecialchars($sevCls) ?>"><?= htmlspecialchars(strtoupper($log['severity'] ?? 'INFO')) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);">No logs identified
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total > $perPage): ?>
    <div style="margin-top:2rem; display:flex; justify-content:center; gap:0.5rem;">
        <?php $numPages = ceil($total / $perPage);
        for ($i = 1; $i <= min(10, $numPages); $i++): ?>
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"
                class="btn <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once '../../../includes/footer.php'; ?>