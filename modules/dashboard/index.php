<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$pageTitle = "Dashboard";
require_once '../../includes/header.php';

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$userData   = $authUser->getData();
$userRole   = $userData['role'];
$userId     = $userData['user_id'];
$firstName  = $userData['first_name'];

// ── Server-side: baseline KPIs (fast scalar queries) ──────────────
try {
    $db = Database::getInstance();

    $totalVehicles     = (int) $db->fetchColumn("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL");
    $activeRentals     = (int) $db->fetchColumn("SELECT COUNT(*) FROM rental_agreements WHERE status = 'active'");
    $pendingMaintenance= (int) $db->fetchColumn("SELECT COUNT(*) FROM maintenance_schedules WHERE status != 'completed'");
    $revenueThisMonth  = (float) ($db->fetchColumn(
        "SELECT COALESCE(SUM(total_amount),0) FROM rental_agreements
         WHERE status NOT IN ('cancelled','draft','no_show')
           AND MONTH(created_at) = MONTH(CURDATE())
           AND YEAR(created_at)  = YEAR(CURDATE())"
    ) ?: 0.00);
    $revenuePrevMonth  = (float) ($db->fetchColumn(
        "SELECT COALESCE(SUM(total_amount),0) FROM rental_agreements
         WHERE status NOT IN ('cancelled','draft','no_show')
           AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
           AND YEAR(created_at)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
    ) ?: 0.00);
    $momPct = $revenuePrevMonth > 0
        ? round((($revenueThisMonth - $revenuePrevMonth) / $revenuePrevMonth) * 100, 1)
        : null;

    $lowStockCount = 0;
    try {
        $lowStockCount = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM parts_inventory WHERE reorder_level > 0 AND quantity_on_hand <= reorder_level"
        );
    } catch (Exception $invEx) { /* table may not exist */ }

    // Overdue returns banner
    $overdueRentals = $db->fetchAll(
        "SELECT ra.agreement_id, ra.agreement_number, ra.rental_end_date,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                c.phone_primary AS customer_phone,
                v.plate_number, v.brand, v.model,
                TIMESTAMPDIFF(HOUR, ra.rental_end_date, NOW()) AS hours_overdue
         FROM rental_agreements ra
         JOIN customers c ON ra.customer_id = c.customer_id
         JOIN vehicles  v ON ra.vehicle_id  = v.vehicle_id
         WHERE ra.status = 'active'
           AND ra.rental_end_date < DATE_SUB(NOW(), INTERVAL 2 HOUR)
         ORDER BY ra.rental_end_date ASC"
    );

    // Recent audit activity
    $recentLogs = AuditLogger::getAuditTrail([], 1, 8)['data'] ?? [];

    // Fleet status counts for pie (server-side backup)
    $fleetStatus = $db->fetchAll(
        "SELECT current_status, COUNT(*) AS cnt FROM vehicles WHERE deleted_at IS NULL GROUP BY current_status"
    );

    // Today's itinerary (used for Gantt fallback)
    $today = date('Y-m-d');
    $ganttRows = $db->fetchAll(
        "SELECT ra.agreement_id, ra.agreement_number,
                ra.rental_start_date, ra.rental_end_date, ra.status,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                v.plate_number, v.brand, v.model
         FROM rental_agreements ra
         JOIN customers c ON ra.customer_id = c.customer_id
         JOIN vehicles  v ON ra.vehicle_id  = v.vehicle_id
         WHERE ra.status IN ('active','reserved','confirmed')
           AND (DATE(ra.rental_start_date) = ?
             OR DATE(ra.rental_end_date)   = ?
             OR (DATE(ra.rental_start_date) < ? AND DATE(ra.rental_end_date) > ?))
         ORDER BY ra.rental_start_date ASC",
        [$today, $today, $today, $today]
    );

} catch (Exception $e) {
    $_SESSION['error_message'] = "Dashboard Error: " . $e->getMessage();
    $overdueRentals = $recentLogs = $fleetStatus = $ganttRows = [];
    $totalVehicles = $activeRentals = $pendingMaintenance = $lowStockCount = 0;
    $revenueThisMonth = $revenuePrevMonth = 0.00;
    $momPct = null;
}

// Role-specific greeting context
$roleLabel = match ($userRole) {
    'system_admin'        => 'System Administrator',
    'manager'             => 'Operations Manager',
    'mechanic'            => 'Fleet Mechanic',
    'maintenance_staff'   => 'Maintenance Staff',
    'procurement_officer' => 'Procurement Officer',
    'rental_agent'        => 'Rental Agent',
    default               => ucwords(str_replace('_', ' ', $userRole)),
};

// Base URL for AJAX calls (strip trailing slash for JS)
$ajaxBase = rtrim(BASE_URL, '/') . '/modules/dashboard/dashboard-ajax.php';
?>

