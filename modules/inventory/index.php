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

$db = Database::getInstance();
$pendingItems = $db->fetchAll(
    "SELECT pi.*, pr.pr_number, pr.created_at as pr_date
     FROM procurement_items pi
     JOIN procurement_requests pr ON pi.pr_id = pr.pr_id
     WHERE pr.status IN ('approved', 'ordered', 'partially_received', 'fully_received')
     AND pi.inventory_status = 'pending'
     ORDER BY pr.created_at DESC"
);
$pendingCount = count($pendingItems);

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
#pending-storage-panel.open {
    transform: translateX(0) !important;
}
</style>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="package" style="width:28px;height:28px;vertical-align:-5px;margin-right:10px;color:var(--primary)"></i>Inventory</h1>
        <p>Track spare parts and supplies stock levels. Linked to Procurement and Maintenance.</p>
    </div>
    <div class="page-actions">
        <?php if ($authUser->hasPermission('inventory.create')): ?>
            <button type="button" class="btn btn-warning" onclick="document.getElementById('pending-storage-panel').classList.add('open')" style="position:relative;background-color:var(--warning-light);color:var(--warning-dark);border-color:var(--warning);">
                <i data-lucide="inbox" style="width:16px;height:16px;"></i> Pending Storage
                <span style="position:absolute;top:-6px;right:-6px;background:<?= $pendingCount > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;color:#fff;font-size:.7rem;font-weight:700;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;"><?= $pendingCount ?></span>
            </button>
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
                    <th>
                        <a href="<?= buildSortUrl('created_at', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Date Added <?= getSortIcon('created_at', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="9">
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
                            <td>
                                <span style="font-size:0.85em; color:var(--text-muted);">
                                    <?= date('M d, Y', strtotime($item['created_at'])) ?>
                                </span>
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

<!-- Pending Storage Slide-out Panel -->
<div id="pending-storage-panel" style="position:fixed;top:0;right:0;width:520px;max-width:100vw;height:100vh;background:var(--bg-surface);box-shadow:-4px 0 32px rgba(0,0,0,.15);z-index:10000;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);background:var(--bg-muted);">
        <h2 style="margin:0;font-size:1.05rem;font-weight:700;display:flex;align-items:center;gap:.5rem;">
            <i data-lucide="inbox" style="width:18px;height:18px;color:var(--warning-dark);"></i>
            Pending Storage
            <span style="background:<?= $pendingCount > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;color:#fff;font-size:.7rem;padding:2px 7px;border-radius:99px;"><?= $pendingCount ?></span>
        </h2>
        <div style="display:flex;align-items:center;gap:1rem;">
            <?php if ($pendingCount > 0): ?>
            <select id="pending-sort-select" class="form-control form-control--sm" style="font-size:0.8rem;padding:0.25rem 0.5rem;height:auto;" onchange="sortPendingItems()">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
            </select>
            <?php endif; ?>
            <button onclick="document.getElementById('pending-storage-panel').classList.remove('open')" style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-muted);border-radius:6px;" title="Close">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
    </div>
    <div id="pending-items-container" style="flex:1;overflow-y:auto;padding:1.25rem 1.5rem;">
        <?php if (empty($pendingItems)): ?>
            <div style="text-align:center;padding:2rem;color:var(--text-muted);">No items pending storage.</div>
        <?php else: foreach ($pendingItems as $pi): ?>
            <div class="pending-item-card" id="pending-item-<?= $pi['item_id'] ?>" data-date="<?= htmlspecialchars($pi['pr_date']) ?>" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:.875rem 1rem;margin-bottom:.75rem;border:1px solid var(--border-color);border-radius:var(--radius-md);background:var(--bg-body);">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:.9375rem;margin-bottom:2px;">
                        <?= htmlspecialchars($pi['item_description']) ?>
                    </div>
                    <div style="font-size:.8rem;color:var(--text-muted);">
                        <code><?= htmlspecialchars($pi['pr_number']) ?></code>
                        • <?= floatval($pi['quantity'] ?? $pi['quantity_received']) ?> <?= htmlspecialchars($pi['unit']) ?>
                        <div style="margin-top:2px;font-size:0.75rem;opacity:0.8;">
                            <i data-lucide="calendar" style="width:10px;height:10px;display:inline-block;vertical-align:-1px;"></i> <?= date('M d, Y g:i A', strtotime($pi['pr_date'])) ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:0.5rem;flex-shrink:0;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="processPendingStorage(<?= $pi['item_id'] ?>, 'store', '<?= htmlspecialchars(addslashes($pi['item_description'])) ?>')" style="width:100%;justify-content:center;">
                        <i data-lucide="download" style="width:13px;height:13px;"></i> Store
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="processPendingStorage(<?= $pi['item_id'] ?>, 'skip', '<?= htmlspecialchars(addslashes($pi['item_description'])) ?>')" style="width:100%;justify-content:center;color:var(--text-muted);">
                        <i data-lucide="x-circle" style="width:13px;height:13px;"></i> Skip
                    </button>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div id="pending-storage-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index:10001; align-items:center; justify-content:center;">
    <div class="gcr-modal" style="background:var(--bg-card, #ffffff); border:1px solid var(--border-color, #e2e8f0); border-radius:12px; padding:2rem; width:400px; max-width:95vw; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); text-align:center;">
        <div id="ps-modal-icon" style="width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
            <!-- Icon injected via JS -->
        </div>
        <h3 id="ps-modal-title" style="margin:0 0 0.5rem; font-size:1.25rem; font-weight:700;">Confirm Action</h3>
        <p id="ps-modal-message" style="color:var(--text-muted); margin:0 0 1.5rem; font-size:0.95rem;"></p>
        
        <div class="gcr-modal-actions" style="display:flex; gap:1rem; justify-content:center;">
            <button class="btn btn-secondary" onclick="document.getElementById('pending-storage-modal-overlay').style.display='none'" style="flex:1;">Cancel</button>
            <button id="ps-modal-confirm" class="btn" style="flex:1;">Confirm</button>
        </div>
    </div>
