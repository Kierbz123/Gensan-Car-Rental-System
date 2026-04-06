<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$authUser->requirePermission('customers.view');

$pageTitle = "Client Directory";
require_once '../../includes/header.php';

// Handle filters
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = ["deleted_at IS NULL"];
$params = [];

if ($type) {
    $where[] = "customer_type = ?";
    $params[] = $type;
}

if ($search) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR customer_code LIKE ? OR email LIKE ? OR phone_primary LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

$whereClause = implode(' AND ', $where);

// Summary Stats
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN customer_type = 'corporate' THEN 1 ELSE 0 END), 0) as corporate,
        COALESCE(SUM(CASE WHEN customer_type != 'corporate' THEN 1 ELSE 0 END), 0) as individual,
        COALESCE(SUM(CASE WHEN is_blacklisted = 1 THEN 1 ELSE 0 END), 0) as blacklisted
    FROM customers WHERE deleted_at IS NULL
");

// Fetch Customers
$customers = $db->fetchAll(
    "SELECT * FROM customers WHERE $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
) ?: [];

$totalCount = (int) ($db->fetchColumn("SELECT COUNT(*) FROM customers WHERE $whereClause", $params) ?? 0);
$totalPages = $totalCount > 0 ? ceil($totalCount / $perPage) : 1;
?>

<div class="page-header">
    <div class="page-title">
        <h1>Client Database</h1>
        <p>Managing demographics and rental behaviors for optimal service delivery.</p>
    </div>
    <?php if ($authUser->hasPermission('customers.create')): ?>
    <div class="page-actions">
        <a href="customer-add.php" class="btn btn-primary">
            <i data-lucide="user-plus" style="width:16px;height:16px;"></i> Register Client
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon primary"><i data-lucide="users" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= number_format($stats['total']) ?></div>
        <div class="stat-label">Total Base</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon primary"><i data-lucide="briefcase" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= number_format($stats['corporate']) ?></div>
        <div class="stat-label">Corporate</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon success"><i data-lucide="user" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= number_format($stats['individual']) ?></div>
        <div class="stat-label">Individual</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon danger"><i data-lucide="user-x" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= number_format($stats['blacklisted']) ?></div>
        <div class="stat-label">Blacklisted</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form">
                <input type="text" name="search" class="form-control" placeholder="Search name, code, or identity..."
                    value="<?= htmlspecialchars($search) ?>">
                <div class="card-header-actions">
                    <a href="?type=individual<?= $search ? '&search=' . urlencode($search) : '' ?>"
                        class="btn <?= $type == 'individual' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Individuals</a>
                    <a href="?type=corporate<?= $search ? '&search=' . urlencode($search) : '' ?>"
                        class="btn <?= $type == 'corporate' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Corporate</a>
                    <a href="index.php" class="btn btn-ghost btn-sm"><i data-lucide="rotate-ccw"
                            style="width:14px;height:14px;"></i></a>
                </div>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Profile</th>
                    <th>Contact</th>
                    <th>Identity</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)):
                    foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;">
                                    <?= htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--primary);font-weight:700;">
                                    <?= htmlspecialchars($c['customer_code'] ?? '') ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:0.875rem;"><?= htmlspecialchars($c['phone_primary'] ?? '—') ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($c['email'] ?? '') ?>
                                </div>
                            </td>
                            <td style="font-size:0.75rem;">
                                <div style="font-weight:700;">
                                    <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $c['id_type'] ?? ''))) ?>
                                </div>
                                <div style="color:var(--text-muted);">EXP:
                                    <?= !empty($c['id_expiry_date']) ? date('M d, Y', strtotime($c['id_expiry_date'])) : '—' ?>
                                </div>
                            </td>
                            <td><span
                                    class="badge badge-secondary"><?= htmlspecialchars(ucfirst($c['customer_type'] ?? '')) ?></span>
                            </td>
                            <td>
                                <?php if ($c['is_blacklisted']): ?>
                                    <span class="badge badge-danger">Blacklisted</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Verified</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="customer-view.php?id=<?= (int) $c['customer_id'] ?>"
                                        class="btn btn-ghost btn-sm">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);">No clients found
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
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"
                class="btn <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>