<!-- ═══ DASHBOARD-SPECIFIC STYLES ════════════════════════════════════ -->
<style>
/* ── KPI Stats Grid ─────────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.75rem;
}
.kpi-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    box-shadow: var(--shadow-xs);
    position: relative;
    overflow: hidden;
    transition: box-shadow var(--transition-base), transform var(--transition-base);
    cursor: default;
}
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--kpi-color, var(--accent)), transparent);
}
.kpi-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.kpi-card .kpi-icon {
    width: 40px; height: 40px; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    background: color-mix(in srgb, var(--kpi-color, var(--accent)) 12%, transparent);
    color: var(--kpi-color, var(--accent));
    margin-bottom: .75rem;
}
.kpi-card .kpi-icon svg { width: 18px; height: 18px; }
.kpi-value { font-size: 1.75rem; font-weight: 800; line-height: 1; letter-spacing: -.03em; color: var(--text-main); }
.kpi-label { font-size: .6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-top: .25rem; }
.kpi-mom {
    display: inline-flex; align-items: center; gap: .25rem;
    font-size: .75rem; font-weight: 700; margin-top: .5rem;
    padding: .15rem .5rem; border-radius: var(--radius-full);
}
.kpi-mom.up   { background: var(--success-light); color: var(--success); }
.kpi-mom.down { background: var(--danger-light);  color: var(--danger); }
.kpi-mom.flat { background: var(--bg-muted);       color: var(--text-muted); }

/* ── Dashboard Grid (2-col) ─────────────────────────────────────── */
.dash-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}
.dash-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
.dash-grid.wide   { grid-template-columns: 2fr 1fr; }
.dash-grid.wide-l { grid-template-columns: 1fr 2fr; }
@media (max-width: 1100px) {
    .dash-grid, .dash-grid.cols-3, .dash-grid.wide, .dash-grid.wide-l {
        grid-template-columns: 1fr;
    }
}

/* ── Widget Card ─────────────────────────────────────────────────── */
.widget {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xs);
    overflow: hidden;
    position: relative;
    transition: box-shadow var(--transition-base);
}
.widget:hover { box-shadow: var(--shadow-md); }
.widget-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: .875rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-muted);
}
.widget-title {
    font-size: .875rem; font-weight: 700; color: var(--text-main);
    display: flex; align-items: center; gap: .5rem;
}
.widget-title svg { width: 16px; height: 16px; color: var(--accent); }
.widget-body { padding: 1.25rem; }
.widget-link {
    font-size: .75rem; font-weight: 600; color: var(--accent);
    text-decoration: none; white-space: nowrap;
}
.widget-link:hover { text-decoration: underline; }

/* Widget Drag Handle */
.widget-drag-handle {
    cursor: grab; color: var(--text-muted); width: 14px; height: 14px;
    opacity: 0; transition: opacity .15s;
}
.widget:hover .widget-drag-handle { opacity: 1; }
.widget.dragging { opacity: .55; border: 2px dashed var(--accent); }

