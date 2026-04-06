<?php
/**
 * Maintenance History View
 * Path: modules/maintenance/history.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('maintenance.view');

$db = Database::getInstance();

// Pagination setup
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE ?? 20;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');

$where = ["r.status = 'completed'"];
$params = [];

if ($search !== '') {
    $where[] = "(v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR r.service_type LIKE ? OR r.service_description LIKE ?)";
    $likeSearch = "%{$search}%";
    $params = array_merge($params, [$likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch]);
}

$whereClause = implode(' AND ', $where);

// Create a combined view of logs and completed schedules
$combinedSql = "
    SELECT 
        log_id as id,
        vehicle_id,
        service_type,
        service_description,
        service_date,
        mileage_at_service,
        total_cost,
        status,
        created_by,
        'log' as record_type
    FROM maintenance_logs
    
    UNION ALL
    
    SELECT 
        schedule_id as id,
        vehicle_id,
        service_type,
        notes as service_description,
        last_service_date as service_date,
        last_service_mileage as mileage_at_service,
        0 as total_cost,
        status,
        created_by,
        'schedule' as record_type
    FROM maintenance_schedules
";

try {
    $totalCount = $db->fetchColumn(
        "SELECT COUNT(*) 
         FROM ($combinedSql) r
         JOIN vehicles v ON r.vehicle_id = v.vehicle_id
         WHERE $whereClause",
        $params
    );
    $totalPages = ceil($totalCount / $perPage);

    $records = $db->fetchAll(
        "SELECT r.*, v.plate_number, v.brand, v.model,
                CONCAT(u.first_name, ' ', u.last_name) as logger_name
         FROM ($combinedSql) r
         JOIN vehicles v ON r.vehicle_id = v.vehicle_id
         LEFT JOIN users u ON r.created_by = u.user_id
         WHERE $whereClause
         ORDER BY r.service_date DESC
         LIMIT $perPage OFFSET $offset",
        $params
    );
} catch (Exception $e) {
    error_log($e->getMessage());
    $records = [];
    $totalCount = 0;
    $totalPages = 0;
}

$pageTitle = 'Maintenance History';
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Maintenance History</h1>
        <p>Comprehensive records of all completed service procedures.</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Hub
        </a>
        <a href="schedule-add.php" class="btn btn-primary">
            <i data-lucide="calendar-plus" style="width:16px;height:16px;"></i> Schedule Service
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="justify-content: flex-end; padding-bottom: 1rem; border-bottom: none;">
        <div class="card-header-filters" style="width: 100%; max-width: 400px; margin-left: auto;">
            <form method="GET" class="card-header-form" style="display:flex; flex-direction:row; gap:0.5rem; align-items:center; width:100%;">
                <input type="text" name="search" class="form-control" placeholder="Search by vehicle, service type..."
                    value="<?= htmlspecialchars($search ?? '') ?>" style="flex:1; height:38px; box-sizing:border-box; border-radius:var(--radius-md);">
                <button type="submit" class="btn btn-secondary" style="display:flex; align-items:center; justify-content:center; flex-shrink:0; height:38px; width:38px; padding:0; border-radius:var(--radius-md); box-sizing:border-box;">
                    <i data-lucide="search" style="width:16px;height:16px;"></i>
                </button>
                <?php if ($search): ?>
                    <a href="history.php" class="btn btn-ghost" style="display:flex; align-items:center; justify-content:center; flex-shrink:0; height:38px; width:38px; padding:0; border-radius:var(--radius-md); box-sizing:border-box;">
                        <i data-lucide="x" style="width:16px;height:16px;"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Service Type</th>
                    <th>Date Completed</th>
                    <th>Mileage</th>
                    <th>Total Cost</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);">No completed
                            maintenance records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;">
                                    <?= htmlspecialchars($record['brand'] . ' ' . $record['model']) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--primary);font-weight:700;">
                                    <?= htmlspecialchars($record['plate_number']) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;">
                                    <?= ucwords(str_replace('_', ' ', $record['service_type'])) ?>
                                </div>
                                <div
                                    style="font-size:0.75rem;color:var(--text-muted); max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars($record['service_description'] ?? '') ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;">
                                    <?= $record['service_date'] ? date('M d, Y', strtotime($record['service_date'])) : 'Unknown Date' ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    Logged by
                                    <?= htmlspecialchars($record['logger_name'] ?? 'System') ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;">
                                    <?= number_format($record['odometer_reading'] ?? $record['mileage_at_service'] ?? 0) ?> km
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;">₱
                                    <?= number_format($record['total_cost'] ?? 0, 2) ?>
                                </div>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <?php if ($record['record_type'] === 'schedule'): ?>
                                        <a href="service-view.php?id=<?= $record['id'] ?>" class="btn btn-ghost btn-sm">Details</a>
                                    <?php else: ?>
                                        <a href="../asset-tracking/vehicle-details.php?id=<?= $record['vehicle_id'] ?>"
                                            class="btn btn-ghost btn-sm">Asset Profile</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($totalPages) && $totalPages > 1): ?>
    <div style="margin-top:2rem; display:flex; justify-content:center; gap:0.5rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&<?= http_build_query(array_merge($_GET, ['page' => null])) ?>"
                class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>