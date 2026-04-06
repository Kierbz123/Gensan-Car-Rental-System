<?php
// modules/inventory/index.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$pageTitle = 'Parts Inventory';
require_once '../../includes/header.php';

$authUser->requirePermission('inventory.view');

$inv = new Inventory();
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$cat = $_GET['category'] ?? '';
$lowOnly = !empty($_GET['low_stock']);

$filters = [];
if ($search)
    $filters['search'] = $search;
if ($cat)
    $filters['category'] = $cat;
if ($lowOnly)
    $filters['low_stock'] = true;

$result = $inv->getAll($filters, $page, ITEMS_PER_PAGE);
$items = $result['data'];

$stats = $inv->getStats();
$totalItems = $stats['total_items'];
$lowCount = $stats['low_stock'];
$stockValue = $stats['stock_value'];
$onOrder = $stats['on_order'];

$CAT_LABELS = ['parts' => 'Parts', 'supplies' => 'Supplies', 'fuel' => 'Fuel', 'others' => 'Others'];
$CAT_COLORS = ['parts' => 'primary', 'supplies' => 'info', 'fuel' => 'warning', 'others' => 'secondary'];
?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="package"
                style="width:28px;height:28px;vertical-align:-5px;margin-right:10px;color:var(--primary)"></i>Parts
            Inventory</h1>
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
    <div class="stat-card">
        <div class="stat-card-icon primary"><i data-lucide="package" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= number_format($totalItems) ?></div>
        <div class="stat-label">Total Unique Items</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon <?= $lowCount > 0 ? 'danger' : 'success' ?>"><i data-lucide="alert-triangle" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= number_format($lowCount) ?></div>
        <div class="stat-label">Low Stock Alerts</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon warning"><i data-lucide="truck" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= number_format($onOrder) ?></div>
        <div class="stat-label">Items On Order</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon info"><i data-lucide="dollar-sign" style="width:20px;height:20px;"></i></div>
        <div class="stat-value">₱<?= number_format($stockValue, 2) ?></div>
        <div class="stat-label">Total Stock Value</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <!-- Filter Bar -->
        <form method="GET" class="card-header-form">
            <input type="text" name="search" class="form-control" placeholder="Search name or code…"
                value="<?= htmlspecialchars($search) ?>">
            <select name="category" class="form-control form-control--inline" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($CAT_LABELS as $v => $l): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= $cat === (string) $v ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="display:flex;gap:0.5rem;">
                <?php if ($lowOnly): ?><input type="hidden" name="low_stock" value="1"><?php endif; ?>
                <a href="?search=<?= urlencode($search) ?>&category=<?= urlencode($cat) ?>"
                    class="btn <?= !$lowOnly ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Items</a>
                <a href="?search=<?= urlencode($search) ?>&category=<?= urlencode($cat) ?>&low_stock=1"
                    class="btn <?= $lowOnly ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Low Stock Only</a>
            </div>
            <div class="card-header-actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <?php if ($search || $cat || $lowOnly): ?>
                    <a href="index.php" class="btn btn-ghost btn-sm" title="Clear Filters">
                        <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div
        style="padding:0.75rem 1.5rem;background:var(--bg-muted);border-bottom:1px solid var(--border-color);font-size:0.875rem;color:var(--text-muted);display:flex;justify-content:space-between;">
        <span>Showing <?= number_format($result['total']) ?> item<?= $result['total'] !== 1 ? 's' : '' ?></span>
    </div>
    <div class="table-container" style="margin-bottom:0;border:none;">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Item</th>
                    <th>Category</th>
                    <th style="text-align:right;">On Hand</th>
                    <th style="text-align:right;">Reorder Level</th>
                    <th>Unit Cost</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                            <i data-lucide="package-x"
                                style="width:32px;height:32px;display:block;margin:0 auto .5rem;opacity:.4"></i>
                            No items found.
                        </td>
                    </tr>
                <?php else:
                    foreach ($items as $item):
                        $isLow = (bool) $item['is_low_stock'];
                        ?>
                        <tr <?= $isLow ? 'style="background:var(--warning-light,#fffbeb);"' : '' ?>>
                            <td><code><?= htmlspecialchars($item['item_code']) ?></code></td>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </td>
                            <td><span class="badge badge-<?= $CAT_COLORS[$item['item_category']] ?? 'secondary' ?>">
                                    <?= $CAT_LABELS[$item['item_category']] ?? $item['item_category'] ?>
                                </span></td>
                            <td
                                style="text-align:right;font-weight:700;color:<?= $isLow ? 'var(--danger)' : 'var(--success)' ?>;">
                                <?= number_format($item['quantity_on_hand'], 3) ?>
                                <?= htmlspecialchars($item['unit']) ?>
                                <?php if ($isLow): ?><i data-lucide="alert-triangle"
                                        style="width:13px;height:13px;color:var(--warning);margin-left:3px;"></i>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;color:var(--text-muted);">
                                <?= number_format($item['reorder_level'], 3) ?>
                                <?= htmlspecialchars($item['unit']) ?>
                            </td>
                            <td>
                                <?= $item['unit_cost'] ? CURRENCY_SYMBOL . number_format($item['unit_cost'], 2) : '—' ?>
                            </td>
                            <td style="font-size:.85rem;color:var(--text-muted);">
                                <?= htmlspecialchars($item['storage_location'] ?? '') ?>
                            </td>
                            <td>
                                <div class="table-actions" style="display:flex; gap:0.25rem;">
                                    <a href="item-view.php?id=<?= $item['inventory_id'] ?>" class="btn btn-sm btn-secondary"
                                        title="View & Adjust">
                                        <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                    </a>
                                    <?php if ($isLow && $authUser->hasPermission('procurement.create')): ?>
                                        <a href="../procurement/pr-create.php?item_id=<?= $item['inventory_id'] ?>" class="btn btn-sm" title="Reorder Item" style="background:var(--warning-light); color:var(--warning-dark); border:1px solid var(--warning);">
                                            <i data-lucide="shopping-cart" style="width:14px;height:14px;"></i>
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
        <div
            style="padding:1rem 1.5rem;border-top:1px solid var(--border-color);display:flex;gap:.5rem;justify-content:flex-end;flex-wrap:wrap;">
            <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
                <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($cat) ?><?= $lowOnly ? '&low_stock=1' : '' ?>"
                    class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>