/* ── Task Queue / Inbox ─────────────────────────────────────────── */
.task-list { list-style: none; display: flex; flex-direction: column; gap: .6rem; }
.task-item {
    display: flex; align-items: center; gap: .875rem;
    padding: .75rem 1rem; border-radius: var(--radius-md);
    text-decoration: none; color: var(--text-main);
    transition: background var(--transition-fast), border-color var(--transition-fast);
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
}
.task-item:hover { background: var(--bg-muted); border-color: var(--primary-600); text-decoration: none; }
.task-icon {
    width: 36px; height: 36px; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.task-icon svg { width: 16px; height: 16px; }
.task-icon.critical { background: var(--danger-light);  color: var(--danger); }
.task-icon.warning  { background: var(--warning-light); color: var(--warning); }
.task-icon.info     { background: var(--info-light);    color: var(--info); }
.task-icon.success  { background: var(--success-light); color: var(--success); }
.task-count {
    font-size: 1.125rem; font-weight: 800; color: var(--text-main);
    min-width: 2rem; text-align: right;
}
.task-label { font-size: .8125rem; color: var(--text-secondary); flex: 1; line-height: 1.3; }
.task-empty {
    text-align: center; padding: 2rem 1rem;
    color: var(--text-muted); font-size: .875rem;
}
.task-empty svg { width: 32px; height: 32px; margin: 0 auto .75rem; display: block; opacity: .4; }

/* ── Gantt / Timeline ───────────────────────────────────────────── */
.gantt-wrapper { overflow-x: auto; }
.gantt-grid {
    display: flex; flex-direction: column; gap: .5rem;
    min-width: 640px; padding-bottom: .5rem;
}
.gantt-time-axis {
    display: flex; margin-left: 160px; margin-bottom: .25rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: .25rem;
}
.gantt-hour {
    flex: 1; text-align: center; font-size: .6rem; font-weight: 600;
    color: var(--text-muted); letter-spacing: .04em;
}
.gantt-row { display: flex; align-items: center; gap: .5rem; min-height: 36px; }
.gantt-label {
    width: 155px; flex-shrink: 0;
    font-size: .75rem; font-weight: 600; color: var(--text-main);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    line-height: 1.2;
}
.gantt-label small { display: block; font-weight: 400; color: var(--text-muted); font-size: .65rem; }
.gantt-track {
    flex: 1; height: 28px; background: var(--bg-muted);
    border-radius: var(--radius-full); position: relative; overflow: hidden;
}
.gantt-bar {
    position: absolute; top: 4px; bottom: 4px;
    border-radius: var(--radius-full);
    display: flex; align-items: center; padding: 0 8px;
    font-size: .65rem; font-weight: 700; color: #fff;
    white-space: nowrap; overflow: hidden; min-width: 16px;
    transition: filter .2s;
    cursor: pointer;
}
.gantt-bar:hover { filter: brightness(1.1); }
.gantt-bar.active   { background: var(--accent); }
.gantt-bar.reserved { background: #7c3aed; }
.gantt-bar.confirmed{ background: var(--success); }
.gantt-bar.departing{ background: var(--warning); }
.gantt-bar.returning{ background: #0284c7; }
.gantt-now {
    position: absolute; top: 0; bottom: 0; width: 2px;
    background: var(--danger); z-index: 5; pointer-events: none;
}
.gantt-now::before {
    content: 'NOW';
    position: absolute; top: 50%; transform: translateY(-50%);
    background: var(--danger); color: #fff;
    font-size: .55rem; font-weight: 800; padding: 1px 3px;
    border-radius: 2px; letter-spacing: .04em; white-space: nowrap;
    left: 3px;
}
.gantt-empty {
    text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); font-size: .875rem;
}

/* ── Chart Canvas ───────────────────────────────────────────────── */
.chart-area { position: relative; height: 200px; width: 100%; }
.chart-area canvas { display: block; }

/* ── Fleet Doughnut ─────────────────────────────────────────────── */
.fleet-chart-wrap {
    position: relative; width: 160px; height: 160px;
    flex-shrink: 0; margin: 0 auto;
}
.fleet-chart-wrap canvas { width: 160px !important; height: 160px !important; display: block; }
.fleet-center-stat {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    text-align: center; pointer-events: none;
}
.fleet-center-stat .num  { font-size: 1.75rem; font-weight: 800; line-height: 1; color: var(--text-main); }
.fleet-center-stat .lbl  { font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-top: .1rem; }
.fleet-legend-list {
    list-style: none; display: flex; flex-direction: column; gap: .5rem;
    flex: 1; min-width: 0;
}
.fleet-legend-item { display: flex; flexwrap: wrap; flex-direction: column; gap: .2rem; }
.fleet-legend-meta { display: flex; align-items: center; gap: .4rem; }
.fleet-legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.fleet-legend-name { font-size: .75rem; font-weight: 600; color: var(--text-secondary); flex: 1; }
.fleet-legend-count { font-size: .75rem; font-weight: 800; color: var(--text-main); }
.fleet-legend-bar-track {
    height: 4px; background: var(--bg-muted); border-radius: var(--radius-full); overflow: hidden;
}
.fleet-legend-bar-fill {
    height: 100%; border-radius: var(--radius-full);
    transition: width .6s cubic-bezier(.4,0,.2,1);
}

/* ── Overdue Alert Banner ───────────────────────────────────────── */
.overdue-banner {
    background: var(--danger);
    border-radius: var(--radius-md);
    overflow: hidden;
    margin-bottom: 1.5rem;
    animation: pulseAlert 2s ease-in-out infinite;
}
@keyframes pulseAlert {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,.25); }
    50%       { box-shadow: 0 0 0 6px rgba(220,38,38,0); }
}
.overdue-banner-header {
    display: flex; align-items: center; gap: .75rem;
    padding: .75rem 1.25rem; font-weight: 700; color: #fff; font-size: .9rem;
}
.overdue-banner-header svg { width: 18px; height: 18px; flex-shrink: 0; }
.overdue-banner-body { background: var(--danger-light); }
.overdue-row {
    display: flex; align-items: center; gap: 1rem;
    padding: .75rem 1.25rem; border-bottom: 1px solid rgba(220,38,38,.12); flex-wrap: wrap;
}
.overdue-row:last-child { border-bottom: none; }

/* ── Page Header Greeting ───────────────────────────────────────── */
.dash-greeting { margin-bottom: 1.5rem; }
.dash-greeting h1 { font-size: 1.5rem; font-weight: 800; letter-spacing: -.03em; }
.dash-greeting p  { color: var(--text-secondary); font-size: .9375rem; margin-top: .25rem; }

