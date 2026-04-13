<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Auth guard MUST execute before any HTML output
$authUser->requirePermission('maintenance.view');

$db = Database::getInstance();

$pageTitle = "Maintenance Hub";
require_once '../../includes/header.php';

// Pre-initialize so references outside try block are always defined
$filters = [];
$stats   = ['total_active' => 0, 'overdue' => 0, 'upcoming' => 0, 'in_service' => 0];
$schedules = [];
$history = [];
$integrityPercentage = 100;
$totalPages = 1;
$result = ['total' => 0];

try {
    $maintObj = new MaintenanceRecord();
    $stats = $maintObj->getStats();

    $filters = [];
    if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
    if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
    if (!empty($_GET['upcoming'])) $filters['upcoming'] = $_GET['upcoming'];
    if (!empty($_GET['sort_by'])) $filters['sort_by'] = $_GET['sort_by'];
    if (!empty($_GET['sort_order'])) $filters['sort_order'] = $_GET['sort_order'];

    $page = max(1, intval($_GET['page'] ?? 1));
    $result = $maintObj->getAllSchedules($filters, $page, 15);
    $schedules = $result['data'] ?? [];
    $totalPages = $result['total_pages'];

    // Get Integrity stat
    $totalFleet = max(1, (int) $db->fetchColumn("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL"));
    $inServiceCount = (int) ($stats['in_service'] ?? 0);
    // Clamp to [0, 100] to guard against data anomalies
    $integrityPercentage = max(0, min(100, round((($totalFleet - $inServiceCount) / $totalFleet) * 100)));

    // Fetch Recent History (Last 15 records) for Sidebar tracking search context
    $historyFilters = [];
    if (!empty($_GET['search'])) $historyFilters['search'] = $_GET['search'];
    $history = $maintObj->getRecentHistory($historyFilters);

} catch (Exception $e) {
    error_log('Maintenance index error: ' . $e->getMessage());
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = "Failed to load maintenance data: " . $e->getMessage();
    }
    // defaults already set before the try block
}

$currentSortBy = $filters['sort_by'] ?? 's.next_due_date';
$currentSortOrder = $filters['sort_order'] ?? 'ASC';

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

