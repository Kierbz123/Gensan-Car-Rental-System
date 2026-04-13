<?php
// modules/inventory/index.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$pageTitle = 'Inventory';
require_once '../../includes/header.php';

$authUser->requirePermission('inventory.view');

$inv = new Inventory();
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$cat = $_GET['category'] ?? '';
$lowOnly = !empty($_GET['low_stock']);
$onOrderOnly = !empty($_GET['on_order']);

$filters = [];
if ($search) $filters['search'] = $search;
if ($cat) $filters['category'] = $cat;
if ($lowOnly) $filters['low_stock'] = true;
if ($onOrderOnly) $filters['on_order'] = true;
if (!empty($_GET['sort_by'])) $filters['sort_by'] = $_GET['sort_by'];
if (!empty($_GET['sort_order'])) $filters['sort_order'] = $_GET['sort_order'];

$result = $inv->getAll($filters, $page, ITEMS_PER_PAGE);
$items = $result['data'];

$stats = $inv->getStats();
$totalItems = $stats['total_items'];
$lowCount = $stats['low_stock'];
$stockValue = $stats['stock_value'];
$onOrder = $stats['on_order'];

$CAT_LABELS = ['parts' => 'Parts', 'supplies' => 'Supplies', 'fuel' => 'Fuel', 'others' => 'Others'];
$CAT_COLORS = ['parts' => 'primary', 'supplies' => 'info', 'fuel' => 'warning', 'others' => 'secondary'];

// Helpers for sorting and filtering
$currentSortBy = $filters['sort_by'] ?? 'item_name';
$currentSortOrder = $filters['sort_order'] ?? 'ASC';

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