/* ── Widget Collapse Toggle ─────────────────────────────────────── */
.widget-collapse-btn {
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); padding: 0; display: flex; align-items: center;
    transition: color .15s, transform .2s;
}
.widget-collapse-btn:hover { color: var(--text-main); }
.widget-collapse-btn.collapsed svg { transform: rotate(-90deg); }
.widget-collapsible { overflow: hidden; transition: max-height .3s ease; max-height: 2000px; }
.widget-collapsible.collapsed { max-height: 0; }

/* ── Audit Feed ─────────────────────────────────────────────────── */
.audit-row {
    display: flex; align-items: flex-start; gap: .75rem;
    padding: .625rem 0; border-bottom: 1px solid var(--border-color);
}
.audit-row:last-child { border-bottom: none; }
.audit-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--accent-light); border: 2px solid var(--accent);
    flex-shrink: 0; margin-top: .35rem;
}
.audit-meta { font-size: .75rem; color: var(--text-muted); margin-top: .2rem; }

/* ── Skeleton Loader ────────────────────────────────────────────── */
.skeleton {
    background: linear-gradient(90deg, var(--bg-muted) 25%, #e8ecf0 50%, var(--bg-muted) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: var(--radius-sm);
}
@keyframes shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.skeleton-text { height: .75rem; margin-bottom: .5rem; }
.skeleton-text:last-child { width: 60%; }

/* ── Toast ──────────────────────────────────────────────────────── */
@keyframes toastSlideIn {
    from { opacity: 0; transform: translateX(60px) scale(.96); }
    to   { opacity: 1; transform: translateX(0) scale(1); }
}
</style>

<!-- ═══ OVERDUE BANNER ════════════════════════════════════════════════ -->
<?php if (!empty($overdueRentals)): ?>
<div class="overdue-banner">
    <div class="overdue-banner-header">
        <i data-lucide="alert-octagon"></i>
        <?= count($overdueRentals) ?> Overdue Vehicle Return<?= count($overdueRentals) > 1 ? 's' : '' ?> — Immediate Attention Required
    </div>
    <div class="overdue-banner-body">
        <?php foreach ($overdueRentals as $ov): ?>
        <div class="overdue-row">
            <span class="badge badge-danger" style="flex-shrink:0;"><?= (int)$ov['hours_overdue'] ?>h overdue</span>
            <div style="flex:1;min-width:180px;">
                <strong><?= htmlspecialchars($ov['agreement_number']) ?></strong>
                — <?= htmlspecialchars($ov['brand'].' '.$ov['model'].' '.$ov['plate_number']) ?>
                <br><small style="color:var(--text-muted);">Customer: <?= htmlspecialchars($ov['customer_name']) ?></small>
            </div>
            <a href="tel:<?= htmlspecialchars($ov['customer_phone']) ?>" class="btn btn-sm btn-danger" style="flex-shrink:0;">
                <i data-lucide="phone" style="width:13px;height:13px;"></i> <?= htmlspecialchars($ov['customer_phone']) ?>
            </a>
            <a href="<?= BASE_URL ?>modules/rentals/view.php?id=<?= $ov['agreement_id'] ?>" class="btn btn-sm btn-secondary" style="flex-shrink:0;">View</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ═══ PAGE HEADER ═══════════════════════════════════════════════════ -->
<div class="page-header">
    <div class="dash-greeting">
        <h1>Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 18) ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars($firstName) ?>.</h1>
        <p><?= htmlspecialchars($roleLabel) ?> · <?= date('l, F j, Y') ?> · <?= date('g:i A') ?></p>
    </div>
    <div class="page-actions" style="align-items:center;gap:.75rem;">
        <?php require_once '../../includes/notifications.php'; ?>
        <a href="dashboard-snapshot.php" class="btn btn-secondary" style="gap:.4rem;" target="_blank">
            <i data-lucide="file-text" style="width:15px;height:15px;"></i> Snapshot
        </a>
        <?php if ($authUser->hasPermission('rentals.create')): ?>
        <a href="<?= BASE_URL ?>modules/rentals/reserve.php" class="btn btn-primary">
            <i data-lucide="plus" style="width:15px;height:15px;"></i> New Booking
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ KPI STATS ROW ════════════════════════════════════════════════ -->
<div class="kpi-grid" id="kpiGrid">
    <!-- Total Fleet -->
    <div class="kpi-card" style="--kpi-color:var(--accent);">
        <div class="kpi-icon"><i data-lucide="car"></i></div>
        <div class="kpi-value"><?= $totalVehicles ?></div>
        <div class="kpi-label">Total Fleet</div>
    </div>

    <!-- Active Rentals -->
    <div class="kpi-card" style="--kpi-color:var(--success);">
        <div class="kpi-icon"><i data-lucide="key"></i></div>
        <div class="kpi-value"><?= $activeRentals ?></div>
        <div class="kpi-label">Active Rentals</div>
    </div>

    <!-- Maintenance -->
    <div class="kpi-card" style="--kpi-color:var(--warning);">
        <div class="kpi-icon"><i data-lucide="wrench"></i></div>
        <div class="kpi-value"><?= $pendingMaintenance ?></div>
        <div class="kpi-label">Pending Maintenance</div>
    </div>

    <!-- Revenue MTD -->
    <div class="kpi-card" style="--kpi-color:var(--success);">
        <div class="kpi-icon"><i data-lucide="trending-up"></i></div>
        <div class="kpi-value" style="font-size:1.35rem;">₱<?= number_format($revenueThisMonth, 2) ?></div>
        <div class="kpi-label">Revenue (MTD)</div>
        <?php if ($momPct !== null): ?>
        <div class="kpi-mom <?= $momPct >= 0 ? 'up' : 'down' ?>">
            <i data-lucide="<?= $momPct >= 0 ? 'trending-up' : 'trending-down' ?>" style="width:11px;height:11px;"></i>
            <?= $momPct >= 0 ? '+' : '' ?><?= $momPct ?>% vs last month
        </div>
        <?php else: ?>
        <div class="kpi-mom flat">— No prior month data</div>
        <?php endif; ?>
    </div>

    <!-- Low Stock (conditional) -->
    <?php if ($lowStockCount > 0): ?>
    <div class="kpi-card" style="--kpi-color:var(--warning);cursor:pointer;"
         onclick="window.location='<?= BASE_URL ?>modules/inventory/index.php?low_stock=1'">
        <div class="kpi-icon"><i data-lucide="package"></i></div>
        <div class="kpi-value" style="color:var(--warning);"><?= $lowStockCount ?></div>
        <div class="kpi-label">Low Stock Items</div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ MAIN DASHBOARD GRID ══════════════════════════════════════════ -->

