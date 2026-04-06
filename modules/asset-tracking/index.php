<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$vehicle = new Vehicle();

// --- Handle recommission POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['recommission_id'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        try {
            $vehicle->recommission($_POST['recommission_id'], $authUser->getData()['user_id']);
            $_SESSION['success_message'] = 'Vehicle recommissioned successfully.';
        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
    header('Location: index.php');
    exit;
}

$pageTitle = "Fleet Inventory";
require_once '../../includes/header.php';

// Filters
$category_id = isset($_GET['category']) && is_scalar($_GET['category']) ? $_GET['category']  : null;
$status      = isset($_GET['status'])   && is_scalar($_GET['status'])   ? $_GET['status']   : null;
$search      = isset($_GET['search'])   && is_scalar($_GET['search'])   ? trim($_GET['search']) : null;
$page        = isset($_GET['page'])     && is_numeric($_GET['page'])    ? max(1, intval($_GET['page'])) : 1;
$perPage     = 12; // Card grid looks best with 12

$filters = array_filter(
    ['category_id' => $category_id, 'status' => $status, 'search' => $search],
    fn($v) => $v !== null && $v !== ''
);

try {
    $vehiclesResult = $vehicle->getAll($filters, $page, $perPage);
    $vehicles       = $vehiclesResult['data']       ?? [];
    $totalItems     = $vehiclesResult['total']       ?? 0;
    $totalPages     = $vehiclesResult['total_pages'] ?? 1;

    $categories     = Database::getInstance()->fetchAll("SELECT * FROM vehicle_categories WHERE is_active = TRUE ORDER BY category_name");
    $decommissioned = $vehicle->getDecommissioned();

    // Stats (unfiltered totals for the dashboard row)
    $db = Database::getInstance();
    $stats = $db->fetchOne(
        "SELECT
            COUNT(*)                                                    AS total,
            SUM(current_status = 'available')                           AS available,
            SUM(current_status = 'rented')                              AS rented,
            SUM(current_status = 'maintenance')                         AS maintenance,
            SUM(current_status = 'reserved')                            AS reserved,
            SUM(current_status NOT IN ('available','rented','maintenance','reserved')) AS other
         FROM vehicles WHERE deleted_at IS NULL"
    );
} catch (Exception $e) {
    $_SESSION['error_message'] = "Failed to load fleet inventory: " . $e->getMessage();
    $vehicles = []; $totalPages = 1; $totalItems = 0; $categories = []; $decommissioned = []; $stats = [];
}

$successMsg = '';
if (!empty($_SESSION['success_message'])) { $successMsg = $_SESSION['success_message']; unset($_SESSION['success_message']); }
$errorMsg = '';
if (!empty($_SESSION['error_message'])) { $errorMsg = $_SESSION['error_message']; unset($_SESSION['error_message']); }
?>

<!-- ── Toast ──────────────────────────────────────────────────────── -->
<?php if ($successMsg): ?>
<div id="toast-fleet" style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:var(--success);color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;max-width:380px;">
    <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
    <span><?= htmlspecialchars($successMsg) ?></span>
</div>
<script>setTimeout(() => { document.getElementById('toast-fleet')?.remove(); }, 3500);</script>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div id="toast-error" style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:var(--danger);color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;max-width:380px;">
    <i data-lucide="alert-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
    <span><?= htmlspecialchars($errorMsg) ?></span>
</div>
<script>setTimeout(() => { document.getElementById('toast-error')?.remove(); }, 4500);</script>
<?php endif; ?>

