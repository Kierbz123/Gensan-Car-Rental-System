<?php
// modules/suppliers/index.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$pageTitle = 'Supplier Management';
require_once '../../includes/header.php';

$authUser->requirePermission('suppliers.view');

$supplier = new Supplier();

$page   = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

$filters = [];
if ($search)   $filters['search'] = $search;
if ($category) $filters['category'] = $category;
if ($status !== '') $filters['is_active'] = $status;
if (!empty($_GET['sort_by'])) $filters['sort_by'] = $_GET['sort_by'];
if (!empty($_GET['sort_order'])) $filters['sort_order'] = $_GET['sort_order'];

$result    = $supplier->getAll($filters, $page, ITEMS_PER_PAGE);
$suppliers = $result['data'];
$total     = $result['total'];

// Summary counts
$stats = $supplier->getStats();
$totalCount = $stats['total'];
$activeCount = $stats['active'];
$inactiveCount = $stats['inactive'];

$CATEGORY_LABELS = [
    'auto_parts'            => ['label' => 'Auto Parts',         'class' => 'primary'],
    'maintenance_supplies'  => ['label' => 'Maintenance',        'class' => 'info'],
    'fuel'                  => ['label' => 'Fuel',               'class' => 'warning'],
    'tires'                 => ['label' => 'Tires',              'class' => 'secondary'],
    'carwash_supplies'      => ['label' => 'Carwash',            'class' => 'info'],
    'insurance'             => ['label' => 'Insurance',          'class' => 'success'],
    'registration_services' => ['label' => 'Registration',       'class' => 'danger'],
    'others'                => ['label' => 'Others',             'class' => 'secondary'],
];

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Helpers for sorting and filtering
$currentSortBy = $filters['sort_by'] ?? 'company_name';
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