<!-- Row 1: Gantt (wide) + Task Queue -->
<div class="dash-grid wide" id="dashRow1" style="margin-bottom:1.25rem;">

    <!-- ── Today's Itinerary (Gantt) ──────────────────────────────── -->
    <div class="widget" id="wGantt" draggable="true">
        <div class="widget-header">
            <span class="widget-title">
                <i data-lucide="grip-vertical" class="widget-drag-handle"></i>
                <i data-lucide="calendar-clock"></i>
                Today's Itinerary
                <span style="font-size:.7rem;font-weight:600;padding:.15rem .5rem;background:var(--accent-50);color:var(--accent);border-radius:var(--radius-full);"><?= date('M j') ?></span>
            </span>
            <div style="display:flex;align-items:center;gap:.5rem;">
                <a href="<?= BASE_URL ?>modules/rentals/index.php" class="widget-link">View All</a>
                <button class="widget-collapse-btn" onclick="toggleWidget(this,'ganttBody')" title="Toggle">
                    <i data-lucide="chevron-down" style="width:15px;height:15px;"></i>
                </button>
            </div>
        </div>
        <div class="widget-collapsible" id="ganttBody">
            <div class="widget-body">
                <div class="gantt-wrapper" id="ganttContainer">
                    <?php if (!empty($ganttRows)): ?>
                    <div class="gantt-grid" id="ganttGrid">
                        <!-- Time axis -->
                        <div class="gantt-time-axis" id="ganttAxis"></div>
                        <!-- Rows injected by JS -->
                    </div>
                    <?php else: ?>
                    <div class="gantt-empty">
                        <i data-lucide="calendar-x" style="width:32px;height:32px;opacity:.35;display:block;margin:0 auto .75rem;"></i>
                        No active or scheduled rentals for today.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Task Queue / Personalised Inbox ───────────────────────── -->
    <div class="widget" id="wTasks" draggable="true">
        <div class="widget-header">
            <span class="widget-title">
                <i data-lucide="grip-vertical" class="widget-drag-handle"></i>
                <i data-lucide="inbox"></i>
                Your Action Queue
            </span>
            <button class="widget-collapse-btn" onclick="toggleWidget(this,'taskBody')" title="Toggle">
                <i data-lucide="chevron-down" style="width:15px;height:15px;"></i>
            </button>
        </div>
        <div class="widget-collapsible" id="taskBody">
            <div class="widget-body">
                <ul class="task-list" id="taskList">
                    <!-- Populated via AJAX -->
                    <li style="padding:.5rem 0;">
                        <div class="skeleton skeleton-text" style="width:80%;height:.85rem;"></div>
                        <div class="skeleton skeleton-text" style="width:60%;height:.85rem;"></div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>


