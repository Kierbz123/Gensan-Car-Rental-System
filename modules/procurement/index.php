<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$procurement = new ProcurementRequest();

$pageTitle = "Procurement Control";
require_once '../../includes/header.php';

$authUser->requirePermission('procurement.view');

// Handle filters
$filters = [
    'status' => $_GET['status'] ?? null,
    'department' => $_GET['department'] ?? null,
    'urgency' => $_GET['urgency'] ?? null,
    'sort_by' => $_GET['sort_by'] ?? null,
    'sort_order' => $_GET['sort_order'] ?? null,
    'pending_my_approval' => isset($_GET['pending_me']) ? ($authUser->getData()['user_id'] ?? null) : null
];

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$results = $procurement->getAll(array_filter($filters), $page, $perPage);
$requests = $results['data'] ?? [];
$totalPages = $results['total_pages'] ?? 1;

// Summary counts
$stats = $procurement->getStats();
$pendingCount = $stats['pending'];
$draftCount = $stats['draft'];
$orderedCount = $stats['ordered'];
$delaysCount = $stats['delays'];
?>

<div class="page-header">
    <div class="page-title">
        <h1>Procurement Control</h1>
        <p>Monitoring purchase requests, budget approvals, and asset acquisition.</p>
    </div>
    <div class="page-actions">
        <a href="pr-create.php" class="btn btn-primary">
            <i data-lucide="plus" style="width:16px;height:16px;"></i> New Request
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon warning"><i data-lucide="clock" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $pendingCount ?></div>
        <div class="stat-label">Pending Approval</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon primary"><i data-lucide="file-text" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $draftCount ?></div>
        <div class="stat-label">Open Drafts</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon success"><i data-lucide="truck" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $orderedCount ?></div>
        <div class="stat-label">On Order</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon danger"><i data-lucide="alert-circle" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $delaysCount ?></div>
        <div class="stat-label">Delays</div>
    </div>
</div>

<!-- Registry -->
<div class="card">
    <div class="card-header">
        <div style="display:flex;gap:0.5rem;">
            <a href="index.php"
                class="btn <?= !isset($_GET['pending_me']) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Registry</a>
            <a href="?pending_me=1"
                class="btn <?= isset($_GET['pending_me']) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">My Approvals</a>
        </div>
        <form method="GET" style="display:flex;gap:1rem;">
            <select name="status" class="form-control" style="width:160px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="pending_approval" <?= ($filters['status'] ?? '') === 'pending_approval' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved
                </option>
            </select>
            <select name="sort_by" class="form-control" style="width:160px;" onchange="this.form.submit()">
                <option value="request_date" <?= ($filters['sort_by'] ?? '') === 'request_date' ? 'selected' : '' ?>>
                    Submission Date</option>
                <option value="created_at" <?= ($filters['sort_by'] ?? '') === 'created_at' || empty($filters['sort_by']) ? 'selected' : '' ?>>Creation Date</option>
                <option value="total_estimated_cost" <?= ($filters['sort_by'] ?? '') === 'total_estimated_cost' ? 'selected' : '' ?>>Value</option>
                <option value="status" <?= ($filters['sort_by'] ?? '') === 'status' ? 'selected' : '' ?>>Status</option>
            </select>
            <select name="sort_order" class="form-control" style="width:120px;" onchange="this.form.submit()">
                <option value="DESC" <?= ($filters['sort_order'] ?? '') === 'DESC' || empty($filters['sort_order']) ? 'selected' : '' ?>>Descending</option>
                <option value="ASC" <?= ($filters['sort_order'] ?? '') === 'ASC' ? 'selected' : '' ?>>Ascending</option>
            </select>
        </form>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Originator</th>
                    <th>Dept</th>
                    <th>Value</th>
                    <th>Submission</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($requests)):
                    foreach ($requests as $pr):
                        $badgeCls = match ($pr['status']) {
                            PR_STATUS_DRAFT => 'badge-secondary',
                            PR_STATUS_PENDING => 'badge-warning',
                            PR_STATUS_APPROVED => 'badge-success',
                            PR_STATUS_REJECTED => 'badge-danger',
                            default => 'badge-info'
                        };
                        ?>
                        <tr>
                            <td style="font-weight:700;"><?= $pr['pr_number'] ?></td>
                            <td><?= htmlspecialchars($pr['requestor_name']) ?></td>
                            <td><span class="badge badge-secondary"><?= strtoupper($pr['department']) ?></span></td>
                            <td>₱<?= number_format($pr['total_estimated_cost'] ?? 0, 2) ?></td>
                            <td style="color:var(--text-muted);"><?= date('M d, Y', strtotime($pr['request_date'])) ?></td>
                            <td><span class="badge <?= $badgeCls ?>"><?= strtoupper($pr['status']) ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="pr-view.php?id=<?= $pr['pr_id'] ?>" class="btn btn-ghost btn-sm">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:3rem;color:var(--text-muted);">No records found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div style="margin-top:2rem; display:flex; justify-content:center; gap:0.5rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&<?= http_build_query(array_merge($_GET, ['page' => null])) ?>"
                class="btn <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>