<!-- ── Page Header ─────────────────────────────────────────────────── -->
<div class="page-header">
    <div class="page-title">
        <h1>
            <i data-lucide="truck" style="width:28px;height:28px;vertical-align:-5px;margin-right:10px;color:var(--accent)"></i>
            Fleet Inventory
        </h1>
        <p>Real-time asset tracking and physical status management.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('decom-panel').classList.add('open')" style="position:relative;" id="decommissionedBtn">
            <i data-lucide="archive" style="width:16px;height:16px;"></i> Decommissioned
            <?php if (!empty($decommissioned)): ?>
                <span style="position:absolute;top:-6px;right:-6px;background:var(--danger);color:#fff;font-size:.7rem;font-weight:700;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;"><?= count($decommissioned) ?></span>
            <?php endif; ?>
        </button>
        <?php if ($authUser->hasPermission('vehicles.create')): ?>
        <a href="vehicle-add.php" class="btn btn-primary" id="registerAssetBtn">
            <i data-lucide="plus" style="width:16px;height:16px;"></i> Register Asset
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Stats Row ──────────────────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:2rem;">

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('')" title="All fleet vehicles">
        <div class="stat-card-icon primary">
            <i data-lucide="car"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)($stats['total'] ?? 0) ?></div>
            <div class="stat-label">Total Fleet</div>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('available')" title="Available vehicles">
        <div class="stat-card-icon success">
            <i data-lucide="circle-check-big"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color:var(--success)"><?= (int)($stats['available'] ?? 0) ?></div>
            <div class="stat-label">Available</div>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('rented')" title="Currently rented out">
        <div class="stat-card-icon danger">
            <i data-lucide="key-round"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color:var(--danger)"><?= (int)($stats['rented'] ?? 0) ?></div>
            <div class="stat-label">Rented</div>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('reserved')" title="Reserved vehicles">
        <div class="stat-card-icon primary" style="background:var(--info-light);color:var(--info);">
            <i data-lucide="calendar-clock"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color:var(--info)"><?= (int)($stats['reserved'] ?? 0) ?></div>
            <div class="stat-label">Reserved</div>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('maintenance')" title="Vehicles in maintenance">
        <div class="stat-card-icon warning">
            <i data-lucide="wrench"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color:var(--warning)"><?= (int)($stats['maintenance'] ?? 0) ?></div>
            <div class="stat-label">Maintenance</div>
        </div>
    </div>

</div>