</div>

<script>
function sortPendingItems() {
    const container = document.getElementById('pending-items-container');
    const items = Array.from(container.querySelectorAll('.pending-item-card'));
    if (items.length === 0) return;
    
    const sortVal = document.getElementById('pending-sort-select').value;
    
    items.sort((a, b) => {
        const dateA = new Date(a.dataset.date).getTime();
        const dateB = new Date(b.dataset.date).getTime();
        return sortVal === 'newest' ? dateB - dateA : dateA - dateB;
    });
    
    items.forEach(item => container.appendChild(item));
}

let pendingStorageActionData = null;

function processPendingStorage(itemId, action, itemName) {
    pendingStorageActionData = { itemId, action, itemName };
    
    const overlay = document.getElementById('pending-storage-modal-overlay');
    const title = document.getElementById('ps-modal-title');
    const msg = document.getElementById('ps-modal-message');
    const iconContainer = document.getElementById('ps-modal-icon');
    const confirmBtn = document.getElementById('ps-modal-confirm');
    
    if (action === 'store') {
        title.textContent = 'Store in Inventory';
        msg.textContent = `Are you sure you want to add "${itemName}" to your physical inventory tracking?`;
        iconContainer.style.background = 'var(--primary-light, #e0e7ff)';
        iconContainer.innerHTML = '<i data-lucide="download" style="width:24px;height:24px;color:var(--primary);"></i>';
        confirmBtn.className = 'btn btn-primary';
        confirmBtn.textContent = 'Yes, Store It';
    } else {
        title.textContent = 'Skip Inventory';
        msg.textContent = `Skip storing "${itemName}"? Use this for items like food or office supplies that don't need garage inventory tracking.`;
        iconContainer.style.background = 'var(--warning-light, #fef3c7)';
        iconContainer.innerHTML = '<i data-lucide="x-circle" style="width:24px;height:24px;color:var(--warning-dark, #b45309);"></i>';
        confirmBtn.className = 'btn btn-warning';
        confirmBtn.style.color = 'var(--warning-dark)';
        confirmBtn.textContent = 'Yes, Skip It';
    }
    
    lucide.createIcons();
    overlay.style.display = 'flex';
}

document.getElementById('ps-modal-confirm').addEventListener('click', function() {
    if (!pendingStorageActionData) return;
    
    const { itemId, action } = pendingStorageActionData;
    const btn = this;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Processing...';
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('item_id', itemId);
    formData.append('csrf_token', '<?= getCsrfToken() ?>');

    fetch('ajax/store-procured-item.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (action === 'store') {
                window.location.reload();
            } else {
                const el = document.getElementById('pending-item-' + itemId);
                if (el) el.remove();
                document.getElementById('pending-storage-modal-overlay').style.display = 'none';
            }
        } else {
            alert(res.message || 'An error occurred.');
            document.getElementById('pending-storage-modal-overlay').style.display = 'none';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to connect to server.');
        document.getElementById('pending-storage-modal-overlay').style.display = 'none';
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>