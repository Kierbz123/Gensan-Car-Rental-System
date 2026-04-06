<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$pageTitle = "Regulatory Compliance";
require_once '../../includes/header.php';

$authUser->requirePermission('compliance.view');

$sort = $_GET['sort'] ?? 'urgency';
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$viewMode = $_GET['view_mode'] ?? 'critical';
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 25;
$offset = ($page - 1) * $perPage;

try {
    // 1. Stats Grid (Global Fleet Health)
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_records,
            COALESCE(SUM(CASE WHEN expiry_date > DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) as total_active,
            COALESCE(SUM(CASE WHEN expiry_date < CURRENT_DATE() THEN 1 ELSE 0 END), 0) as expired,
            COALESCE(SUM(CASE WHEN expiry_date >= CURRENT_DATE() AND expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) as expiring_soon
        FROM compliance_records c
        WHERE status != 'renewed' AND status != 'cancelled'
          AND record_id = (
              SELECT MAX(record_id)
              FROM compliance_records c2
              WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
          )
    ");

    $totalRecords = $stats['total_records'] ?: 1; // Prevent division by zero
    $systemicScore = round(($stats['total_active'] / $totalRecords) * 100);

    // 2. Critical Watchlist (Filtered results)
    $where = ["c.status NOT IN ('renewed', 'cancelled')"];
    $params = [];

    // Filter by View Mode
    if ($viewMode === 'critical') {
        $where[] = "c.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)";
    }

    // Filter by Type
    if (!empty($type)) {
        $where[] = "c.compliance_type = ?";
        $params[] = $type;
    }

    // Filter by Search (Plate/Brand/Model)
    if (!empty($search)) {
        $where[] = "(v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR c.document_number LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Subquery for the most recent record of each type per vehicle
    $where[] = "c.record_id = (
        SELECT MAX(record_id) 
        FROM compliance_records c2 
        WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
    )";

    $whereClause = implode(' AND ', $where);

    // Get Total Count for Pagination
    $totalFiltered = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM compliance_records c
        JOIN vehicles v ON c.vehicle_id = v.vehicle_id
        WHERE $whereClause
    ", $params);

    $totalPages = ceil($totalFiltered / $perPage);

    // Order Clause
    $orderClause = "ORDER BY CASE WHEN c.expiry_date < CURRENT_DATE() THEN 1 ELSE 2 END ASC, c.expiry_date ASC";
    if ($sort === 'expiry') {
        $orderClause = "ORDER BY c.expiry_date ASC";
    } elseif ($sort === 'vehicle') {
        $orderClause = "ORDER BY v.brand ASC, v.model ASC, c.expiry_date ASC";
    } elseif ($sort === 'type') {
        $orderClause = "ORDER BY c.compliance_type ASC, c.expiry_date ASC";
    }

    $items = $db->fetchAll("
        SELECT c.*, v.plate_number, v.brand, v.model
        FROM compliance_records c
        JOIN vehicles v ON c.vehicle_id = v.vehicle_id
        WHERE $whereClause
        $orderClause
        LIMIT " . (int) $perPage . " OFFSET " . (int) $offset, 
        $params
    );

    // 3. Compliance History (Event Drawer)
    $history = $db->fetchAll("
        SELECT c.*, v.plate_number, v.brand, v.model
        FROM compliance_records c
        JOIN vehicles v ON c.vehicle_id = v.vehicle_id
        ORDER BY c.created_at DESC
        LIMIT 15
    ");

} catch (Exception $e) {
    $stats = ['total_active' => 0, 'expired' => 0, 'expiring_soon' => 0];
    $items = [];
    $history = [];
    $systemicScore = 100;
    $totalFiltered = 0;
    $totalPages = 1;
}
?>

<div class="page-header">
    <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div style="width:48px;height:48px;background:var(--danger-light);border:1px solid var(--border-color);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--danger);">
                <i data-lucide="shield-check" style="width:24px;height:24px;"></i>
            </div>
            <div>
                <h1 style="margin:0;font-size:1.5rem;font-weight:800;letter-spacing:-0.02em;">Regulatory Compliance</h1>
                <p style="margin:0;color:var(--text-muted);font-size:0.875rem;font-weight:600;">
                    Franchise validity, insurance coverage, and statutory vehicle documentation
                </p>
            </div>
        </div>
        <div class="page-actions" style="margin:0;">
            <a href="../documents/index.php?category=registration,insurance,permit" class="btn btn-secondary">
                <i data-lucide="folder-open" style="width:16px;height:16px;"></i> Document Repository
            </a>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('compliance-history-panel').classList.add('open')">
                <i data-lucide="history" style="width:16px;height:16px;"></i> History
            </button>
            <?php if ($authUser->hasPermission('compliance.create')): ?>
                <a href="renew-upload.php" class="btn btn-primary">
                    <i data-lucide="upload-cloud" style="width:16px;height:16px;"></i> Archive Instrument
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon danger"><i data-lucide="shield-alert" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['expired'] ?? 0 ?></div>
        <div class="stat-label">Breached Instruments</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon warning"><i data-lucide="timer" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['expiring_soon'] ?? 0 ?></div>
        <div class="stat-label">30-Day Critical</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon primary"><i data-lucide="file-check" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['total_active'] ?? 0 ?></div>
        <div class="stat-label">Valid Instruments</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon success"><i data-lucide="award" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $systemicScore ?>%</div>
        <div class="stat-label">Systemic Score</div>
    </div>
</div>

<div class="card mt-8">
    <div class="card-header" style="flex-direction:column; align-items:stretch; gap:1.25rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
             <h2 class="card-title" style="margin:0; font-size:1.125rem; display:flex; align-items:center; gap:0.5rem;">
                <i data-lucide="alert-triangle" style="width:18px;height:18px;color:var(--warning);"></i>
                Compliance Monitoring
            </h2>
            <div style="display:flex; gap:0.5rem;">
                <a href="?view_mode=critical" class="btn <?= $viewMode === 'critical' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">Critical Watchlist</a>
                <a href="?view_mode=all" class="btn <?= $viewMode === 'all' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">All Entities</a>
            </div>
        </div>

        <div class="card-header-filters" style="width:100%;">
            <form method="GET" class="card-header-form" style="display:flex; gap:0.5rem; flex-wrap:wrap; width:100%; align-items:center;">
                <input type="hidden" name="view_mode" value="<?= htmlspecialchars($viewMode) ?>">
                
                <div style="flex:1; min-width:200px; position:relative;">
                    <i data-lucide="search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); width:14px; height:14px; color:var(--text-muted);"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="form-control" placeholder="Search plate, model, or ref..." style="padding-left:32px; width:100%;">
                </div>
                
                <select name="type" class="form-control" style="width:180px; font-weight:600;">
                    <option value="">All Types</option>
                    <option value="lto_registration" <?= $type === 'lto_registration' ? 'selected' : '' ?>>LTO Registration</option>
                    <option value="insurance_comprehensive" <?= $type === 'insurance_comprehensive' ? 'selected' : '' ?>>Insurance</option>
                    <option value="emission_test" <?= $type === 'emission_test' ? 'selected' : '' ?>>Emission Test</option>
                    <option value="franchise_ltfrb" <?= $type === 'franchise_ltfrb' ? 'selected' : '' ?>>Franchise (LTFRB)</option>
                    <option value="mayors_permit" <?= $type === 'mayors_permit' ? 'selected' : '' ?>>Mayor's Permit</option>
                </select>

                <select name="sort" class="form-control" style="width:180px; font-weight:600;">
                    <option value="urgency" <?= $sort === 'urgency' ? 'selected' : '' ?>>Sort: Urgency</option>
                    <option value="expiry" <?= $sort === 'expiry' ? 'selected' : '' ?>>Sort: Expiry</option>
                    <option value="type" <?= $sort === 'type' ? 'selected' : '' ?>>Sort: Type</option>
                    <option value="vehicle" <?= $sort === 'vehicle' ? 'selected' : '' ?>>Sort: Vehicle</option>
                </select>

                <div style="display:flex; gap:0.25rem;">
                    <button type="submit" class="btn btn-primary btn-sm" style="padding:0.5rem 1rem;">Filter</button>
                    <a href="?" class="btn btn-ghost btn-sm" title="Reset Filters"><i data-lucide="rotate-ccw" style="width:18px;height:18px;"></i></a>
                </div>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Vehicle Target</th>
                    <th>Instrument Type</th>
                    <th>Reference #</th>
                    <th>Expiry Horizon</th>
                    <th>State</th>
                    <th style="flex: 0 0 100px; justify-content: flex-end; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)):
                    foreach ($items as $item):
                        $diff = ceil((strtotime($item['expiry_date']) - time()) / (60 * 60 * 24));
                        $badgeCls = $diff < 0 ? 'badge-danger' : 'badge-warning';
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($item['plate_number']) ?>
                                </div>
                            </td>
                            <td>
                                <span
                                    class="badge badge-secondary"><?= strtoupper(str_replace('_', ' ', $item['compliance_type'])) ?></span>
                            </td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($item['document_number'] ?? 'N/A') ?></td>
                            <td style="font-weight:600; color:<?= $diff < 0 ? 'var(--danger)' : 'var(--text-main)' ?>;">
                                <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                <div style="font-size:10px;">
                                    <?= $diff < 0 ? abs($diff) . ' days lapsed' : $diff . ' days left' ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $badgeCls ?>"><?= $diff < 0 ? 'BREACHED' : 'PENDING' ?></span>
                            </td>
                            <td>
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="instrument-view.php?id=<?= $item['record_id'] ?>"
                                        class="btn btn-ghost btn-sm">Inspect</a>
                                    <?php if ($authUser->hasPermission('compliance.create')): ?>
                                        <a href="renew-upload.php?vehicle_id=<?= $item['vehicle_id'] ?>&type=<?= urlencode($item['compliance_type']) ?>"
                                            class="btn btn-<?= $diff < 0 ? 'danger' : 'warning' ?> btn-sm">
                                            Renew
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:0.5rem;">
                                <i data-lucide="shield-check" style="width:48px;height:48px;opacity:0.5;"></i>
                                <span style="font-weight:600;">No critical exposures detected.</span>
                                <span>All compliance instruments are valid.</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
<div id="compliance-history-panel" 
     style="position:fixed;top:0;right:0;width:560px;max-width:100vw;height:100vh;background:var(--bg-surface,#fff);box-shadow:-4px 0 24px rgba(0,0,0,.15);z-index:10000;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,.0,.2,1);display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);">
        <h2 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;font-weight:800;">
            <i data-lucide="history" style="width:20px;height:20px;color:var(--primary);"></i>
            Compliance Event Log
        </h2>
        <button onclick="document.getElementById('compliance-history-panel').classList.remove('open')" 
                style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:color 0.2s;"
                onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
            <i data-lucide="x" style="width:20px;height:20px;"></i>
        </button>
    </div>
    <div style="flex:1;overflow-y:auto;padding:1.5rem;background:var(--bg-body, #f4f6f8);">
        <?php if (empty($history)): ?>
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
                <i data-lucide="info" style="width:32px;height:32px;margin-bottom:1rem;opacity:0.5;"></i>
                <p>No recent compliance event logs recorded.</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $h): 
                $hStatusCls = match($h['status']) {
                    'active' => 'badge-success',
                    'renewed' => 'badge-primary',
                    'cancelled' => 'badge-danger',
                    default => 'badge-secondary'
                };
            ?>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:1.25rem;margin-bottom:1rem;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-surface,#fff);box-shadow:0 1px 3px rgba(0,0,0,.04);transition:transform 0.2s, box-shadow 0.2s;"
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.box_shadow='0 4px 12px rgba(0,0,0,0.08)';"
                     onmouseout="this.style.transform='none'; this.style.box_shadow='0 1px 3px rgba(0,0,0,0.04)';">
                    <div style="flex-grow:1;padding-right:1rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <div style="font-weight:800;color:var(--primary);font-family:monospace;font-size:0.9rem;">
                                <?= strtoupper(str_replace('_', ' ', $h['compliance_type'])) ?>
                            </div>
                            <span class="badge <?= $hStatusCls ?>" style="font-size:0.65rem;text-transform:uppercase;padding:2px 6px;"><?= $h['status'] ?></span>
                        </div>
                        <div style="font-size:.95rem;font-weight:700;margin-bottom:6px;color:var(--text-main);">
                            <?= htmlspecialchars($h['brand'] . ' ' . $h['model']) ?>
                        </div>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                            <i data-lucide="hash" style="width:12px;height:12px;"></i>
                            <span style="font-family:monospace;background:var(--secondary-50);padding:2px 6px;border-radius:4px;color:var(--secondary-700);font-weight:700;">
                                <?= htmlspecialchars($h['plate_number']) ?>
                            </span>
                            <span style="color:var(--text-muted);">&bull;</span>
                            <span style="font-weight:600;"><?= htmlspecialchars($h['document_number'] ?? 'REF-NONE') ?></span>
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:center;gap:4px;font-weight:600;">
                            <i data-lucide="clock" style="width:12px;height:12px;"></i>
                            Event: <?= date('M d, Y', strtotime($h['created_at'])) ?>
                            <span style="margin:0 4px;opacity:0.3;">|</span>
                            Expiry: <?= date('M d, Y', strtotime($h['expiry_date'])) ?>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.5rem;min-width:80px;text-align:right;">
                        <a href="instrument-view.php?id=<?= $h['record_id'] ?>" class="btn btn-ghost btn-sm" style="font-weight:700;font-size:0.75rem;">Inspect</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    #compliance-history-panel.open { transform: translateX(0) !important; }
</style>

<script>
    lucide.createIcons();
</script>

<?php require_once '../../includes/footer.php'; ?>