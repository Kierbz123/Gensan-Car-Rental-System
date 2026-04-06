<?php
/**
 * Confirmed Rentals View
 * Path: modules/rentals/confirmed.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$authUser->requirePermission('rentals.view');

try {
    $rentalObj = new RentalAgreement();

    // Pagination and Filters
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = ITEMS_PER_PAGE ?? 20;

    $filters = ['status' => RENTAL_STATUS_CONFIRMED];
    if ($search) {
        $filters['search'] = $search;
    }

    $result = $rentalObj->getAll($filters, $page, $perPage);
    $agreements = $result['data'] ?? [];
    $totalCount = $result['total'] ?? 0;
    $totalPages = $result['total_pages'] ?? 0;
} catch (Exception $e) {
    $agreements = [];
    $totalCount = 0;
    $totalPages = 0;
    $error = "Failed to load confirmed bookings: " . $e->getMessage();
}

$pageTitle = "Confirmed Rental Bookings";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Confirmed Bookings</h1>
        <p>Rental agreements awaiting dispatch.</p>
    </div>
    <div class="page-actions">
        <a href="reserve.php" class="btn btn-primary">
            <i data-lucide="calendar-plus" style="width:16px;height:16px;"></i> New Rental
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Operations
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div
        style="background:var(--danger-light); color:var(--danger); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1.5rem;">
        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i data-lucide="check-circle"
                style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--success)"></i>
            Pending Dispatches
        </h2>
        <div class="card-header-filters">
            <form method="GET" class="card-header-form" style="display:flex;gap:0.5rem;align-items:center;">
                <input type="text" name="search" class="form-control" placeholder="Search agreement #, client, etc."
                    value="<?= htmlspecialchars($search) ?>" style="min-width:250px;">
                <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="search"
                        style="width:14px;height:14px;"></i></button>
                <?php if ($search): ?>
                    <a href="confirmed.php" class="btn btn-ghost btn-sm"><i data-lucide="x"
                            style="width:14px;height:14px;"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Agreement #</th>
                    <th>Customer</th>
                    <th>Vehicle</th>
                    <th>Pickup</th>
                    <th>Return</th>
                    <th>Total</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agreements)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:3rem;color:var(--text-muted);">No confirmed
                            bookings found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($agreements as $ra): ?>
                        <tr>
                            <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($ra['agreement_number']) ?>
                            </td>
                            <td><?= htmlspecialchars($ra['customer_name'] ?? '') ?>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($ra['brand'] . ' ' . $ra['model']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($ra['plate_number']) ?>
                                </div>
                            </td>
                            <td><?= date('M d, Y', strtotime($ra['rental_start_date'])) ?></td>
                            <td><?= date('M d, Y', strtotime($ra['rental_end_date'])) ?></td>
                            <td style="font-weight:600;">₱ <?= number_format($ra['total_amount'], 2) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="view.php?id=<?= $ra['agreement_id'] ?>" class="btn btn-ghost btn-sm">Details</a>
                                    <?php if ($authUser->hasPermission('rentals.update')): ?>
                                        <a href="check-out.php?id=<?= $ra['agreement_id'] ?>"
                                            class="btn btn-primary btn-sm">Dispatch</a>
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
                class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>