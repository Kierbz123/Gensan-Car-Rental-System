<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$pageTitle = "Regulatory Compliance";
require_once '../../includes/header.php';

$authUser->requirePermission('compliance.view');

try {
    $compObj = new ComplianceRecord();
    $stats = $compObj->getStats();

    $filters = [];
    if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
    if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
    if (!empty($_GET['type'])) $filters['type'] = $_GET['type'];
    if (!empty($_GET['sort_by'])) $filters['sort_by'] = $_GET['sort_by'];
    if (!empty($_GET['sort_order'])) $filters['sort_order'] = $_GET['sort_order'];

    $page = max(1, intval($_GET['page'] ?? 1));
    $result = $compObj->getAll($filters, $page, 25);
    $items = $result['data'] ?? [];
    $totalPages = $result['total_pages'];

    $totalRecords = max(1, $stats['total_records']);
    $systemicScore = round(($stats['total_active'] / $totalRecords) * 100);

    // Fetch Recent History (Last 15 records) for Sidebar tracking search context
    $historyFilters = [];
    if (!empty($_GET['search'])) $historyFilters['search'] = $_GET['search'];
    $history = $compObj->getRecentHistory($historyFilters);

} catch (Exception $e) {
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = "Failed to load compliance data: " . $e->getMessage();
    }
    $stats = ['total_active' => 0, 'expired' => 0, 'expiring_soon' => 0];
    $items = [];
    $history = [];
    $systemicScore = 100;
    $totalPages = 1;
}

$currentSortBy = $filters['sort_by'] ?? '';
$currentSortOrder = $filters['sort_order'] ?? '';

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

function buildStatUrl($statusValue) {
    $params = $_GET;
    if (!empty($params['status']) && $params['status'] === $statusValue) {
        unset($params['status']);
    } else {
        $params['status'] = $statusValue;
    }
    unset($params['page']);
    return '?' . http_build_query($params);
}

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
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
.stat-card.active-expired {
    border-color: var(--danger) !important;
    box-shadow: 0 0 0 1px var(--danger) !important;
}
.stat-card.active-critical {
    border-color: var(--warning) !important;
    box-shadow: 0 0 0 1px var(--warning) !important;
}
.stat-card.active-valid {
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
</style>

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
    <a href="<?= buildStatUrl('expired') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['status']) && $_GET['status'] === 'expired') ? 'active-expired' : '' ?>">
            <div class="stat-card-icon danger"><i data-lucide="shield-alert" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $stats['expired'] ?? 0 ?></div>
            <div class="stat-label">Breached Instruments</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('critical') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['status']) && $_GET['status'] === 'critical') ? 'active-critical' : '' ?>">
            <div class="stat-card-icon warning"><i data-lucide="timer" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $stats['expiring_soon'] ?? 0 ?></div>
            <div class="stat-label">30-Day Critical</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('valid') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['status']) && $_GET['status'] === 'valid') ? 'active-valid' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="file-check" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $stats['total_active'] ?? 0 ?></div>
            <div class="stat-label">Valid Instruments</div>
        </div>
    </a>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon success"><i data-lucide="award" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $systemicScore ?>%</div>
        <div class="stat-label">Systemic Score</div>
    </div>
</div>

