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

$result    = $supplier->getAll($filters, $page, ITEMS_PER_PAGE);
$suppliers = $result['data'];
$total     = $result['total'];

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
?>

<?php if ($successMsg): ?>
    <div id="toast-sup"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:var(--success);color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;max-width:380px;">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span><?= htmlspecialchars($successMsg) ?></span>
    </div>
    <script>setTimeout(() => { document.getElementById('toast-sup')?.remove(); }, 3500);</script>
<?php endif; ?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="truck"
                style="width:28px;height:28px;vertical-align:-5px;margin-right:10px;color:var(--primary)"></i>Supplier
            Management</h1>
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

<div class="card">
    <div class="card-header">
        <form method="GET" class="card-header-form">
            <input type="text" name="search" class="form-control" placeholder="Search name, code, contact…"
                value="<?= htmlspecialchars($search) ?>">
            <select name="category" class="form-control form-control--inline" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($CATEGORY_LABELS as $val => $meta): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $category === $val ? 'selected' : '' ?>>
                        <?= htmlspecialchars($meta['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control form-control--inline" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <div class="card-header-actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <?php if ($search || $category || $status !== ''): ?>
                    <a href="index.php" class="btn btn-ghost btn-sm" title="Clear Filters">
                        <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div
        style="padding:0.75rem 1.5rem;background:var(--bg-muted);border-bottom:1px solid var(--border-color);font-size:0.875rem;color:var(--text-muted);display:flex;justify-content:space-between;">
        <span>Showing <?= count($suppliers) ?> of <?= number_format($total) ?> suppliers</span>
    </div>

    <div class="table-container" style="margin-bottom:0;border:none;">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Company Name</th>
                    <th>Category</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
                            <i data-lucide="truck"
                                style="width:32px;height:32px;display:block;margin:0 auto .5rem;opacity:.4"></i>
                            No suppliers found.
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
                            <td><?= htmlspecialchars($s['contact_person'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($s['phone_primary'] ?? '—') ?></td>
                            <td>
                                <span class="badge badge-<?= $s['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 4px;">
                                    <a href="supplier-view.php?id=<?= $s['supplier_id'] ?>" class="btn btn-sm btn-secondary"
                                        title="View">
                                        <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                    </a>
                                    <?php if ($authUser->hasPermission('suppliers.update')): ?>
                                        <a href="supplier-edit.php?id=<?= $s['supplier_id'] ?>"
                                            class="btn btn-sm btn-secondary" title="Edit">
                                            <i data-lucide="pencil" style="width:14px;height:14px;"></i>
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
                <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>"
                    class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
