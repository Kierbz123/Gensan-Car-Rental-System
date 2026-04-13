<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$pageTitle = "Rental Operations";
require_once '../../includes/header.php';

// Actual DB Logic
try {
    $rentalObj = new RentalAgreement();
    $stats = $rentalObj->getStats();

    // calculate efficiency dynamically
    $totalActiveAndOverdue = max(1, ($stats['active'] + $stats['overdue']));
    $efficiency = max(0, 100 - round(($stats['overdue'] / $totalActiveAndOverdue) * 100));

    $type = $_GET['type'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $overdueOnly = !empty($_GET['overdue']);

    $statusFilter = [];
    if (!$overdueOnly) {
        $statusFilter = [RENTAL_STATUS_RESERVED, RENTAL_STATUS_CONFIRMED, RENTAL_STATUS_ACTIVE];
        if ($type === 'dispatch') {
            $statusFilter = [RENTAL_STATUS_RESERVED, RENTAL_STATUS_CONFIRMED];
        } elseif ($type === 'return') {
            $statusFilter = [RENTAL_STATUS_ACTIVE];
        }
    }

    $filters = [];
    if (!empty($statusFilter)) $filters['status'] = $statusFilter;
    if ($search !== '') $filters['search'] = $search;
    if ($overdueOnly) $filters['overdue'] = true;
    if (!empty($_GET['sort_by'])) $filters['sort_by'] = $_GET['sort_by'];
    if (!empty($_GET['sort_order'])) $filters['sort_order'] = $_GET['sort_order'];

    $result = $rentalObj->getAll($filters, 1, 20);
    $agreements = $result['data'] ?? [];

    // History specifically forces RENTAL_STATUS_RETURNED & RENTAL_STATUS_CANCELLED without any limits
    $historyFilters = ['status' => [RENTAL_STATUS_RETURNED, RENTAL_STATUS_CANCELLED]];
    $historyResult = $rentalObj->getAll($historyFilters, 1, 50);
    $rentalHistory = $historyResult['data'] ?? [];
} catch (Exception $e) {
    $_SESSION['error_message'] = "Failed to load rental data: " . $e->getMessage();
    $stats = ['total' => 0, 'reserved' => 0, 'confirmed' => 0, 'active' => 0, 'overdue' => 0];
    $agreements = [];
    $rentalHistory = [];
    $efficiency = 100;
}

$currentSortBy = $filters['sort_by'] ?? 'ra.created_at';
$currentSortOrder = $filters['sort_order'] ?? 'DESC';

function buildSortUrl($field, $currentSortBy, $currentSortOrder) {
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

function buildStatUrl($typeValue, $isOverdue = false) {
    $params = $_GET;
    // Toggle logic for clicking URL states safely
    if ($isOverdue) {
        if (!empty($params['overdue'])) unset($params['overdue']); // toggle off
        else {
            $params['overdue'] = 1;
            unset($params['type']); // isolate filter
        }
    } else {
        unset($params['overdue']);
        if (isset($params['type']) && $params['type'] === $typeValue) {
            unset($params['type']); // toggle off
        } else {
            $params['type'] = $typeValue; // switch bounds
        }
    }
    unset($params['page']);
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
.stat-card-link .stat-card {
    height: 100%;
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
</style>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="key" style="width:28px;height:28px;vertical-align:-5px;margin-right:10px;color:var(--primary)"></i>Rental Operations</h1>
        <p>Coordinating vehicle assignments and reservation lifecycles.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('history-panel').classList.add('open')">
            <i data-lucide="history" style="width:16px;height:16px;"></i> History
        </button>
        <?php if ($authUser->hasPermission('rentals.create')): ?>
        <a href="reserve.php" class="btn btn-primary">
            <i data-lucide="calendar-plus" style="width:16px;height:16px;"></i> New Rental
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom:2rem;">
    <a href="<?= buildStatUrl('dispatch') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['type']) && $_GET['type'] === 'dispatch' && empty($_GET['overdue'])) ? 'active' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="key" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['reserved'] + $stats['confirmed'] ?? 0 ?></div>
                <div class="stat-label">To Dispatch</div>
            </div>
        </div>
    </a>
    <a href="<?= buildStatUrl('return') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['type']) && $_GET['type'] === 'return' && empty($_GET['overdue'])) ? 'active' : '' ?>">
            <div class="stat-card-icon info"><i data-lucide="corner-down-left" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['active'] ?? 0 ?></div>
                <div class="stat-label">Active (To Return)</div>
            </div>
        </div>
    </a>
    <a href="<?= buildStatUrl('overdue', true) ?>" class="stat-card-link">
        <div class="stat-card <?= !empty($_GET['overdue']) ? 'active' : '' ?>">
            <div class="stat-card-icon danger"><i data-lucide="alert-triangle" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value" style="color:var(--danger);"><?= $stats['overdue'] ?? 0 ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
    </a>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon success"><i data-lucide="activity" style="width:20px;height:20px;"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $efficiency ?>%</div>
            <div class="stat-label">System Efficiency</div>
        </div>
    </div>
</div>

<div class="card">
    <div style="padding:.875rem 1.25rem; border-bottom:1px solid var(--border-color);">
        <form method="GET" id="filterForm" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;width:100%;">
            
            <!-- Search -->
            <div style="position:relative;flex:1;min-width:200px;">
                <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
                <input type="text" name="search" id="searchInput" class="form-control" style="padding-left:34px;width:100%;" placeholder="Search agreements, clients or vehicles..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>

            <?php if (isset($_GET['type'])): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($_GET['type']) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['sort_by'])): ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order']) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['overdue'])): ?>
                <input type="hidden" name="overdue" value="1">
            <?php endif; ?>

            <div style="display:flex;gap:.5rem;flex-shrink:0;">
                <button type="submit" class="btn btn-primary btn-sm" id="applyFilterBtn">
                    <i data-lucide="search" style="width:13px;height:13px;"></i> Search
                </button>
                <?php if (!empty($_GET['search']) || !empty($_GET['type']) || !empty($_GET['overdue'])): ?>
                    <a href="index.php" class="btn btn-ghost btn-sm" title="Clear Filters"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></a>
                <?php endif; ?>
            </div>

            <!-- Result count -->
            <span style="margin-left:auto;font-size:.8125rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0;">
                <?= number_format($result['total']) ?> rental<?= $result['total'] !== 1 ? 's' : '' ?>
            </span>
        </form>
    </div>
    <div class="table-container" style="border:none; margin-bottom:0;">
        <table>
            <thead>
                <tr>
                    <th>
                        <a href="<?= buildSortUrl('ra.agreement_number', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Agreement # <?= getSortIcon('ra.agreement_number', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('customer_name', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Customer <?= getSortIcon('customer_name', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('v.plate_number', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Vehicle <?= getSortIcon('v.plate_number', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('ra.rental_start_date', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Dates <?= getSortIcon('ra.rental_start_date', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('ra.status', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Status <?= getSortIcon('ra.status', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agreements)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i data-lucide="folder-search" class="empty-state-icon"></i>
                                <h3>No Rentals Found</h3>
                                <p style="margin-bottom:1rem;">We couldn't find any active rentals matching your current filters.</p>
                                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                                <?php if ($authUser->hasPermission('rentals.create')): ?>
                                    <a href="reserve.php" class="btn btn-primary" style="margin-left:0.5rem;">New Rental</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    foreach ($agreements as $ra):
                        $badgeClass = match ($ra['status']) {
                            RENTAL_STATUS_RESERVED => 'badge-info',
                            RENTAL_STATUS_ACTIVE => 'badge-success',
                            RENTAL_STATUS_CANCELLED => 'badge-danger',
                            default => 'badge-secondary'
                        };
                        $isBreached = ($ra['status'] === RENTAL_STATUS_ACTIVE && strtotime($ra['rental_end_date']) < time());
                        ?>
                        <tr <?= $isBreached ? 'style="background:var(--danger-light);"' : '' ?>>
                            <td style="font-weight:700;color:var(--primary);">
                                <?= htmlspecialchars($ra['agreement_number']) ?>
                            </td>
                            <td>
                                <a href="../customers/customer-view.php?id=<?= $ra['customer_id'] ?>" style="font-weight:600; display:flex; align-items:center; gap:4px; color:inherit;">
                                    <i data-lucide="link" style="width:12px;height:12px;opacity:0.6;"></i>
                                    <?= htmlspecialchars($ra['customer_name']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="../asset-tracking/vehicle-details.php?id=<?= $ra['vehicle_id'] ?>" style="font-weight:600; display:flex; flex-direction:column; color:inherit;">
                                    <?= htmlspecialchars($ra['brand'] . ' ' . $ra['model']) ?>
                                    <span style="font-size:0.75rem; color:var(--text-muted); font-family:monospace; display:flex; align-items:center; gap:2px;">
                                        <i data-lucide="link" style="width:10px;height:10px;opacity:0.6;"></i> <?= htmlspecialchars($ra['plate_number']) ?>
                                    </span>
                                </a>
                            </td>
                            <td style="font-size:0.8rem;">
                                <?= date('M d', strtotime($ra['rental_start_date'])) ?> - <?= date('M d', strtotime($ra['rental_end_date'])) ?>
                                <?php if ($isBreached): ?>
                                    <div style="color:var(--danger);font-weight:700;font-size:10px;margin-top:2px;">
                                        OVERDUE
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($ra['status']) ?></span></td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; align-items:center; gap:4px;">
                                    <a href="view.php?id=<?= $ra['agreement_id'] ?>" class="btn btn-sm btn-ghost" title="Details">
                                        <i data-lucide="eye" style="width:16px;height:16px;"></i>
                                    </a>
                                    <?php if ($ra['status'] === RENTAL_STATUS_RESERVED || $ra['status'] === RENTAL_STATUS_CONFIRMED): ?>
                                        <a href="check-out.php?id=<?= $ra['agreement_id'] ?>" class="btn btn-primary btn-sm" title="Dispatch">
                                            <i data-lucide="key" style="width:16px;height:16px;"></i>
                                        </a>
                                    <?php elseif ($ra['status'] === RENTAL_STATUS_ACTIVE): ?>
                                        <a href="check-in.php?id=<?= $ra['agreement_id'] ?>" class="btn btn-success btn-sm" title="Return">
                                            <i data-lucide="corner-down-left" style="width:16px;height:16px;"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($result['total_pages'] > 1): ?>
        <div style="padding:1rem 1.5rem;border-top:1px solid var(--border-color);display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
            <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
                <a href="?page=<?= $p ?>&<?= http_build_query(array_merge($_GET, ['page' => null])) ?>"
                    class="btn btn-sm <?= $p === $result['page'] ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ───── Rental History Slide-in Panel ───── -->
<div id="history-panel" style="position:fixed;top:0;right:0;width:560px;max-width:100vw;height:100vh;background:var(--bg-surface,#fff);box-shadow:-4px 0 24px rgba(0,0,0,.15);z-index:10000;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,.0,.2,1);display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);">
        <h2 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
            <i data-lucide="history" style="width:20px;height:20px;color:var(--primary);"></i>
            Transaction History
        </h2>
        <button onclick="document.getElementById('history-panel').classList.remove('open')"
            style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-muted);">
            <i data-lucide="x" style="width:20px;height:20px;"></i>
        </button>
    </div>
    <div style="flex:1;overflow-y:auto;padding:1rem 1.5rem;background:var(--bg-body, #f4f6f8);">
        <?php if (empty($rentalHistory)): ?>
            <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                <i data-lucide="file-question" style="width:40px;height:40px;display:block;margin:0 auto .75rem;opacity:.3;"></i>
                <p style="font-weight:600;">No past transactions</p>
                <p style="font-size:.85rem;">History of returned and cancelled rentals will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($rentalHistory as $hr): ?>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:1rem;margin-bottom:1rem;border:1px solid var(--border-color);border-radius:var(--radius-md);background:var(--bg-surface,#fff);box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <div style="flex-grow:1;padding-right:1rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <div style="font-weight:700;color:var(--primary);font-family:monospace;"><?= htmlspecialchars($hr['agreement_number']) ?></div>
                            <?php 
                                $hBadgeClass = match ($hr['status']) {
                                    RENTAL_STATUS_RETURNED => 'badge-success',
                                    RENTAL_STATUS_CANCELLED => 'badge-danger',
                                    default => 'badge-secondary'
                                };
                            ?>
                            <span class="badge <?= $hBadgeClass ?>"><?= htmlspecialchars($hr['status']) ?></span>
                        </div>
                        <div style="font-size:.9rem;font-weight:600;margin-bottom:4px;">
                            <?= htmlspecialchars($hr['customer_name']) ?>
                        </div>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:8px;">
                            <i data-lucide="car" style="width:12px;height:12px;vertical-align:-2px;margin-right:2px;"></i>
                            <?= htmlspecialchars($hr['brand'] . ' ' . $hr['model']) ?> 
                            <span style="font-family:monospace;background:var(--bg-muted);padding:1px 4px;border-radius:3px;margin-left:4px;"><?= htmlspecialchars($hr['plate_number']) ?></span>
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;gap:1rem;">
                            <span>
                                <i data-lucide="calendar" style="width:12px;height:12px;vertical-align:-2px;margin-right:2px;"></i>
                                <?= date('M d, Y', strtotime($hr['rental_start_date'])) ?> - <?= date('M d, Y', strtotime($hr['rental_end_date'])) ?>
                            </span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.5rem;min-width:80px;text-align:right;">
                        <a href="view.php?id=<?= $hr['agreement_id'] ?>" class="btn btn-ghost btn-sm" style="justify-content:center;">Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<div id="history-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:9999;opacity:0;pointer-events:none;transition:opacity .3s;"
    onclick="document.getElementById('history-panel').classList.remove('open')"></div>
<style>
    #history-panel.open { transform: translateX(0) !important; }
    #history-panel.open ~ #history-overlay { opacity: 1 !important; pointer-events: auto !important; }
</style>

<?php require_once '../../includes/footer.php'; ?>