<!-- Row 3: Recent Activity Feed (full width) -->
<div id="dashRow3" style="margin-bottom:1.25rem;">
    <div class="widget" id="wActivity" draggable="true">
        <div class="widget-header">
            <span class="widget-title">
                <i data-lucide="grip-vertical" class="widget-drag-handle"></i>
                <i data-lucide="activity"></i>
                Recent System Activity
            </span>
            <div style="display:flex;align-items:center;gap:.5rem;">
                <span style="font-size:.7rem;padding:.15rem .5rem;background:var(--accent-50);color:var(--accent);border-radius:var(--radius-full);font-weight:600;">Live Feed</span>
                <button class="widget-collapse-btn" onclick="toggleWidget(this,'activityBody')" title="Toggle">
                    <i data-lucide="chevron-down" style="width:15px;height:15px;"></i>
                </button>
            </div>
        </div>
        <div class="widget-collapsible" id="activityBody">
            <div class="widget-body" style="padding:1rem 1.25rem;">
                <?php if (!empty($recentLogs)): ?>
                <?php foreach ($recentLogs as $log): ?>
                <div class="audit-row">
                    <div class="audit-dot"></div>
                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                            <strong style="font-size:.8125rem;"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></strong>
                            <span class="badge badge-info"><?= htmlspecialchars($log['action'] ?? '') ?></span>
                            <span style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($log['module'] ?? '') ?></span>
                        </div>
                        <div class="audit-meta"><?= date('M j, Y · g:i A', strtotime($log['action_timestamp'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:.875rem;">No recent activity</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══ SUCCESS TOAST ════════════════════════════════════════════════ -->
<?php if ($successMsg): ?>
<div id="login-toast" style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:var(--success);color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn .35s cubic-bezier(.4,0,.2,1);">
    <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
    <span style="flex:1;"><?= htmlspecialchars($successMsg) ?></span>
    <button onclick="document.getElementById('login-toast').remove()" style="background:none;border:none;cursor:pointer;color:#fff;padding:0;display:flex;align-items:center;opacity:.8;">
        <i data-lucide="x" style="width:15px;height:15px;"></i>
    </button>
</div>
<script>
setTimeout(function(){
    var t = document.getElementById('login-toast');
    if(t){ t.style.transition='opacity .4s,transform .4s'; t.style.opacity='0'; t.style.transform='translateX(60px)'; setTimeout(function(){if(t)t.remove();},400); }
},3500);
</script>
<?php endif; ?>

<!-- ═══ CHART.JS + DASHBOARD LOGIC ══════════════════════════════════ -->
<script src="<?= ASSETS_URL ?>js/chart.js"></script>
<script>
(function () {
    'use strict';

    const AJAX_BASE = '<?= addslashes($ajaxBase) ?>';
    const BASE_URL  = '<?= addslashes(BASE_URL) ?>';

    /* ── Lucide re-init helper ───────────────────────────────────── */
    function refreshIcons(el) {
        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: el ? [el] : undefined });
    }

    /* ── Widget toggle collapse ──────────────────────────────────── */
    window.toggleWidget = function(btn, bodyId) {
        var body = document.getElementById(bodyId);
        if (!body) return;
        var isCollapsed = body.classList.toggle('collapsed');
        btn.classList.toggle('collapsed', isCollapsed);
        saveLayout();
    };

    /* ── Chart defaults ──────────────────────────────────────────── */
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
        Chart.defaults.font.size   = 12;
        Chart.defaults.color       = '#64748b';
    }

    /* ── AJAX helper ─────────────────────────────────────────────── */
    function fetchAction(action, params) {
        params = params || {};
        var qs = new URLSearchParams(Object.assign({ action: action }, params)).toString();
        return fetch(AJAX_BASE + '?' + qs).then(function(r){ return r.json(); });
    }

    /* ── 1. Revenue Line Chart ───────────────────────────────────── */
    var revenueChart = null;
    function initRevenueChart() {
        fetchAction('revenue_trend').then(function(d) {
            var ctx = document.getElementById('revenueChart');
            if (!ctx || typeof Chart === 'undefined') return;
            if (revenueChart) revenueChart.destroy();
            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: d.revenues,
                        borderColor: '#2563eb',
                        backgroundColor: function(ctx) {
                            var gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height);
                            gradient.addColorStop(0,   'rgba(37,99,235,.18)');
                            gradient.addColorStop(1,   'rgba(37,99,235,0)');
                            return gradient;
                        },
                        borderWidth: 2.5,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        tension: .4,
                        fill: 'origin',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,.92)',
                            titleColor: '#94a3b8',
                            bodyColor: '#fff',
                            padding: 10,
                            callbacks: {
                                label: function(c){ return ' ₱' + Number(c.raw).toLocaleString('en-PH', {minimumFractionDigits:2}); }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,.05)' },
                            ticks: {
                                callback: function(v){
                                    if (v >= 1000000) return '₱' + (v/1000000).toFixed(1) + 'M';
                                    if (v >= 1000)    return '₱' + (v/1000).toFixed(0) + 'k';
                                    return '₱' + v;
                                }
                            },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { color: '#64748b' }
                        }
                    }
                }
            });
        }).catch(function(e){ console.warn('Revenue chart error:', e); });
    }

    /* ── 2. Fleet Doughnut Chart ─────────────────────────────────── */
    var fleetPieChart = null;
    function initFleetPie() {
        fetchAction('fleet_pie').then(function(d) {
            var ctx = document.getElementById('fleetPieChart');
            if (!ctx || typeof Chart === 'undefined') return;
            if (fleetPieChart) fleetPieChart.destroy();

            var total = d.data.reduce(function(a, b){ return a + b; }, 0);

            fleetPieChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{
                        data: d.data,
                        backgroundColor: d.colors,
                        borderWidth: 3,
                        borderColor: '#fff',
                        hoverBorderWidth: 4,
                    }]
                },
                options: {
                    responsive: false,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    animation: { animateRotate: true, duration: 700 },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,.92)',
                            titleColor: '#94a3b8',
                            bodyColor: '#fff',
                            padding: 10,
                            callbacks: {
                                label: function(c){
                                    var pct = total > 0 ? Math.round((c.raw / total) * 100) : 0;
                                    return ' ' + c.label + ': ' + c.raw + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Update center total
            var totalEl = document.getElementById('fleetTotal');
            if (totalEl) totalEl.textContent = total;

            // Update legend bars with animated fill
            var legend = document.getElementById('fleetPieLegend');
            if (legend) {
                legend.innerHTML = d.labels.map(function(lbl, i) {
                    var pct = total > 0 ? Math.round((d.data[i] / total) * 100) : 0;
                    return '<li class="fleet-legend-item">'
                        + '<div class="fleet-legend-meta">'
                        + '<span class="fleet-legend-dot" style="background:' + d.colors[i] + ';"></span>'
                        + '<span class="fleet-legend-name">' + lbl + '</span>'
                        + '<span class="fleet-legend-count">' + d.data[i] + '</span>'
                        + '<span style="font-size:.7rem;color:var(--text-muted);margin-left:.2rem;">' + pct + '%</span>'
                        + '</div>'
                        + '<div class="fleet-legend-bar-track">'
                        + '<div class="fleet-legend-bar-fill" style="width:0%;background:' + d.colors[i] + ';"></div>'
                        + '</div>'
                        + '</li>';
                }).join('');
                // Animate bars in after paint
                requestAnimationFrame(function(){
                    requestAnimationFrame(function(){
                        legend.querySelectorAll('.fleet-legend-bar-fill').forEach(function(bar, i){
                            var pct = total > 0 ? Math.round((d.data[i] / total) * 100) : 0;
                            bar.style.width = pct + '%';
                        });
                    });
                });
            }
        }).catch(function(e){ console.warn('Fleet chart error:', e); });
    }

    /* ── 3. Task Queue ───────────────────────────────────────────── */
    function initTaskQueue() {
        fetchAction('task_queue').then(function(d) {
            var list = document.getElementById('taskList');
            if (!list) return;
            if (!d.tasks || d.tasks.length === 0) {
                list.innerHTML = '<li class="task-empty"><i data-lucide="check-circle-2" style="width:32px;height:32px;opacity:.35;display:block;margin:0 auto .75rem;"></i>All clear! No pending actions for your role.</li>';
                refreshIcons(list);
                return;
            }
            list.innerHTML = d.tasks.map(function(t) {
                return '<li>'
                    + '<a href="' + t.link + '" class="task-item">'
                    + '<div class="task-icon ' + t.priority + '"><i data-lucide="' + t.icon + '"></i></div>'
                    + '<span class="task-count">' + t.count + '</span>'
                    + '<span class="task-label">' + t.label + '</span>'
                    + '<i data-lucide="chevron-right" style="width:14px;height:14px;color:var(--text-muted);flex-shrink:0;"></i>'
                    + '</a></li>';
            }).join('');
            refreshIcons(list);
        }).catch(function(e){
            var list = document.getElementById('taskList');
            if (list) list.innerHTML = '<li class="task-empty">Unable to load tasks.</li>';
            console.warn('Task queue error:', e);
        });
    }

    /* ── 4. Gantt Chart ──────────────────────────────────────────── */
    var GANTT_DATA = <?= json_encode(array_values($ganttRows)) ?>;

    function buildGantt() {
        var grid = document.getElementById('ganttGrid');
        if (!grid || GANTT_DATA.length === 0) return;

        var now = new Date();
        var dayStart = new Date(now); dayStart.setHours(0,0,0,0);
        var dayEnd   = new Date(now); dayEnd.setHours(23,59,59,999);

        // Build time axis (every 3 hours)
        var axis = document.getElementById('ganttAxis');
        if (axis) {
            var axisHtml = '';
            for (var h = 0; h <= 24; h += 3) {
                var lbl = h === 0 ? '12 AM' : (h < 12 ? h + ' AM' : (h === 12 ? '12 PM' : (h-12) + ' PM'));
                axisHtml += '<span class="gantt-hour">' + lbl + '</span>';
            }
            axis.innerHTML = axisHtml;
        }

        // Build rows
        var rowsHtml = '';
        GANTT_DATA.forEach(function(r) {
            var start = new Date(r.rental_start_date);
            var end   = new Date(r.rental_end_date);

            // Clamp to today
            var clampedStart = start < dayStart ? dayStart : start;
            var clampedEnd   = end   > dayEnd   ? dayEnd   : end;

            var totalMs = dayEnd - dayStart;
            var leftPct  = ((clampedStart - dayStart) / totalMs) * 100;
            var widthPct = ((clampedEnd   - clampedStart) / totalMs) * 100;
            widthPct = Math.max(widthPct, 1);
            leftPct  = Math.max(0, Math.min(leftPct, 99));

            var isToday    = (start <= now && end >= now);
            var departing  = (start >= dayStart && start.toDateString() === now.toDateString() && start >= now);
            var returning  = (end.toDateString() === now.toDateString() && end <= dayEnd);
            var barClass   = departing ? 'departing' : (returning ? 'returning' : r.status);

            var vehicle    = (r.brand || '') + ' ' + (r.model || '') + ' · ' + (r.plate_number || '');
            var label      = r.customer_name || 'Unknown';

            rowsHtml += '<div class="gantt-row">'
                + '<div class="gantt-label">' + label + '<small>' + vehicle + '</small></div>'
                + '<div class="gantt-track" title="' + r.agreement_number + '">';

            // NOW line
            if (now >= dayStart && now <= dayEnd) {
                var nowPct = ((now - dayStart) / totalMs) * 100;
                rowsHtml += '<div class="gantt-now" style="left:' + nowPct.toFixed(2) + '%;"></div>';
            }

            rowsHtml += '<div class="gantt-bar ' + barClass + '"'
                + ' style="left:' + leftPct.toFixed(2) + '%;width:' + widthPct.toFixed(2) + '%;"'
                + ' title="' + r.agreement_number + ' · ' + label + '"'
                + ' onclick="window.location=\'' + BASE_URL + 'modules/rentals/view.php?id=' + r.agreement_id + '\'">'
                + (widthPct > 8 ? r.agreement_number : '')
                + '</div>'
                + '</div>'  // .gantt-track
                + '</div>'; // .gantt-row
        });

        grid.insertAdjacentHTML('beforeend', rowsHtml);
    }

    /* ── 5. Widget Drag-and-Drop Layout ──────────────────────────── */
    var dragSrc = null;

    function initDragDrop() {
        var widgets = document.querySelectorAll('[draggable="true"]');
        widgets.forEach(function(w) {
            w.addEventListener('dragstart', function(e) {
                dragSrc = w;
                w.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', w.id);
            });
            w.addEventListener('dragend', function() {
                w.classList.remove('dragging');
                dragSrc = null;
                saveLayout();
            });
            w.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            w.addEventListener('drop', function(e) {
                e.preventDefault();
                if (dragSrc && dragSrc !== w) {
                    var parent = w.parentNode;
                    var srcParent = dragSrc.parentNode;
                    if (parent === srcParent) {
                        var nextSibling = dragSrc.nextSibling;
                        parent.insertBefore(dragSrc, w);
                        if (nextSibling) {
                            parent.insertBefore(w, nextSibling);
                        } else {
                            parent.appendChild(w);
                        }
                    }
                }
            });
        });
    }

    /* ── 6. Layout Persistence ───────────────────────────────────── */
    function saveLayout() {
        var layout = {};
        document.querySelectorAll('.widget').forEach(function(w) {
            layout[w.id] = {
                collapsed: !!w.querySelector('.widget-collapsible.collapsed'),
            };
        });
        fetch(AJAX_BASE + '?action=save_layout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ layout: layout }),
        }).catch(function() {
            // silent fail — layout preference is non-critical
        });
    }

    function restoreLayout() {
        fetch(AJAX_BASE + '?action=load_layout').then(function(r){ return r.json(); }).then(function(d) {
            if (!d.layout) return;
            Object.keys(d.layout).forEach(function(wId) {
                var pref  = d.layout[wId];
                var w     = document.getElementById(wId);
                if (!w) return;
                if (pref.collapsed) {
                    var body = w.querySelector('.widget-collapsible');
                    var btn  = w.querySelector('.widget-collapse-btn');
                    if (body) body.classList.add('collapsed');
                    if (btn)  btn.classList.add('collapsed');
                }
            });
        }).catch(function() {});
    }

    /* ── Boot ────────────────────────────────────────────────────── */
    function bootDashboard() {
        // Gantt (uses server-side PHP data, no AJAX needed)
        buildGantt();
        refreshIcons();

        // AJAX-powered widgets
        initTaskQueue();
        initRevenueChart();
        initFleetPie();

        // Drag-and-drop
        initDragDrop();

        // Restore saved layout preferences
        restoreLayout();
    }

    // Boot: works whether DOMContentLoaded has already fired or not
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootDashboard);
    } else {
        bootDashboard();
    }

})();
</script>

<?php require_once '../../includes/footer.php'; ?>