function buildStatUrl($key, $value) {
    $params = $_GET;
    // reset mutual exclusivity between status and upcoming constraints
    if ($key === 'status') {
        unset($params['upcoming']);
        if (!empty($params['status']) && $params['status'] === $value) {
            unset($params['status']);
        } else {
            $params['status'] = $value;
        }
    } else if ($key === 'upcoming') {
        unset($params['status']);
        if (!empty($params['upcoming'])) {
            unset($params['upcoming']);
        } else {
            $params['upcoming'] = 1;
        }
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
.stat-card.active {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 1px var(--primary-color) !important;
}
.stat-card.active-overdue {
    border-color: var(--danger) !important;
    box-shadow: 0 0 0 1px var(--danger) !important;
}
.stat-card.active-upcoming {
    border-color: var(--warning) !important;
    box-shadow: 0 0 0 1px var(--warning) !important;
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
        <h1>Maintenance Hub</h1>
        <p>Monitoring diagnostics, preventative scheduling, and workshop performance.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-secondary" style="margin-right: 8px;" 
                onclick="document.getElementById('maintenance-history-panel').classList.add('open')">
            <i data-lucide="history" style="width:16px;height:16px;"></i> History
        </button>
        <a href="schedule-add.php" class="btn btn-primary">
            <i data-lucide="calendar-plus" style="width:16px;height:16px;"></i> Schedule Service
        </a>
    </div>
</div>

<div class="stats-grid">
    <a href="<?= buildStatUrl('status', 'overdue') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['status']) && $_GET['status'] === 'overdue') ? 'active-overdue' : '' ?>">
            <div class="stat-card-icon danger"><i data-lucide="alert-octagon" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $stats['overdue'] ?? 0 ?></div>
            <div class="stat-label">Critical Overdue</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('upcoming', '1') ?>" class="stat-card-link">
        <div class="stat-card <?= !empty($_GET['upcoming']) ? 'active-upcoming' : '' ?>">
            <div class="stat-card-icon warning"><i data-lucide="clock" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $stats['upcoming'] ?? 0 ?></div>
            <div class="stat-label">Due Soon (7d)</div>
        </div>
    </a>
    <a href="<?= buildStatUrl('status', 'in_progress') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['status']) && $_GET['status'] === 'in_progress') ? 'active' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="wrench" style="width:20px;height:20px;"></i></div>
            <div class="stat-value"><?= $stats['in_service'] ?? 0 ?></div>
            <div class="stat-label">In Workshop</div>
        </div>
    </a>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon success"><i data-lucide="shield-check" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $integrityPercentage ?>%</div>
        <div class="stat-label">Fleet Integrity</div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="border-bottom:none; padding-bottom:0;">
        <h2 class="card-title">Service Queue</h2>
    </div>
    
    <div style="padding:.875rem 1.25rem; border-bottom:1px solid var(--border-color);">
        <form method="GET" id="filterForm" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;width:100%;">
            
            <!-- Search -->
            <div style="position:relative;flex:1;min-width:200px;">
                <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
                <input type="text" name="search" id="searchInput" class="form-control" style="padding-left:34px;width:100%;" placeholder="Search plates, models, or service types..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>

            <select name="status" class="form-control" style="width:auto;flex-shrink:0;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="scheduled" <?= (isset($_GET['status']) && $_GET['status'] === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                <option value="in_progress" <?= (isset($_GET['status']) && $_GET['status'] === 'in_progress') ? 'selected' : '' ?>>In Workshop</option>
                <option value="overdue" <?= (isset($_GET['status']) && $_GET['status'] === 'overdue') ? 'selected' : '' ?>>Overdue</option>
                <option value="active" <?= (isset($_GET['status']) && $_GET['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                <option value="paused" <?= (isset($_GET['status']) && $_GET['status'] === 'paused') ? 'selected' : '' ?>>Paused</option>
            </select>

            <?php if (!empty($_GET['upcoming'])): ?>
                <input type="hidden" name="upcoming" value="1">
            <?php endif; ?>
            <?php if (!empty($_GET['sort_by'])): ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order']) ?>">
            <?php endif; ?>

            <div style="display:flex;gap:.5rem;flex-shrink:0;">
                <button type="submit" class="btn btn-primary btn-sm" id="applyFilterBtn">
                    <i data-lucide="search" style="width:13px;height:13px;"></i> Search
                </button>
                <?php if (!empty($_GET['search']) || !empty($_GET['status']) || !empty($_GET['upcoming'])): ?>
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
                        <a href="<?= buildSortUrl('v.plate_number', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Vehicle <?= getSortIcon('v.plate_number', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('s.service_type', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Service Type <?= getSortIcon('s.service_type', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('s.next_due_date', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Due Date <?= getSortIcon('s.next_due_date', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('s.status', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Status <?= getSortIcon('s.status', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($schedules)):
                    foreach ($schedules as $s):
                        $overdue = strtotime($s['next_due_date']) < time() && $s['status'] !== 'completed';
                        $badgeCls = match (strtolower($s['status'])) {
                            'scheduled' => 'badge-info',
                            'in_progress' => 'badge-warning',
                            'overdue' => 'badge-danger',
                            'active' => 'badge-primary',
                            'paused' => 'badge-secondary',
                            default => 'badge-secondary'
                        };
                        ?>
                        <tr <?= $overdue && $s['status'] == 'overdue' ? 'style="background:var(--danger-light);"' : '' ?>>
                            <td>
                                <a href="../asset-tracking/vehicle-details.php?id=<?= $s['vehicle_id'] ?>" style="font-weight:600;display:flex;align-items:center;gap:4px;color:inherit;">
                                    <i data-lucide="link" style="width:12px;height:12px;opacity:0.6;"></i> <?= htmlspecialchars($s['brand'] . ' ' . $s['model']) ?>
                                </a>
                                <div style="font-size:0.75rem;color:var(--text-muted);font-family:monospace;margin-left:16px;">
                                    <?= htmlspecialchars($s['plate_number']) ?>
                                </div>
                            </td>
                            <td style="font-weight:600;"><?= ucfirst(str_replace('_', ' ', $s['service_type'])) ?></td>
                            <td>
                                <div style="color:<?= $overdue ? 'var(--danger)' : 'var(--text-main)' ?>; font-weight:<?= $overdue ? '700' : '400' ?>;">
                                    <?= date('M d, Y', strtotime($s['next_due_date'])) ?>
                                </div>
                                <?php if ($overdue): ?>
                                    <div style="color:var(--danger);font-size:10px;font-weight:700;">OVERDUE</div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badgeCls ?>"><?= strtoupper($s['status']) ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="service-view.php?id=<?= $s['schedule_id'] ?>" class="btn btn-ghost btn-sm">View</a>
                                    
                                    <?php if ($s['status'] === 'scheduled' || $s['status'] === 'overdue' || $s['status'] === 'active'): ?>
                                        <a href="service-start.php?id=<?= $s['schedule_id'] ?>"
                                            class="btn btn-primary btn-sm">Initiate</a>
                                    <?php elseif ($s['status'] === 'in_progress'): ?>
                                        <a href="service-view.php?id=<?= $s['schedule_id'] ?>" 
                                            class="btn btn-success btn-sm" title="Finalize Maintenance">
                                            <i data-lucide="check-circle" style="width:14px;height:14px;margin-right:4px;"></i> Complete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i data-lucide="shield-check" class="empty-state-icon"></i>
                                <h3>All Units Ready</h3>
                                <p style="margin-bottom:1rem;">Your fleet is operating normally without any scheduled queues matching your specific filters.</p>
                                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div style="padding:1rem 1.5rem;border-top:1px solid var(--border-color);display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
            <?php
                $paginationParams = $_GET;
                unset($paginationParams['page']);
                $baseQuery = http_build_query($paginationParams);
                for ($p = 1; $p <= $totalPages; $p++):
                    $sep = $baseQuery ? $baseQuery . '&' : '';
            ?>
                <a href="?<?= $sep ?>page=<?= $p ?>"
                    class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?>
    <div id="maintenance-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($successMsg) ?></span>
        <button onclick="document.getElementById('maintenance-toast').remove()"
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
            var t = document.getElementById('maintenance-toast');
            if (t) {
                t.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                setTimeout(function () { if (t) t.remove(); }, 400);
            }
        }, 3500);
    </script>
<?php endif; ?>

<!-- Maintenance History Sidebar -->
<div id="maintenance-history-panel" 
     style="position:fixed;top:0;right:0;width:560px;max-width:100vw;height:100vh;background:var(--bg-surface,#fff);box-shadow:-4px 0 24px rgba(0,0,0,.15);z-index:10000;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);">
        <h2 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;font-weight:800;">
            <i data-lucide="history" style="width:20px;height:20px;color:var(--primary);"></i>
            Transaction History
            <?php if(!empty($_GET['search'])): ?>
                <span class="badge badge-secondary" style="font-size:0.7rem; margin-left:10px;">Filtered: <?= htmlspecialchars($_GET['search']) ?></span>
            <?php endif; ?>
        </h2>
        <button onclick="document.getElementById('maintenance-history-panel').classList.remove('open')" 
                style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:color 0.2s;"
                onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
            <i data-lucide="x" style="width:20px;height:20px;"></i>
        </button>
    </div>
    <div style="flex:1;overflow-y:auto;padding:1.5rem;background:var(--bg-body, #f4f6f8);">
        <?php if (empty($history)): ?>
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
                <i data-lucide="info" style="width:32px;height:32px;margin-bottom:1rem;opacity:0.5;"></i>
                <p>No recent maintenance history found matching your query.</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $h): 
                $hStatusCls = match($h['status']) {
                    'completed' => 'badge-success',
                    'in_progress' => 'badge-warning',
                    default => 'badge-secondary'
                };
            ?>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:1.25rem;margin-bottom:1rem;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-surface,#fff);box-shadow:0 1px 3px rgba(0,0,0,.04);transition:transform 0.2s, box-shadow 0.2s;"
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.box_shadow='0 4px 12px rgba(0,0,0,0.08)';"
                     onmouseout="this.style.transform='none'; this.style.box_shadow='0 1px 3px rgba(0,0,0,0.04)';">
                    <div style="flex-grow:1;padding-right:1rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <div style="font-weight:800;color:var(--primary);font-family:monospace;font-size:0.9rem;">
                                <?= strtoupper(str_replace('_', ' ', $h['service_type'])) ?>
                            </div>
                            <span class="badge <?= $hStatusCls ?>" style="font-size:0.65rem;text-transform:uppercase;padding:2px 6px;"><?= $h['status'] ?></span>
                        </div>
                        <div style="font-size:.95rem;font-weight:700;margin-bottom:6px;color:var(--text-main);">
                            <?= htmlspecialchars($h['brand'] . ' ' . $h['model']) ?>
                        </div>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                            <i data-lucide="car" style="width:12px;height:12px;"></i>
                            <span style="font-family:monospace;background:var(--secondary-50);padding:2px 6px;border-radius:4px;color:var(--secondary-700);font-weight:700;">
                                <?= htmlspecialchars($h['plate_number']) ?>
                            </span>
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:center;gap:12px;font-weight:600;">
                            <span style="display:flex;align-items:center;gap:4px;">
                                <i data-lucide="calendar" style="width:12px;height:12px;"></i>
                                <?= date('M d, Y', strtotime($h['service_date'])) ?>
                            </span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.5rem;min-width:80px;text-align:right;">
                        <a href="service-view.php?id=<?= $h['id'] ?>" 
                           class="btn btn-ghost btn-sm" style="font-weight:700;font-size:0.75rem;">Inspect</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <div style="text-align:center;padding:1rem 0;">
                <a href="history.php" class="btn btn-ghost btn-sm" style="text-decoration:underline;color:var(--primary);">
                    Explore Comprehensive Records
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    #maintenance-history-panel.open { transform: translateX(0) !important; }
</style>

<script>
    lucide.createIcons();
</script>

<?php require_once '../../includes/footer.php'; ?>