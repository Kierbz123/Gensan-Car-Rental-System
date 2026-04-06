<?php
/**
 * Mechanics List Page
 * Path: modules/maintenance/mechanics/index.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

$authUser->requirePermission('maintenance.view');

$pageTitle = 'Mechanics';
$db = Database::getInstance();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE;
$search = sanitize($_GET['search'] ?? '');

$where = ['deleted_at IS NULL'];
$params = [];

if ($search) {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR specialization LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s]);
}

$whereClause = implode(' AND ', $where);
$total = $db->fetchColumn("SELECT COUNT(*) FROM mechanics WHERE {$whereClause}", $params);
$offset = ($page - 1) * $perPage;

$mechanics = $db->fetchAll(
    "SELECT * FROM mechanics WHERE {$whereClause} ORDER BY last_name, first_name LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

include_once '../../../includes/header.php';
?>
<div class="flex-between mb-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Mechanics</h1>
        <p class="text-sm text-gray-500">Manage maintenance technicians</p>
    </div>
    <a href="mechanic-add.php" class="btn btn-primary">
        <i data-lucide="plus"></i> Add Mechanic
    </a>
</div>

<!-- Search -->
<form method="GET" class="mb-4">
    <div class="search-container" style="width:320px">
        <i data-lucide="search" class="text-gray-400 w-4 h-4"></i>
        <input type="text" name="search" class="search-input" placeholder="Search mechanics..."
            value="<?php echo htmlspecialchars($search); ?>">
    </div>
</form>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Specialization</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mechanics as $m): ?>
                <tr>
                    <td class="font-medium">
                        <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($m['specialization'] ?? '—'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($m['phone'] ?? '—'); ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $m['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo ucfirst($m['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="mechanic-assign.php?id=<?php echo $m['mechanic_id']; ?>"
                            class="btn btn-secondary text-sm py-1 px-3">Assign</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($mechanics)): ?>
                <tr>
                    <td colspan="5" class="text-center text-gray-400 py-8">No mechanics found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php echo pagination($total, $page, $perPage); ?>
<?php include_once '../../../includes/footer.php'; ?>