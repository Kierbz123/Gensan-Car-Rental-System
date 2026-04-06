<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$pageTitle = "Rental Operations";
require_once '../../includes/header.php';

// Actual DB Logic
try {
    $rentalObj = new RentalAgreement();
    $stats = $rentalObj->getStats();

    $type = $_GET['type'] ?? 'all';
    $search = $_GET['search'] ?? '';

    $statusFilter = [RENTAL_STATUS_RESERVED, RENTAL_STATUS_CONFIRMED, RENTAL_STATUS_ACTIVE];
    if ($type === 'dispatch') {
        $statusFilter = [RENTAL_STATUS_RESERVED, RENTAL_STATUS_CONFIRMED];
    } elseif ($type === 'return') {
        $statusFilter = [RENTAL_STATUS_ACTIVE];
    }

    $filters = ['status' => $statusFilter];
    if ($search !== '') {
        $filters['search'] = $search;
    }

    $result = $rentalObj->getAll($filters, 1, 20);
    $agreements = $result['data'] ?? [];

    $historyFilters = ['status' => [RENTAL_STATUS_RETURNED, RENTAL_STATUS_CANCELLED]];
    $historyResult = $rentalObj->getAll($historyFilters, 1, 50);
    $rentalHistory = $historyResult['data'] ?? [];
} catch (Exception $e) {
    $_SESSION['error_message'] = "Failed to load rental data: " . $e->getMessage();
    $stats = ['total' => 0, 'reserved' => 0, 'confirmed' => 0, 'active' => 0, 'overdue' => 0];
    $agreements = [];
    $rentalHistory = [];
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Rental Operations</h1>
        <p>Coordinating vehicle assignments and reservation lifecycles.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('history-panel').classList.add('open')">
            <i data-lucide="history" style="width:16px;height:16px;"></i> History
        </button>
        <?php if ($authUser->hasPermission('rentals.create')): ?>
        <a href="reserve.php" class="btn btn-primary">
            <i data-lucide="calendar-plus" style="width:16px;height:16px;"></i> New Rental
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom:2rem;">
    <div class="stat-card">
        <div class="stat-value"><?= $stats['reserved'] ?? 0 ?></div>
        <div class="stat-label">Reserved</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['confirmed'] ?? 0 ?></div>
        <div class="stat-label">Confirmed</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['active'] ?? 0 ?></div>
        <div class="stat-label">Active</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:var(--danger);"><?= $stats['overdue'] ?? 0 ?></div>
        <div class="stat-label">Overdue</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">94%</div>
        <div class="stat-label">Efficiency</div>
    </div>
</div>

<div class="card">
    <div class="card-header"
        style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <form method="GET" class="card-header-form" style="display:flex;gap:0.5rem;align-items:center;">
            <input type="text" name="search" class="form-control" placeholder="Search agreements..."
                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <?php if (isset($_GET['type'])): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($_GET['type']) ?>">
            <?php endif; ?>
            <div class="card-header-actions" style="display:flex;gap:0.5rem;">
                <a href="?type=all"
                    class="btn <?= (!isset($_GET['type']) || $_GET['type'] === 'all') ? 'btn-secondary' : 'btn-ghost' ?> btn-sm">All</a>
                <a href="?type=dispatch"
                    class="btn <?= (isset($_GET['type']) && $_GET['type'] === 'dispatch') ? 'btn-secondary' : 'btn-ghost' ?> btn-sm">To
                    be Dispatch</a>
                <a href="?type=return"
                    class="btn <?= (isset($_GET['type']) && $_GET['type'] === 'return') ? 'btn-secondary' : 'btn-ghost' ?> btn-sm">To
                    be Return</a>
                <a href="index.php" class="btn btn-ghost btn-sm"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" data-lucide="rotate-ccw" aria-hidden="true"
                        style="width:14px;height:14px;" class="lucide lucide-rotate-ccw">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                        <path d="M3 3v5h5"></path>
                    </svg></a>
            </div>
        </form>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Agreement #</th>
                    <th>Customer</th>
                    <th>Vehicle</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($agreements)):
                    foreach ($agreements as $ra):
                        $badgeClass = match ($ra['status']) {
                            RENTAL_STATUS_RESERVED => 'badge-info',
                            RENTAL_STATUS_ACTIVE => 'badge-success',
                            RENTAL_STATUS_CANCELLED => 'badge-danger',
                            default => 'badge-secondary'
                        };
                        ?>
                        <tr>
                            <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($ra['agreement_number']) ?>
                            </td>
                            <td><?= htmlspecialchars($ra['customer_name']) ?></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($ra['brand'] . ' ' . $ra['model']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($ra['plate_number']) ?>
                                </div>
                            </td>
                            <td style="font-size:0.75rem;">
                                <?= date('M d', strtotime($ra['rental_start_date'])) ?> -
                                <?= date('M d', strtotime($ra['rental_end_date'])) ?>
                                <?php if ($ra['status'] === RENTAL_STATUS_ACTIVE && strtotime($ra['rental_end_date']) < time()): ?>
                                    <div style="color:var(--danger);font-weight:700;font-size:10px;">BREACHED</div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($ra['status']) ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="view.php?id=<?= $ra['agreement_id'] ?>" class="btn btn-ghost btn-sm">Details</a>
                                    <?php if ($ra['status'] === RENTAL_STATUS_RESERVED || $ra['status'] === RENTAL_STATUS_CONFIRMED): ?>
                                        <a href="check-out.php?id=<?= $ra['agreement_id'] ?>"
                                            class="btn btn-primary btn-sm">Dispatch</a>
                                    <?php elseif ($ra['status'] === RENTAL_STATUS_ACTIVE): ?>
                                        <a href="check-in.php?id=<?= $ra['agreement_id'] ?>"
                                            class="btn btn-success btn-sm">Return</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);">No active rentals
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ───── Rental History Slide-in Panel ───── -->
<div id="history-panel" style="position:fixed;top:0;right:0;width:560px;max-width:100vw;height:100vh;background:var(--bg-surface,#fff);box-shadow:-4px 0 24px rgba(0,0,0,.15);z-index:10000;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,.0,.2,1);display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);">
        <h2 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
            <i data-lucide="history" style="width:20px;height:20px;color:var(--primary);"></i>
            Transaction History
        </h2>
        <button onclick="document.getElementById('history-panel').classList.remove('open')"
            style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-muted);">
            <i data-lucide="x" style="width:20px;height:20px;"></i>
        </button>
    </div>
    <div style="flex:1;overflow-y:auto;padding:1rem 1.5rem;background:var(--bg-body, #f4f6f8);">
        <?php if (empty($rentalHistory)): ?>
            <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                <i data-lucide="file-question" style="width:40px;height:40px;display:block;margin:0 auto .75rem;opacity:.3;"></i>
                <p style="font-weight:600;">No past transactions</p>
                <p style="font-size:.85rem;">History of returned and cancelled rentals will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($rentalHistory as $hr): ?>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:1rem;margin-bottom:1rem;border:1px solid var(--border-color);border-radius:var(--radius-md);background:var(--bg-surface,#fff);box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <div style="flex-grow:1;padding-right:1rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <div style="font-weight:700;color:var(--primary);font-family:monospace;"><?= htmlspecialchars($hr['agreement_number']) ?></div>
                            <?php 
                                $hBadgeClass = match ($hr['status']) {
                                    RENTAL_STATUS_RETURNED => 'badge-success',
                                    RENTAL_STATUS_CANCELLED => 'badge-danger',
                                    default => 'badge-secondary'
                                };
                            ?>
                            <span class="badge <?= $hBadgeClass ?>"><?= htmlspecialchars($hr['status']) ?></span>
                        </div>
                        <div style="font-size:.9rem;font-weight:600;margin-bottom:4px;">
                            <?= htmlspecialchars($hr['customer_name']) ?>
                        </div>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:8px;">
                            <i data-lucide="car" style="width:12px;height:12px;vertical-align:-2px;margin-right:2px;"></i>
                            <?= htmlspecialchars($hr['brand'] . ' ' . $hr['model']) ?> 
                            <span style="font-family:monospace;background:var(--bg-muted);padding:1px 4px;border-radius:3px;margin-left:4px;"><?= htmlspecialchars($hr['plate_number']) ?></span>
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;gap:1rem;">
                            <span>
                                <i data-lucide="calendar" style="width:12px;height:12px;vertical-align:-2px;margin-right:2px;"></i>
                                <?= date('M d, Y', strtotime($hr['rental_start_date'])) ?> - <?= date('M d, Y', strtotime($hr['rental_end_date'])) ?>
                            </span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.5rem;min-width:80px;text-align:right;">
                        <a href="view.php?id=<?= $hr['agreement_id'] ?>" class="btn btn-ghost btn-sm" style="justify-content:center;">Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<div id="history-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:9999;opacity:0;pointer-events:none;transition:opacity .3s;"
    onclick="document.getElementById('history-panel').classList.remove('open')"></div>
<style>
    #history-panel.open { transform: translateX(0) !important; }
    #history-panel.open ~ #history-overlay { opacity: 1 !important; pointer-events: auto !important; }
</style>

<?php require_once '../../includes/footer.php'; ?>