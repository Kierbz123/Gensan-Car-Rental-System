<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$authUser->requirePermission('customers.view');

$pageTitle = "Client Directory";
require_once '../../includes/header.php';

// Instantiate OOP Logic
try {
    $customerObj = new Customer();
    $stats = $customerObj->getStats();

    $filters = [];
    if (!empty($_GET['type'])) $filters['type'] = $_GET['type'];
    if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
    if (!empty($_GET['blacklisted'])) $filters['blacklisted'] = $_GET['blacklisted'];
    if (!empty($_GET['sort_by'])) $filters['sort_by'] = $_GET['sort_by'];
    if (!empty($_GET['sort_order'])) $filters['sort_order'] = $_GET['sort_order'];
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $result = $customerObj->getAll($filters, $page, 10);
    $customers = $result['data'];
    $totalPages = $result['total_pages'];

} catch (Exception $e) {
    $_SESSION['error_message'] = "Failed to load customer data: " . $e->getMessage();
    $stats = ['total' => 0, 'corporate' => 0, 'individual' => 0, 'blacklisted' => 0];
    $customers = [];
    $result = ['total' => 0];
    $totalPages = 1;
}

$currentSortBy = $filters['sort_by'] ?? 'created_at';
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

function buildStatUrl($typeValue, $isBlacklist = false) {
    $params = $_GET;
    if ($isBlacklist) {
        if (!empty($params['blacklisted'])) unset($params['blacklisted']);
        else {
            $params['blacklisted'] = 1;
            unset($params['type']);
        }
    } else {
        unset($params['blacklisted']);
        if (isset($params['type']) && $params['type'] === $typeValue) {
            unset($params['type']);
        } else {
            if ($typeValue) {
                $params['type'] = $typeValue;
            } else {
                unset($params['type']);
            }
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
.stat-card.active-blacklisted {
    border-color: var(--danger) !important;
    box-shadow: 0 0 0 1px var(--danger) !important;
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
    <a href="<?= buildStatUrl('') ?>" class="stat-card-link">
        <div class="stat-card <?= (empty($_GET['type']) && empty($_GET['blacklisted'])) ? 'active' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="users" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
            <div class="stat-label">Total Base</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('corporate') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['type']) && $_GET['type'] === 'corporate' && empty($_GET['blacklisted'])) ? 'active' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="briefcase" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= number_format($stats['corporate']) ?></div>
            <div class="stat-label">Corporate</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('individual') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['type']) && $_GET['type'] === 'individual' && empty($_GET['blacklisted'])) ? 'active' : '' ?>">
            <div class="stat-card-icon success"><i data-lucide="user" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= number_format($stats['individual']) ?></div>
            <div class="stat-label">Individual</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('', true) ?>" class="stat-card-link">
        <div class="stat-card <?= (!empty($_GET['blacklisted'])) ? 'active-blacklisted' : '' ?>">
            <div class="stat-card-icon danger"><i data-lucide="user-x" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= number_format($stats['blacklisted']) ?></div>
            <div class="stat-label">Blacklisted</div>
        </div>
    </a>
</div>

<div class="card">
    <div style="padding:.875rem 1.25rem; border-bottom:1px solid var(--border-color);">
        <form method="GET" id="filterForm" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;width:100%;">
            
            <!-- Search -->
            <div style="position:relative;flex:1;min-width:200px;">
                <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
                <input type="text" name="search" id="searchInput" class="form-control" style="padding-left:34px;width:100%;" placeholder="Search name, code, or identity..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>

            <?php if (isset($_GET['type'])): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($_GET['type']) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['sort_by'])): ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order']) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['blacklisted'])): ?>
                <input type="hidden" name="blacklisted" value="1">
            <?php endif; ?>

            <div style="display:flex;gap:.5rem;flex-shrink:0;">
                <button type="submit" class="btn btn-primary btn-sm" id="applyFilterBtn">
                    <i data-lucide="search" style="width:13px;height:13px;"></i> Search
                </button>
                <?php if (!empty($_GET['search']) || !empty($_GET['type']) || !empty($_GET['blacklisted'])): ?>
                    <a href="index.php" class="btn btn-ghost btn-sm" title="Clear Filters"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></a>
                <?php endif; ?>
            </div>

            <!-- Result count -->
            <span style="margin-left:auto;font-size:.8125rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0;">
                <?= number_format($result['total']) ?> client<?= $result['total'] !== 1 ? 's' : '' ?>
            </span>
        </form>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>
                        <a href="<?= buildSortUrl('first_name', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Profile <?= getSortIcon('first_name', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('phone_primary', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Contact <?= getSortIcon('phone_primary', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('id_type', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Identity <?= getSortIcon('id_type', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('customer_type', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Type <?= getSortIcon('customer_type', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('is_blacklisted', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Status <?= getSortIcon('is_blacklisted', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)):
                    foreach ($customers as $c): ?>
                        <tr <?= $c['is_blacklisted'] ? 'style="background:var(--danger-light);"' : '' ?>>
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
                                <div class="table-actions" style="text-align:right;">
                                    <a href="customer-view.php?id=<?= (int) $c['customer_id'] ?>"
                                        class="btn btn-ghost btn-sm">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i data-lucide="users" class="empty-state-icon"></i>
                                <h3>No Clients Found</h3>
                                <p style="margin-bottom:1rem;">We couldn't find any clients matching your current criteria.</p>
                                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                                <?php if ($authUser->hasPermission('customers.create')): ?>
                                    <a href="customer-add.php" class="btn btn-primary" style="margin-left:0.5rem;">Register New Client</a>
                                <?php endif; ?>
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
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"
                class="btn <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>