function buildStatUrl($statusFilter) {
    $params = $_GET;
    // Note: status filter might be 0 or 1, so strict comparison strings 
    if (($params['status'] ?? '') === (string)$statusFilter) {
        unset($params['status']);
    } else {
        $params['status'] = $statusFilter;
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
.toast-close {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    opacity: 0.8;
    display: flex;
    align-items: center;
    padding: 0;
    margin-left: auto;
}
.toast-close:hover {
    opacity: 1;
}
.contact-details {
    display: flex;
    flex-direction: column;
}
.contact-meta {
    font-size: 0.8em;
    color: var(--text-muted);
    display: flex;
    gap: 0.5rem;
    margin-top: 2px;
}
.contact-meta span {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
</style>

<?php if ($successMsg): ?>
    <div id="toast-sup" style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:var(--success);color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;max-width:380px;">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span><?= htmlspecialchars($successMsg) ?></span>
        <button class="toast-close" onclick="document.getElementById('toast-sup').remove();">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    </div>
    <script>setTimeout(() => { document.getElementById('toast-sup')?.remove(); }, 4000);</script>
<?php endif; ?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="truck" style="width:28px;height:28px;vertical-align:-5px;margin-right:10px;color:var(--primary)"></i>Supplier Management</h1>
        <p>Manage vendors, service providers, and supply chain partners.</p>
    </div>
    <div class="page-actions">
        <?php if ($authUser->hasPermission('suppliers.create')): ?>
            <a href="supplier-add.php" class="btn btn-primary">
                <i data-lucide="plus" style="width:16px;height:16px;"></i> Add Supplier
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-grid">
    <a href="index.php" class="stat-card-link">
        <div class="stat-card <?= ($status === '') ? 'active' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="truck" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $totalCount ?></div>
            <div class="stat-label">Total Suppliers</div>
        </div>
    </a>
    <a href="<?= buildStatUrl(1) ?>" class="stat-card-link">
        <div class="stat-card <?= ($status === '1') ? 'active' : '' ?>">
            <div class="stat-card-icon success"><i data-lucide="check-circle" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $activeCount ?></div>
            <div class="stat-label">Active Contractors</div>
        </div>
    </a>
    <a href="<?= buildStatUrl(0) ?>" class="stat-card-link">
        <div class="stat-card <?= ($status === '0') ? 'active' : '' ?>">
            <div class="stat-card-icon danger"><i data-lucide="x-circle" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $inactiveCount ?></div>
            <div class="stat-label">Inactive / Suspended</div>
        </div>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="card-header-form" style="width:100%; display:flex; gap:0.5rem; flex-wrap:wrap;">
            <?php if(isset($_GET['sort_by'])): ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order'] ?? 'ASC') ?>">
            <?php endif; ?>
            <input type="text" name="search" class="form-control" placeholder="Search name, code, contact…" style="flex:1; min-width:200px;" value="<?= htmlspecialchars($search) ?>">
            <select name="category" class="form-control form-control--inline" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($CATEGORY_LABELS as $val => $meta): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $category === $val ? 'selected' : '' ?>>
                        <?= htmlspecialchars($meta['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control form-control--inline" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <div class="card-header-actions" style="margin-left:auto;">
                <button type="submit" class="btn btn-secondary"><i data-lucide="search" style="width:16px;height:16px;"></i></button>
                <?php if ($search || $category || $status !== ''): ?>
                    <a href="index.php" class="btn btn-ghost" title="Clear Filters">
                        <i data-lucide="rotate-ccw" style="width:16px;height:16px;"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div style="padding:0.75rem 1.5rem;background:var(--bg-muted);border-bottom:1px solid var(--border-color);font-size:0.875rem;color:var(--text-muted);display:flex;justify-content:space-between;">
        <span>Showing <?= count($suppliers) ?> of <?= number_format($total) ?> suppliers</span>
    </div>

    <div class="table-container" style="margin-bottom:0;border:none;">
        <table>
            <thead>
                <tr>
                    <th>
                        <a href="<?= buildSortUrl('supplier_code', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">
                            Code <?= getSortIcon('supplier_code', $currentSortBy, $currentSortOrder) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('company_name', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">
                            Company Name <?= getSortIcon('company_name', $currentSortBy, $currentSortOrder) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('category', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">
                            Category <?= getSortIcon('category', $currentSortBy, $currentSortOrder) ?>
                        </a>
                    </th>
                    <th>Contact Details</th>
                    <th>
                        <a href="<?= buildSortUrl('is_active', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">
                            Status <?= getSortIcon('is_active', $currentSortBy, $currentSortOrder) ?>
                        </a>
                    </th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i data-lucide="truck" class="empty-state-icon"></i>
                                <h3>No Suppliers Found</h3>
                                <p style="margin-bottom:1rem;">We couldn't find any suppliers matching your current filters.</p>
                                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                                <?php if ($authUser->hasPermission('suppliers.create')): ?>
                                    <a href="supplier-add.php" class="btn btn-primary" style="margin-left:0.5rem;">Add Supplier</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    foreach ($suppliers as $s):
                        $cat = $CATEGORY_LABELS[$s['category']] ?? ['label' => $s['category'], 'class' => 'secondary'];
                        ?>
                        <tr>
                            <td><code><?= htmlspecialchars($s['supplier_code']) ?></code></td>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($s['company_name']) ?>
                            </td>
                            <td><span class="badge badge-<?= $cat['class'] ?>"><?= $cat['label'] ?></span></td>
                            <td>
                                <div class="contact-details">
                                    <div><?= htmlspecialchars($s['contact_person'] ?? '—') ?></div>
                                    <div class="contact-meta">
                                        <?php if(!empty($s['phone_primary'])): ?>
                                            <span title="Phone"><i data-lucide="phone" style="width:10px;height:10px;"></i> <?= htmlspecialchars($s['phone_primary']) ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($s['email'])): ?>
                                            <span title="Email"><i data-lucide="mail" style="width:10px;height:10px;"></i> <?= htmlspecialchars($s['email']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $s['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; align-items:center; gap:4px;">
                                    <a href="supplier-view.php?id=<?= $s['supplier_id'] ?>" class="btn btn-sm btn-ghost" title="View">
                                        <i data-lucide="eye" style="width:16px;height:16px;"></i>
                                    </a>
                                    <?php if ($authUser->hasPermission('suppliers.update')): ?>
                                        <a href="supplier-edit.php?id=<?= $s['supplier_id'] ?>" class="btn btn-sm btn-ghost" title="Edit">
                                            <i data-lucide="pencil" style="width:16px;height:16px;"></i>
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