function buildStatUrl($filterKey) {
    $params = $_GET;
    // Toggle logic for boolean filters
    if (!empty($params[$filterKey])) {
        unset($params[$filterKey]);
    } else {
        $params[$filterKey] = 1;
        // Turn off conflicting filters if toggling one on
        if ($filterKey === 'low_stock') unset($params['on_order']);
        if ($filterKey === 'on_order') unset($params['low_stock']);
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
        <h1><i data-lucide="package" style="width:28px;height:28px;vertical-align:-5px;margin-right:10px;color:var(--primary)"></i>Inventory</h1>
        <p>Track spare parts and supplies stock levels. Linked to Procurement and Maintenance.</p>
    </div>
    <div class="page-actions">
        <?php if ($authUser->hasPermission('inventory.create')): ?>
            <a href="item-add.php" class="btn btn-primary">
                <i data-lucide="plus" style="width:16px;height:16px;"></i> Add Item
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-grid" style="margin-bottom:1.5rem;">
    <a href="index.php" class="stat-card-link">
        <div class="stat-card <?= (!$lowOnly && !$onOrderOnly) ? 'active' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="package" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalItems) ?></div>
                <div class="stat-label">Total Unique Items</div>
            </div>
        </div>
    </a>
    <a href="<?= buildStatUrl('low_stock') ?>" class="stat-card-link">
        <div class="stat-card <?= $lowOnly ? 'active' : '' ?>">
            <div class="stat-card-icon <?= $lowCount > 0 ? 'danger' : 'success' ?>"><i data-lucide="alert-triangle" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($lowCount) ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
        </div>
    </a>
    <a href="<?= buildStatUrl('on_order') ?>" class="stat-card-link">
        <div class="stat-card <?= $onOrderOnly ? 'active' : '' ?>">
            <div class="stat-card-icon warning"><i data-lucide="truck" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($onOrder) ?></div>
                <div class="stat-label">Items On Order</div>
            </div>
        </div>
    </a>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon info"><i data-lucide="dollar-sign" style="width:20px;height:20px;"></i></div>
        <div class="stat-info">
            <div class="stat-value">₱<?= number_format($stockValue, 2) ?></div>
            <div class="stat-label">Total Stock Value</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <!-- Filter Bar -->
        <form method="GET" class="card-header-form" style="width:100%; display:flex; gap:0.5rem; flex-wrap:wrap;">
            <?php if(isset($_GET['sort_by'])): ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order'] ?? 'ASC') ?>">
            <?php endif; ?>
            <?php if ($lowOnly): ?><input type="hidden" name="low_stock" value="1"><?php endif; ?>
            <?php if ($onOrderOnly): ?><input type="hidden" name="on_order" value="1"><?php endif; ?>

            <input type="text" name="search" class="form-control" placeholder="Search name or code…" style="flex:1; min-width:200px;" value="<?= htmlspecialchars($search) ?>">
            <select name="category" class="form-control form-control--inline" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($CAT_LABELS as $v => $l): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= $cat === (string) $v ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="card-header-actions" style="margin-left:auto;">
                <button type="submit" class="btn btn-secondary"><i data-lucide="search" style="width:16px;height:16px;"></i></button>
                <?php if ($search || $cat || $lowOnly || $onOrderOnly): ?>
                    <a href="index.php" class="btn btn-ghost" title="Clear Filters">
                        <i data-lucide="rotate-ccw" style="width:16px;height:16px;"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div style="padding:0.75rem 1.5rem;background:var(--bg-muted);border-bottom:1px solid var(--border-color);font-size:0.875rem;color:var(--text-muted);display:flex;justify-content:space-between;">
        <span>Showing <?= number_format($result['total']) ?> item<?= $result['total'] !== 1 ? 's' : '' ?></span>
    </div>
    <div class="table-container" style="margin-bottom:0;border:none;">
        <table>
            <thead>
                <tr>
                    <th>
                        <a href="<?= buildSortUrl('item_code', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Code <?= getSortIcon('item_code', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('item_name', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Item <?= getSortIcon('item_name', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('item_category', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Category <?= getSortIcon('item_category', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>Supplier</th>
                    <th style="text-align:right;">
                        <a href="<?= buildSortUrl('quantity_on_hand', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">On Hand <?= getSortIcon('quantity_on_hand', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th style="text-align:right;">
                        <a href="<?= buildSortUrl('reorder_level', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Reorder Level <?= getSortIcon('reorder_level', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('unit_cost', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Unit Cost <?= getSortIcon('unit_cost', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i data-lucide="package-x" class="empty-state-icon"></i>
                                <h3>No Inventory Found</h3>
                                <p style="margin-bottom:1rem;">We couldn't find any items matching your current filters.</p>
                                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                                <?php if ($authUser->hasPermission('inventory.create')): ?>
                                    <a href="item-add.php" class="btn btn-primary" style="margin-left:0.5rem;">Add Item</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    foreach ($items as $item):
                        $isLow = (bool) $item['is_low_stock'];
                        ?>
                        <tr <?= $isLow ? 'style="background:var(--warning-light,#fffbeb);"' : '' ?>>
                            <td><code><?= htmlspecialchars($item['item_code']) ?></code></td>
                            <td style="font-weight:600;">
                                <div style="display:flex; flex-direction:column;">
                                    <?= htmlspecialchars($item['item_name']) ?>
                                    <span style="font-size:0.8em; color:var(--text-muted); font-weight:normal; display:flex; align-items:center; gap:4px;">
                                        <i data-lucide="map-pin" style="width:10px;height:10px;"></i>
                                        <?= htmlspecialchars($item['storage_location'] ?? 'Unassigned') ?>
                                    </span>
                                </div>
                            </td>
                            <td><span class="badge badge-<?= $CAT_COLORS[$item['item_category']] ?? 'secondary' ?>">
                                    <?= $CAT_LABELS[$item['item_category']] ?? $item['item_category'] ?>
                                </span></td>
                            <td>
                                <?php if (!empty($item['supplier_id']) && !empty($item['supplier_name'])): ?>
                                    <a href="../suppliers/supplier-view.php?id=<?= $item['supplier_id'] ?>" style="font-size:0.9em; font-weight:500; display:flex; align-items:center; gap:4px; max-width:140px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <i data-lucide="link" style="width:12px;height:12px;opacity:0.6;"></i> <?= htmlspecialchars($item['supplier_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:0.85em;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;font-weight:700;color:<?= $isLow ? 'var(--danger)' : 'var(--success)' ?>;">
                                <?= number_format($item['quantity_on_hand'], 3) ?>
                                <?= htmlspecialchars($item['unit']) ?>
                                <?php if ($isLow): ?><i data-lucide="alert-triangle" style="width:13px;height:13px;color:var(--warning);margin-left:3px;"></i>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;color:var(--text-muted);">
                                <?= number_format($item['reorder_level'], 3) ?>
                                <?= htmlspecialchars($item['unit']) ?>
                            </td>
                            <td>
                                <?= $item['unit_cost'] ? CURRENCY_SYMBOL . number_format($item['unit_cost'], 2) : '—' ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; align-items:center; gap:4px;">
                                    <a href="item-view.php?id=<?= $item['inventory_id'] ?>" class="btn btn-sm btn-ghost" title="View & Adjust">
                                        <i data-lucide="eye" style="width:16px;height:16px;"></i>
                                    </a>
                                    <?php if ($authUser->hasPermission('procurement.create')): ?>
                                        <a href="../procurement/pr-create.php?item_id=<?= $item['inventory_id'] ?>" class="btn btn-sm <?= $isLow ? 'btn-warning' : 'btn-ghost' ?>" title="Reorder Item" style="<?= $isLow ? 'background:var(--warning-light); color:var(--warning-dark); border:1px solid var(--warning);' : '' ?>">
                                            <i data-lucide="shopping-cart" style="width:16px;height:16px;"></i>
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
                    class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>