<!-- ── Category Tabs ──────────────────────────────────────────────── -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;padding-bottom:.25rem;">
    <?php
    $baseQ = array_filter(['status' => $status, 'search' => $search], fn($v) => $v !== null && $v !== '');
    $allActive = empty($category_id);
    ?>
    <a href="?<?= http_build_query($baseQ) ?>"
       id="tab-all"
       style="display:inline-flex;align-items:center;gap:.4rem;padding:.375rem .875rem;border-radius:var(--radius-full);font-size:.8125rem;font-weight:600;text-decoration:none;transition:all .15s;
              <?= $allActive
                  ? 'background:var(--accent);color:#fff;border:1.5px solid var(--accent);'
                  : 'background:var(--bg-surface);color:var(--text-secondary);border:1.5px solid var(--border-color);' ?>">
        <i data-lucide="layout-grid" style="width:13px;height:13px;"></i> All
        <span style="background:<?= $allActive ? 'rgba(255,255,255,.25)' : 'var(--bg-muted)' ?>;color:<?= $allActive ? '#fff' : 'var(--text-muted)' ?>;padding:1px 6px;border-radius:99px;font-size:.7rem;">
            <?= (int)($stats['total'] ?? 0) ?>
        </span>
    </a>
    <?php foreach ($categories as $cat):
        $catActive = $category_id === $cat['category_code'];
        $catQ = array_merge($baseQ, ['category' => $cat['category_code']]);
    ?>
    <a href="?<?= http_build_query($catQ) ?>"
       id="tab-<?= htmlspecialchars($cat['category_code']) ?>"
       style="display:inline-flex;align-items:center;gap:.4rem;padding:.375rem .875rem;border-radius:var(--radius-full);font-size:.8125rem;font-weight:600;text-decoration:none;transition:all .15s;
              <?= $catActive
                  ? 'background:var(--accent);color:#fff;border:1.5px solid var(--accent);'
                  : 'background:var(--bg-surface);color:var(--text-secondary);border:1.5px solid var(--border-color);' ?>">
        <?= htmlspecialchars($cat['category_name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Filter Bar ─────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.5rem;">
    <div style="padding:.875rem 1.25rem;">
        <form method="GET" id="filterForm" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <?php if (!empty($category_id)): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($category_id) ?>">
            <?php endif; ?>

            <!-- Search -->
            <div style="position:relative;flex:1;min-width:200px;">
                <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
                <input type="text" name="search" id="vehicleSearch"
                       class="form-control" style="padding-left:34px;"
                       placeholder="Plate, brand, model, ID…"
                       value="<?= htmlspecialchars($search ?? '') ?>">
            </div>

            <!-- Status -->
            <select name="status" id="statusSelect" class="form-control" style="min-width:155px;max-width:195px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($VEHICLE_STATUS_LABELS as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <div style="display:flex;gap:.5rem;flex-shrink:0;">
                <button type="submit" class="btn btn-primary btn-sm" id="applyFilterBtn">
                    <i data-lucide="search" style="width:13px;height:13px;"></i> Search
                </button>
                <?php if ($search || $status): ?>
                    <a href="?<?= $category_id ? 'category='.urlencode($category_id) : '' ?>" class="btn btn-secondary btn-sm" id="clearFilterBtn">
                        <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Clear
                    </a>
                <?php endif; ?>
            </div>

            <span style="margin-left:auto;font-size:.8125rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0;">
                <?= number_format($totalItems) ?> vehicle<?= $totalItems !== 1 ? 's' : '' ?>
            </span>
        </form>
    </div>
</div>

<!-- ── Vehicle Card Grid ──────────────────────────────────────────── -->
<?php if (empty($vehicles)): ?>
    <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:4rem 2rem;text-align:center;color:var(--text-muted);">
        <i data-lucide="car" style="width:48px;height:48px;display:block;margin:0 auto 1rem;opacity:.3;"></i>
        <p style="font-size:1.0625rem;font-weight:600;margin-bottom:.25rem;">No vehicles found</p>
        <p style="font-size:.875rem;">Try adjusting your filters or search term.</p>
        <?php if ($authUser->hasPermission('vehicles.create')): ?>
        <a href="vehicle-add.php" class="btn btn-primary" style="margin-top:1.5rem;">
            <i data-lucide="plus" style="width:15px;height:15px;"></i> Register First Vehicle
        </a>
        <?php endif; ?>
    </div>
<?php else: ?>

<div id="fleetGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:1.25rem;margin-bottom:2rem;">
<?php foreach ($vehicles as $v):
    $statusColor = $VEHICLE_STATUS_COLORS[$v['current_status']] ?? 'secondary';
    $statusLabel = $VEHICLE_STATUS_LABELS[$v['current_status']] ?? $v['current_status'];
    $compStatus  = $v['compliance_status'] ?? 'valid';
    $compBadge   = match($compStatus) { 'breached' => 'danger', 'expiring' => 'warning', default => 'success' };
    $compLabel   = match($compStatus) { 'breached' => 'Breached', 'expiring' => 'Expiring', default => 'Compliant' };
    $hasPhoto    = !empty($v['primary_photo_path']);
    $photoUrl    = $hasPhoto ? BASE_URL . ltrim($v['primary_photo_path'], '/') : null;
?>
<div class="fleet-card" id="vehicle-<?= htmlspecialchars($v['vehicle_id']) ?>"
     style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);box-shadow:var(--shadow-xs);overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s;">

    <!-- Photo / Placeholder -->
    <div style="position:relative;height:148px;overflow:hidden;background:linear-gradient(135deg,var(--primary) 0%,#1e3a5f 100%);">
        <?php if ($hasPhoto): ?>
            <img src="<?= htmlspecialchars($photoUrl) ?>"
                 alt="<?= htmlspecialchars($v['brand'].' '.$v['model']) ?>"
                 style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
            <!-- Elegant placeholder -->
            <div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;opacity:.55;">
                <i data-lucide="car" style="width:44px;height:44px;color:#fff;"></i>
                <span style="font-size:.75rem;font-weight:600;color:#fff;letter-spacing:.08em;text-transform:uppercase;"><?= htmlspecialchars($v['category_name'] ?? '') ?></span>
            </div>
        <?php endif; ?>

        <!-- Status badge floating top-right -->
        <span class="badge badge-<?= $statusColor ?>"
              style="position:absolute;top:.625rem;right:.625rem;font-size:.65rem;box-shadow:0 2px 6px rgba(0,0,0,.2);">
            <?= $statusLabel ?>
        </span>

        <!-- Category chip top-left -->
        <span style="position:absolute;top:.625rem;left:.625rem;background:rgba(0,0,0,.45);color:#fff;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:99px;letter-spacing:.05em;text-transform:uppercase;backdrop-filter:blur(4px);">
            <?= htmlspecialchars($v['category_name'] ?? 'Fleet') ?>
        </span>
    </div>

    <!-- Card Body -->
    <div style="padding:1rem 1rem .75rem;flex:1;">
        <!-- Brand, Model, Year -->
        <div style="margin-bottom:.625rem;">
            <div style="font-weight:700;font-size:1rem;color:var(--text-main);line-height:1.3;">
                <?= htmlspecialchars($v['brand'].' '.$v['model']) ?>
                <?php if (!empty($v['variant'])): ?>
                    <span style="font-weight:400;font-size:.8125rem;color:var(--text-muted);"> · <?= htmlspecialchars($v['variant']) ?></span>
                <?php endif; ?>
            </div>
            <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px;">
                <?= htmlspecialchars($v['year_model'] ?? '') ?>
                <?php if (!empty($v['color'])): ?> &bull; <?= htmlspecialchars($v['color']) ?><?php endif; ?>
                <?php if (!empty($v['transmission'])): ?> &bull; <?= ucfirst($v['transmission']) ?><?php endif; ?>
            </div>
        </div>

        <!-- Plate number -->
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.75rem;">
            <span style="font-family:monospace;font-weight:700;font-size:.875rem;background:var(--bg-muted);border:1px solid var(--border-color);padding:3px 10px;border-radius:var(--radius-sm);letter-spacing:.08em;">
                <?= htmlspecialchars($v['plate_number']) ?>
            </span>
            <span style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($v['vehicle_id']) ?></span>
        </div>

        <!-- Info row: daily rate + compliance -->
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
            <div style="font-size:.8125rem;color:var(--text-secondary);">
                <i data-lucide="philippine-peso" style="width:12px;height:12px;vertical-align:-1px;"></i>
                <strong><?= number_format((float)($v['daily_rental_rate'] ?? 0), 0) ?></strong>
                <span style="color:var(--text-muted)">/day</span>
            </div>
            <a href="../compliance/index.php?search=<?= urlencode($v['plate_number']) ?>"
               class="badge badge-<?= $compBadge ?>" style="text-decoration:none;font-size:.65rem;">
                <i data-lucide="<?= $compStatus === 'valid' ? 'shield-check' : 'shield-alert' ?>"
                   style="width:10px;height:10px;margin-right:2px;"></i>
                <?= $compLabel ?>
            </a>
        </div>

        <?php if (!empty($v['total_rentals'])): ?>
        <div style="margin-top:.5rem;font-size:.75rem;color:var(--text-muted);">
            <i data-lucide="history" style="width:11px;height:11px;vertical-align:-1px;"></i>
            <?= (int)$v['total_rentals'] ?> rental<?= $v['total_rentals'] != 1 ? 's' : '' ?> total
        </div>
        <?php endif; ?>
    </div>

    <!-- Card Footer Actions -->
    <div style="padding:.625rem 1rem;border-top:1px solid var(--border-color);background:var(--bg-muted);display:flex;gap:.375rem;justify-content:flex-end;">
        <a href="vehicle-details.php?id=<?= urlencode($v['vehicle_id']) ?>"
           class="btn btn-sm btn-secondary" title="View Details"
           id="view-<?= htmlspecialchars($v['vehicle_id']) ?>">
            <i data-lucide="eye" style="width:13px;height:13px;"></i> Details
        </a>
        <a href="../documents/index.php?entity_type=vehicle&entity_id=<?= urlencode($v['vehicle_id']) ?>"
           class="btn btn-sm btn-ghost" title="Documents"
           id="docs-<?= htmlspecialchars($v['vehicle_id']) ?>">
            <i data-lucide="file-text" style="width:13px;height:13px;"></i>
        </a>
        <?php if ($authUser->hasPermission('vehicles.update')): ?>
        <a href="vehicle-edit.php?id=<?= urlencode($v['vehicle_id']) ?>"
           class="btn btn-sm btn-ghost" title="Edit Vehicle"
           id="edit-<?= htmlspecialchars($v['vehicle_id']) ?>">
            <i data-lucide="pencil" style="width:13px;height:13px;"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ── Pagination ──────────────────────────────────────────────────── -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-bottom:2rem;" id="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&<?= http_build_query(array_merge($filters, ['category' => $category_id])) ?>"
           class="btn btn-sm btn-secondary" id="prevPageBtn">
            <i data-lucide="chevron-left" style="width:14px;height:14px;"></i>
        </a>
    <?php endif; ?>
    <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
        <a href="?page=<?= $i ?>&<?= http_build_query(array_merge($filters, ['category' => $category_id])) ?>"
           class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"
           id="page-<?= $i ?>-btn"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&<?= http_build_query(array_merge($filters, ['category' => $category_id])) ?>"
           class="btn btn-sm btn-secondary" id="nextPageBtn">
            <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
        </a>
    <?php endif; ?>
    <span style="display:flex;align-items:center;padding:0 .5rem;font-size:.8125rem;color:var(--text-muted);">
        Page <?= $page ?> of <?= $totalPages ?>
    </span>
</div>
<?php endif; ?>

<?php endif; // end fleet grid ?>

<!-- ── Decommissioned Slide-in Panel ──────────────────────────────── -->
<div id="decom-panel" style="position:fixed;top:0;right:0;width:520px;max-width:100vw;height:100vh;background:var(--bg-surface);box-shadow:-4px 0 32px rgba(0,0,0,.15);z-index:10000;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);background:var(--bg-muted);">
        <h2 style="margin:0;font-size:1.05rem;font-weight:700;display:flex;align-items:center;gap:.5rem;">
            <i data-lucide="archive" style="width:18px;height:18px;color:var(--danger);"></i>
            Decommissioned Vehicles
            <?php if (!empty($decommissioned)): ?>
                <span style="background:var(--danger);color:#fff;font-size:.7rem;padding:2px 7px;border-radius:99px;"><?= count($decommissioned) ?></span>
            <?php endif; ?>
        </h2>
        <button onclick="document.getElementById('decom-panel').classList.remove('open')"
                style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-muted);border-radius:6px;" title="Close">
            <i data-lucide="x" style="width:20px;height:20px;"></i>
        </button>
    </div>
    <div style="flex:1;overflow-y:auto;padding:1.25rem 1.5rem;">
        <?php if (empty($decommissioned)): ?>
            <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                <i data-lucide="check-circle" style="width:40px;height:40px;display:block;margin:0 auto .75rem;opacity:.3;"></i>
                <p style="font-weight:700;margin-bottom:.25rem;">All vehicles active</p>
                <p style="font-size:.875rem;">No decommissioned fleet assets.</p>
            </div>
        <?php else: ?>
            <?php foreach ($decommissioned as $idx => $dv): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.875rem 1rem;margin-bottom:.75rem;border:1px solid var(--border-color);border-radius:var(--radius-md);background:var(--bg-body);">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:.9375rem;margin-bottom:2px;">
                        <?= htmlspecialchars($dv['brand'].' '.$dv['model']) ?>
                    </div>
                    <div style="font-size:.8rem;color:var(--text-muted);">
                        <code><?= htmlspecialchars($dv['vehicle_id']) ?></code>
                        &bull; <?= htmlspecialchars($dv['plate_number']) ?>
                        &bull; <?= htmlspecialchars($dv['category_name']) ?>
                    </div>
                    <div style="font-size:.75rem;color:var(--danger);margin-top:4px;display:flex;align-items:center;gap:.25rem;">
                        <i data-lucide="calendar-x" style="width:12px;height:12px;"></i>
                        Decommissioned <?= date('M d, Y', strtotime($dv['decommissioned_at'])) ?>
                    </div>
                </div>
                <form id="recommission-form-<?= $idx ?>" method="POST" style="margin:0;flex-shrink:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="recommission_id" value="<?= htmlspecialchars($dv['vehicle_id']) ?>">
                    <button type="button" class="btn btn-primary btn-sm"
                            onclick="confirmRecommission(<?= $idx ?>, '<?= htmlspecialchars($dv['brand'].' '.$dv['model'], ENT_QUOTES) ?>', '<?= htmlspecialchars($dv['vehicle_id'], ENT_QUOTES) ?>')">
                        <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Recommission
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<div id="decom-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;opacity:0;pointer-events:none;transition:opacity .3s;"
     onclick="document.getElementById('decom-panel').classList.remove('open')"></div>