<div class="card mt-8">
    <div class="card-header" style="border-bottom:none; padding-bottom:0;">
        <h2 class="card-title" style="margin:0; font-size:1.125rem; display:flex; align-items:center; gap:0.5rem;">
            <i data-lucide="alert-triangle" style="width:18px;height:18px;color:var(--warning);"></i>
            Compliance Monitoring
        </h2>
    </div>

    <div style="padding:.875rem 1.25rem; border-bottom:1px solid var(--border-color);">
        <form method="GET" id="filterForm" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;width:100%;">
            
            <!-- Search -->
            <div style="position:relative;flex:1;min-width:200px;">
                <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
                <input type="text" name="search" id="searchInput" class="form-control" style="padding-left:34px;width:100%;" placeholder="Search plate, model, or ref..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>

            <select name="type" class="form-control" style="width:auto;flex-shrink:0;" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="lto_registration" <?= (isset($_GET['type']) && $_GET['type'] === 'lto_registration') ? 'selected' : '' ?>>LTO Registration</option>
                <option value="insurance_comprehensive" <?= (isset($_GET['type']) && $_GET['type'] === 'insurance_comprehensive') ? 'selected' : '' ?>>Insurance</option>
                <option value="emission_test" <?= (isset($_GET['type']) && $_GET['type'] === 'emission_test') ? 'selected' : '' ?>>Emission Test</option>
                <option value="franchise_ltfrb" <?= (isset($_GET['type']) && $_GET['type'] === 'franchise_ltfrb') ? 'selected' : '' ?>>Franchise (LTFRB)</option>
                <option value="mayors_permit" <?= (isset($_GET['type']) && $_GET['type'] === 'mayors_permit') ? 'selected' : '' ?>>Mayor's Permit</option>
            </select>

            <?php if (!empty($_GET['status'])): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($_GET['status']) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['sort_by'])): ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order']) ?>">
            <?php endif; ?>

            <div style="display:flex;gap:.5rem;flex-shrink:0;">
                <button type="submit" class="btn btn-primary btn-sm" id="applyFilterBtn">
                    <i data-lucide="search" style="width:13px;height:13px;"></i> Search
                </button>
                <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['type'])): ?>
                    <a href="index.php" class="btn btn-ghost btn-sm" title="Clear Filters"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></a>
                <?php endif; ?>
            </div>

            <!-- Result count -->
            <span style="margin-left:auto;font-size:.8125rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0;">
                <?= number_format($result['total'] ?? 0) ?> record<?= ($result['total'] ?? 0) !== 1 ? 's' : '' ?>
            </span>
        </form>
    </div>

    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>
                        <a href="<?= buildSortUrl('v.plate_number', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Vehicle Target <?= getSortIcon('v.plate_number', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('c.compliance_type', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Document Type <?= getSortIcon('c.compliance_type', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('c.document_number', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Reference # <?= getSortIcon('c.document_number', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('c.expiry_date', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Expiry Horizon <?= getSortIcon('c.expiry_date', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('c.status', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">State <?= getSortIcon('c.status', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th style="flex: 0 0 100px; justify-content: flex-end; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)):
                    foreach ($items as $item):
                        $hasExpiry  = !empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00';
                        $diff       = $hasExpiry ? (int) ceil((strtotime($item['expiry_date']) - time()) / (60 * 60 * 24)) : null;
                        $isBreached = $hasExpiry && $diff < 0;
                        $isCritical = $hasExpiry && !$isBreached && $diff <= 30;
                        $isPending  = !$hasExpiry;
                        $badgeCls   = $isPending ? 'badge-secondary' : ($isBreached ? 'badge-danger' : ($isCritical ? 'badge-warning' : 'badge-success'));
                        $badgeText  = $isPending ? 'PENDING' : ($isBreached ? 'BREACHED' : ($isCritical ? 'CRITICAL' : 'VALID'));
                        ?>
                        <tr <?= $isPending ? '' : ($isBreached ? 'style="background:var(--danger-light);"' : ($isCritical ? 'style="background:var(--warning-light);"' : '')) ?>>
                            <td>
                                <a href="../asset-tracking/vehicle-details.php?id=<?= $item['vehicle_id'] ?>" style="font-weight:600;display:flex;align-items:center;gap:4px;color:inherit;">
                                    <i data-lucide="link" style="width:12px;height:12px;opacity:0.6;"></i> <?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                                </a>
                                <div style="font-size:0.75rem;color:var(--text-muted);font-family:monospace;margin-left:16px;">
                                    <?= htmlspecialchars($item['plate_number']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?= strtoupper(str_replace('_', ' ', $item['compliance_type'])) ?></span>
                            </td>
                            <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($item['document_number'] ?? 'N/A') ?></td>
                            <td style="font-weight:600; color:<?= $isPending ? 'var(--text-muted)' : ($isBreached ? 'var(--danger)' : 'var(--text-main)') ?>;">
                                <?= $hasExpiry ? date('M d, Y', strtotime($item['expiry_date'])) : '<em style="opacity:.6;font-weight:400">No expiry set</em>' ?>
                                <div style="font-size:10px;">
                                    <?= $isPending ? 'Awaiting instrument upload' : ($isBreached ? abs($diff) . ' days lapsed' : $diff . ' days left') ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $badgeCls ?>"><?= $badgeText ?></span>
                            </td>
                            <td>
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="instrument-view.php?id=<?= $item['record_id'] ?>" class="btn btn-ghost btn-sm">Inspect</a>
                                    <?php if ($authUser->hasPermission('compliance.create')): ?>
                                        <a href="renew-upload.php?vehicle_id=<?= $item['vehicle_id'] ?>&type=<?= urlencode($item['compliance_type']) ?>"
                                            class="btn btn-<?= $isBreached ? 'danger' : 'warning' ?> btn-sm">
                                            Renew
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i data-lucide="shield-check" class="empty-state-icon"></i>
                                <h3>No Critical Exposures</h3>
                                <p style="margin-bottom:1rem;">Your instruments are perfectly optimized. No compliance tasks match your criteria.</p>
                                <a href="index.php" class="btn btn-secondary">Refresh Dashboard</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="padding:1rem 1.5rem;border-top:1px solid var(--border-color);display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>&<?= http_build_query(array_merge($_GET, ['page' => null])) ?>"
                    class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?>
    <div id="compliance-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($successMsg) ?></span>
        <button onclick="document.getElementById('compliance-toast').remove()"
            style="background:none;border:none;cursor:pointer;color:#fff;padding:0;margin:0;display:flex;align-items:center;opacity:0.8;"
            aria-label="Dismiss">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    </div>
    <style>
        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(60px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }
    </style>
    <script>
        setTimeout(function () {
            var t = document.getElementById('compliance-toast');
            if (t) {
                t.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                setTimeout(function () { if (t) t.remove(); }, 400);
            }
        }, 3500);
    </script>
<?php endif; ?>

<div id="compliance-history-panel" 
     style="position:fixed;top:0;right:0;width:560px;max-width:100vw;height:100vh;background:var(--bg-surface,#fff);box-shadow:-4px 0 24px rgba(0,0,0,.15);z-index:10000;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,.0,.2,1);display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);">
        <h2 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;font-weight:800;">
            <i data-lucide="history" style="width:20px;height:20px;color:var(--primary);"></i>
            Compliance Event Log
            <?php if(!empty($_GET['search'])): ?>
                <span class="badge badge-secondary" style="font-size:0.7rem; margin-left:10px;">Filtered: <?= htmlspecialchars($_GET['search']) ?></span>
            <?php endif; ?>
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
                <p>No recent compliance event logs matching your query.</p>
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
                            Expiry: <?php
                                $hExpD = $h['expiry_date'] ?? '';
                                echo (!empty($hExpD) && $hExpD !== '0000-00-00')
                                    ? date('M d, Y', strtotime($hExpD))
                                    : '<em>Pending</em>';
                            ?>
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