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
    'search' => $_GET['search'] ?? null,
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

// Helper for building sort URLs
$currentSortBy = $filters['sort_by'] ?? 'created_at';
$currentSortOrder = $filters['sort_order'] ?? 'DESC';

function buildSortUrl($field, $currentSortBy, $currentSortOrder) {
    // Note: To match prefixes, some queries use pr.pr_number etc.. The easiest way is to pass exactly what the backend expects
    $order = ($currentSortBy === $field && strtoupper($currentSortOrder) === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort_by'] = $field;
    $params['sort_order'] = $order;
    unset($params['page']);
    return '?' . http_build_query($params);
}

function getSortIcon($field, $currentSortBy, $currentSortOrder) {
    if ($currentSortBy === $field) {
        $iconName = strtoupper($currentSortOrder) === 'ASC' ? 'chevron-up' : 'chevron-down';
        return '<i data-lucide="' . $iconName . '" style="width:14px;height:14px;display:inline-block;vertical-align:middle;margin-left:4px;"></i>';
    }
    return '';
}

// Function to build URLs for stats cards
function buildStatUrl($statusFilter) {
    $params = $_GET;
    // Toggle logic: if already selected, clear it
    if (($params['status'] ?? '') === $statusFilter) {
        unset($params['status']);
    } else {
        $params['status'] = $statusFilter;
    }
    unset($params['page']); // back to page 1
    return '?' . http_build_query($params);
}

?>

<style>
.stat-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.stat-card.active {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 1px var(--primary-color) !important;
}
.sortable-header {
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    user-select: none;
}
.sortable-header:hover {
    color: var(--primary-color);
}
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}
.empty-state-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    opacity: 0.5;
}
.urgency-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
}
.urgency-high { background-color: var(--danger-color); }
.urgency-medium { background-color: var(--warning-color); }
.urgency-low { background-color: var(--success-color); }
</style>

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
    <a href="<?= buildStatUrl('pending_approval') ?>" class="stat-card-link">
        <div class="stat-card <?= ($filters['status'] ?? '') === 'pending_approval' ? 'active' : '' ?>">
            <div class="stat-card-icon warning"><i data-lucide="clock" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('draft') ?>" class="stat-card-link">
        <div class="stat-card <?= ($filters['status'] ?? '') === 'draft' ? 'active' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="file-text" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $draftCount ?></div>
            <div class="stat-label">Open Drafts</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('ordered') ?>" class="stat-card-link">
        <div class="stat-card <?= ($filters['status'] ?? '') === 'ordered' ? 'active' : '' ?>">
            <div class="stat-card-icon success"><i data-lucide="truck" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $orderedCount ?></div>
            <div class="stat-label">On Order</div>
        </div>
    </a>
    <a href="?status=ordered&delays=1" class="stat-card-link">
        <div class="stat-card <?= isset($_GET['delays']) ? 'active' : '' ?>">
            <div class="stat-card-icon danger"><i data-lucide="alert-circle" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $delaysCount ?></div>
            <div class="stat-label">Delays</div>
        </div>
    </a>
</div>

<!-- Registry -->
<div class="card">
    <div class="card-header" style="flex-wrap:wrap; gap:1rem;">
        <div style="display:flex;gap:0.5rem; align-items:center;">
            <a href="index.php" class="btn <?= !isset($_GET['pending_me']) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Registry</a>
            <a href="?pending_me=1" class="btn <?= isset($_GET['pending_me']) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">My Approvals</a>
        </div>
        
        <form method="GET" style="display:flex;gap:0.5rem;flex-wrap:wrap;flex:1;justify-content:flex-end;">
            <?php if(isset($_GET['pending_me'])): ?>
                <input type="hidden" name="pending_me" value="1">
            <?php endif; ?>
            <?php if(isset($_GET['sort_by'])): ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order'] ?? 'DESC') ?>">
            <?php endif; ?>
            
            <input type="text" name="search" class="form-control" placeholder="Search PR # or Name..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>" style="width:200px;">
            
            <select name="department" class="form-control" style="width:140px;">
                <option value="">All Depts</option>
                <option value="operations" <?= ($filters['department'] ?? '') === 'operations' ? 'selected' : '' ?>>Operations</option>
                <option value="maintenance" <?= ($filters['department'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                <option value="administration" <?= ($filters['department'] ?? '') === 'administration' ? 'selected' : '' ?>>Admin</option>
            </select>

            <select name="urgency" class="form-control" style="width:120px;">
                <option value="">Any Urgency</option>
                <option value="high" <?= ($filters['urgency'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= ($filters['urgency'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= ($filters['urgency'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
            </select>

            <select name="status" class="form-control" style="width:130px;">
                <option value="">All Statuses</option>
                <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="pending_approval" <?= ($filters['status'] ?? '') === 'pending_approval' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="ordered" <?= ($filters['status'] ?? '') === 'ordered' ? 'selected' : '' ?>>Ordered</option>
            </select>
            
            <button type="submit" class="btn btn-secondary"><i data-lucide="search" style="width:16px;height:16px;"></i></button>
        </form>
    </div>
    
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>
                        <a href="<?= buildSortUrl('pr_number', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">
                            Ref # <?= getSortIcon('pr_number', $currentSortBy, $currentSortOrder) ?>
                        </a>
                    </th>
                    <th>Originator</th>
                    <th>Dept</th>
                    <th>
                        <a href="<?= buildSortUrl('total_estimated_cost', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">
                            Value <?= getSortIcon('total_estimated_cost', $currentSortBy, $currentSortOrder) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('request_date', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">
                            Submission <?= getSortIcon('request_date', $currentSortBy, $currentSortOrder) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('status', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">
                            Status <?= getSortIcon('status', $currentSortBy, $currentSortOrder) ?>
                        </a>
                    </th>
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
                        $urgencyCls = match ($pr['urgency'] ?? 'medium') {
                            'high' => 'urgency-high',
                            'low' => 'urgency-low',
                            default => 'urgency-medium'
                        };
                        ?>
                        <tr>
                            <td style="font-weight:700;">
                                <span class="urgency-indicator <?= $urgencyCls ?>" title="Urgency: <?= ucfirst($pr['urgency'] ?? 'Medium') ?>"></span>
                                <?= $pr['pr_number'] ?>
                            </td>
                            <td><?= htmlspecialchars($pr['requestor_name']) ?></td>
                            <td><span class="badge badge-secondary"><?= strtoupper($pr['department']) ?></span></td>
                            <td>₱<?= number_format($pr['total_estimated_cost'] ?? 0, 2) ?></td>
                            <td style="color:var(--text-muted);"><?= date('M d, Y', strtotime($pr['request_date'])) ?></td>
                            <td><span class="badge <?= $badgeCls ?>"><?= strtoupper($pr['status']) ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="pr-view.php?id=<?= $pr['pr_id'] ?>" class="btn btn-ghost btn-sm">View</a>
                                    <?php if ($pr['status'] === PR_STATUS_DRAFT && $pr['requestor_id'] === ($authUser->getData()['user_id'] ?? 0)): ?>
                                        <a href="pr-edit.php?id=<?= $pr['pr_id'] ?>" class="btn btn-ghost btn-sm"><i data-lucide="edit-2" style="width:16px;height:16px;"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i data-lucide="inbox" class="empty-state-icon"></i>
                                <h3>No Procurement Requests Found</h3>
                                <p style="margin-bottom:1rem;">We couldn't find any requests matching your current filters.</p>
                                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                                <a href="pr-create.php" class="btn btn-primary" style="margin-left:0.5rem;">Create Request</a>
                            </div>
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