<style>
/* Fleet card hover micro-animation */
.fleet-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-3px);
}
/* Category tab hover */
a[id^="tab-"]:hover {
    border-color: var(--accent) !important;
    color: var(--accent) !important;
    text-decoration: none !important;
}
/* Decom panel open */
#decom-panel.open { transform: translateX(0) !important; }
#decom-panel.open ~ #decom-overlay { opacity: 1 !important; pointer-events: auto !important; }

@media (max-width:640px) {
    #fleetGrid { grid-template-columns: 1fr !important; }
}
</style>

<script>
lucide.createIcons();

function setStatusFilter(value) {
    const sel = document.getElementById('statusSelect');
    if (sel) { sel.value = value; document.getElementById('filterForm').submit(); }
}

function confirmRecommission(formIdx, vehicleName, vehicleId) {
    if (typeof openGcrModal === 'function') {
        openGcrModal(
            'Confirm Recommission',
            'Are you sure you want to recommission <strong>' + vehicleName + '</strong> (' + vehicleId + ') and return it to the active fleet?',
            function () { document.getElementById('recommission-form-' + formIdx).submit(); },
            { variant: 'primary', confirmLabel: 'Yes, Recommission', icon: 'rotate-ccw' }
        );
    } else {
        if (confirm('Recommission ' + vehicleName + ' (' + vehicleId + ') and return it to the active fleet?')) {
            document.getElementById('recommission-form-' + formIdx).submit();
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>