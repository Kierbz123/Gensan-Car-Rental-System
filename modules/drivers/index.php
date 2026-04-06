<?php
// modules/drivers/index.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$pageTitle = 'Chauffeur Management';
require_once '../../includes/header.php';

$authUser->requirePermission('drivers.view');

$db       = Database::getInstance();
$driver   = new Driver();

$page   = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$licType = $_GET['license_type'] ?? '';

$filters = [];
if ($search)  $filters['search']       = $search;
if ($status)  $filters['status']       = $status;
if ($licType) $filters['license_type'] = $licType;

$result  = $driver->getAll($filters, $page, ITEMS_PER_PAGE);
$drivers = $result['data'];
$total   = $result['total'];

// Driver statistics
$stats = $db->fetchOne(
    "SELECT
        COUNT(*)                                                   AS total,
        SUM(status = 'available')                                  AS available,
        SUM(status = 'on_duty')                                    AS on_duty,
        SUM(status = 'off_duty')                                   AS off_duty,
        SUM(status = 'suspended')                                  AS suspended
     FROM drivers WHERE deleted_at IS NULL"
);

// Expiring licences within 30 days
$expiring = $driver->getExpiringLicenses(30);

$STATUS_LABELS = [
    'available'  => ['label' => 'Available',  'class' => 'success'],
    'on_duty'    => ['label' => 'On Duty',    'class' => 'primary'],
    'off_duty'   => ['label' => 'Off Duty',   'class' => 'secondary'],
    'suspended'  => ['label' => 'Suspended',  'class' => 'danger'],
];

// Avatar initials colour palette (deterministic by name)
$AVATAR_COLORS = [
    '#2563eb','#7c3aed','#059669','#d97706','#dc2626',
    '#0284c7','#9333ea','#16a34a','#b45309','#be123c',
];
function driverAvatarColor(string $name, array $palette): string {
    $hash = 0;
    foreach (str_split($name) as $ch) $hash = ($hash * 31 + ord($ch)) & 0xFFF;
    return $palette[$hash % count($palette)];
}
?>

<!-- ── Page Header ───────────────────────────────────────────────── -->
<div class="page-header">
    <div class="page-title">
        <h1>
            <i data-lucide="user-check"
               style="width:28px;height:28px;vertical-align:-5px;margin-right:10px;color:var(--accent)"></i>
            Chauffeur Management
        </h1>
        <p>Manage licensed drivers and their scheduling assignments.</p>
    </div>
    <div class="page-actions">
        <?php if ($authUser->hasPermission('drivers.create')): ?>
            <a href="driver-add.php" class="btn btn-primary" id="addDriverBtn">
                <i data-lucide="plus" style="width:16px;height:16px;"></i> Add Driver
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Stats Row ─────────────────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:2rem;">

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('')" title="All Drivers">
        <div class="stat-card-icon primary">
            <i data-lucide="users"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)($stats['total']    ?? 0) ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('available')" title="Available drivers">
        <div class="stat-card-icon success">
            <i data-lucide="circle-check-big"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color:var(--success)"><?= (int)($stats['available'] ?? 0) ?></div>
            <div class="stat-label">Available</div>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('on_duty')" title="Currently on duty">
        <div class="stat-card-icon primary">
            <i data-lucide="steering-wheel"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color:var(--accent)"><?= (int)($stats['on_duty']    ?? 0) ?></div>
            <div class="stat-label">On Duty</div>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('off_duty')" title="Off duty drivers">
        <div class="stat-card-icon warning">
            <i data-lucide="moon"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color:var(--warning)"><?= (int)($stats['off_duty']   ?? 0) ?></div>
            <div class="stat-label">Off Duty</div>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="setStatusFilter('suspended')" title="Suspended drivers">
        <div class="stat-card-icon danger">
            <i data-lucide="shield-ban"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="color:var(--danger)"><?= (int)($stats['suspended']  ?? 0) ?></div>
            <div class="stat-label">Suspended</div>
        </div>
    </div>

</div>

<!-- ── License Expiry Alert ──────────────────────────────────────── -->
<?php if (!empty($expiring)): ?>
<div id="expiryAlert" style="margin-bottom:1.5rem;padding:1rem 1.25rem 1rem 1rem;background:linear-gradient(135deg,#fffbeb 0%,#fef9c3 100%);border:1px solid var(--warning);border-left:4px solid var(--warning);border-radius:var(--radius-md);display:flex;align-items:flex-start;gap:.875rem;">
    <i data-lucide="alert-triangle" style="width:20px;height:20px;color:var(--warning);flex-shrink:0;margin-top:1px;"></i>
    <div style="flex:1;">
        <div style="font-weight:700;font-size:.875rem;color:#92400e;margin-bottom:.25rem;">
            <?= count($expiring) ?> License<?= count($expiring) !== 1 ? 's' : '' ?> Expiring Within 30 Days
        </div>
        <div style="font-size:.8125rem;color:#b45309;line-height:1.5;">
            <?= implode(' &bull; ', array_map(
                fn($d) => htmlspecialchars("{$d['first_name']} {$d['last_name']}") .
                          ' <span style="font-weight:700">' . (int)$d['days_left'] . 'd</span>',
                $expiring
            )) ?>
        </div>
    </div>
    <button onclick="document.getElementById('expiryAlert').remove()" style="background:none;border:none;cursor:pointer;padding:2px;color:var(--warning);opacity:.7;flex-shrink:0;" title="Dismiss">
        <i data-lucide="x" style="width:16px;height:16px;"></i>
    </button>
</div>
<?php endif; ?>

<!-- ── Filter / Toolbar ──────────────────────────────────────────── -->
<div class="card" style="margin-bottom:1.5rem;">
    <div style="padding:.875rem 1.25rem;">
        <form method="GET" id="filterForm" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">

            <!-- Search -->
            <div style="position:relative;flex:1;min-width:200px;">
                <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
                <input type="text" name="search" id="searchInput"
                       class="form-control"
                       style="padding-left:34px;"
                       placeholder="Name, code, license…"
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <!-- Status filter -->
            <select name="status" id="statusSelect" class="form-control" style="min-width:145px;max-width:180px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($STATUS_LABELS as $val => $meta): ?>
                    <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>>
                        <?= $meta['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- License Type -->
            <select name="license_type" class="form-control" style="min-width:155px;max-width:195px;" onchange="this.form.submit()">
                <option value="">All License Types</option>
                <option value="professional"     <?= $licType === 'professional'     ? 'selected' : '' ?>>Professional</option>
                <option value="non_professional" <?= $licType === 'non_professional' ? 'selected' : '' ?>>Non-Professional</option>
            </select>

            <div style="display:flex;gap:.5rem;flex-shrink:0;">
                <button type="submit" class="btn btn-primary btn-sm" id="applyFilterBtn">
                    <i data-lucide="search" style="width:13px;height:13px;"></i> Search
                </button>
                <?php if ($search || $status || $licType): ?>
                    <a href="index.php" class="btn btn-secondary btn-sm" title="Clear all filters" id="clearFiltersBtn">
                        <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Clear
                    </a>
                <?php endif; ?>
            </div>

            <!-- Result count -->
            <span style="margin-left:auto;font-size:.8125rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0;">
                <?= number_format($total) ?> driver<?= $total !== 1 ? 's' : '' ?>
            </span>
        </form>
    </div>
</div>

<!-- ── Driver Card Grid ──────────────────────────────────────────── -->
<?php if (empty($drivers)): ?>
    <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:4rem 2rem;text-align:center;color:var(--text-muted);">
        <i data-lucide="user-x" style="width:48px;height:48px;display:block;margin:0 auto 1rem;opacity:.3;"></i>
        <p style="font-size:1.0625rem;font-weight:600;margin-bottom:.25rem;">No drivers found</p>
        <p style="font-size:.875rem;">Try adjusting your search or filters.</p>
        <?php if ($authUser->hasPermission('drivers.create')): ?>
            <a href="driver-add.php" class="btn btn-primary" style="margin-top:1.5rem;">
                <i data-lucide="plus" style="width:15px;height:15px;"></i> Add First Driver
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1.25rem;margin-bottom:2rem;" id="driverGrid">
    <?php foreach ($drivers as $d):
        $si      = $STATUS_LABELS[$d['status']] ?? ['label' => $d['status'], 'class' => 'secondary'];
        $exp     = (int) $d['days_until_expiry'];
        $expBadge = $exp <= 0  ? ['class'=>'danger',  'text'=>'Expired']
                  : ($exp <= 30 ? ['class'=>'warning', 'text'=> $exp.'d left']
                                : ['class'=>'success', 'text'=> date('M Y', strtotime($d['license_expiry']))]);
        $fullName    = htmlspecialchars($d['first_name'].' '.$d['last_name']);
        $initials    = strtoupper(substr($d['first_name'],0,1).substr($d['last_name'],0,1));
        $avatarColor = driverAvatarColor($d['first_name'].$d['last_name'], $AVATAR_COLORS);
        $hasPhoto    = !empty($d['profile_photo_path']);
        $photoUrl    = $hasPhoto ? (BASE_URL . ltrim($d['profile_photo_path'], '/')) : null;
    ?>
    <div class="driver-card" id="driver-<?= $d['driver_id'] ?>"
         style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);box-shadow:var(--shadow-xs);overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s;">

        <!-- Card Top: coloured accent bar -->
        <div style="height:4px;background:<?= $avatarColor ?>;"></div>

        <!-- Card Body -->
        <div style="padding:1.25rem 1.25rem .875rem;flex:1;">

            <!-- Avatar + Name + Status -->
            <div style="display:flex;align-items:flex-start;gap:1rem;margin-bottom:1rem;">

                <!-- Avatar -->
                <div style="flex-shrink:0;">
                    <?php if ($hasPhoto): ?>
                        <img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= $fullName ?>"
                             style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);">
                    <?php else: ?>
                        <div style="width:52px;height:52px;border-radius:50%;background:<?= $avatarColor ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;letter-spacing:-.02em;flex-shrink:0;">
                            <?= $initials ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Name & Code -->
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:1rem;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                         title="<?= $fullName ?>">
                        <?= $fullName ?>
                    </div>
                    <div style="font-size:.75rem;color:var(--text-muted);font-family:monospace;margin-top:1px;">
                        <?= htmlspecialchars($d['employee_code']) ?>
                    </div>
                </div>

                <!-- Status Badge -->
                <span class="badge badge-<?= $si['class'] ?>" style="flex-shrink:0;margin-top:2px;">
                    <?= $si['label'] ?>
                </span>
            </div>

            <!-- Info rows -->
            <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.8125rem;">

                <!-- Phone -->
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--text-secondary);">
                    <i data-lucide="phone" style="width:13px;height:13px;color:var(--text-muted);flex-shrink:0;"></i>
                    <a href="tel:<?= htmlspecialchars($d['phone']) ?>" style="color:inherit;text-decoration:none;" class="info-link">
                        <?= htmlspecialchars($d['phone']) ?>
                    </a>
                </div>

                <?php if (!empty($d['email'])): ?>
                <!-- Email -->
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--text-secondary);">
                    <i data-lucide="mail" style="width:13px;height:13px;color:var(--text-muted);flex-shrink:0;"></i>
                    <a href="mailto:<?= htmlspecialchars($d['email']) ?>" style="color:inherit;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" class="info-link">
                        <?= htmlspecialchars($d['email']) ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- License -->
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--text-secondary);">
                    <i data-lucide="id-card" style="width:13px;height:13px;color:var(--text-muted);flex-shrink:0;"></i>
                    <span><?= htmlspecialchars($d['license_number']) ?></span>
                    <span style="margin-left:auto;padding:1px 6px;border-radius:4px;font-size:.7rem;font-weight:600;background:var(--bg-muted);color:var(--text-muted);">
                        <?= $d['license_type'] === 'professional' ? 'Pro' : 'Non-Pro' ?>
                    </span>
                </div>

                <!-- License expiry -->
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--text-secondary);">
                    <i data-lucide="calendar-clock" style="width:13px;height:13px;color:var(--text-muted);flex-shrink:0;"></i>
                    <span>Expires <?= date('M d, Y', strtotime($d['license_expiry'])) ?></span>
                    <span class="badge badge-<?= $expBadge['class'] ?>" style="margin-left:auto;font-size:.65rem;padding:1px 6px;">
                        <?= $expBadge['text'] ?>
                    </span>
                </div>

            </div>
        </div>

        <!-- Card Footer -->
        <div style="padding:.75rem 1.25rem;border-top:1px solid var(--border-color);background:var(--bg-muted);display:flex;gap:.5rem;justify-content:flex-end;">
            <a href="driver-view.php?id=<?= $d['driver_id'] ?>"
               class="btn btn-sm btn-secondary" title="View Profile" id="view-driver-<?= $d['driver_id'] ?>">
                <i data-lucide="eye" style="width:13px;height:13px;"></i> View
            </a>
            <?php if ($authUser->hasPermission('drivers.update')): ?>
            <a href="driver-edit.php?id=<?= $d['driver_id'] ?>"
               class="btn btn-sm btn-ghost" title="Edit Driver" id="edit-driver-<?= $d['driver_id'] ?>">
                <i data-lucide="pencil" style="width:13px;height:13px;"></i> Edit
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Pagination ───────────────────────────────────────────────── -->
<?php if ($result['total_pages'] > 1): ?>
<div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-bottom:2rem;" id="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&license_type=<?= urlencode($licType) ?>"
           class="btn btn-sm btn-secondary" id="prevPageBtn">
            <i data-lucide="chevron-left" style="width:14px;height:14px;"></i>
        </a>
    <?php endif; ?>

    <?php for ($p = max(1, $page-2); $p <= min($result['total_pages'], $page+2); $p++): ?>
        <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&license_type=<?= urlencode($licType) ?>"
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"
           id="page-<?= $p ?>-btn">
            <?= $p ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $result['total_pages']): ?>
        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&license_type=<?= urlencode($licType) ?>"
           class="btn btn-sm btn-secondary" id="nextPageBtn">
            <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
        </a>
    <?php endif; ?>

    <span style="display:flex;align-items:center;padding:0 .5rem;font-size:.8125rem;color:var(--text-muted);">
        Page <?= $page ?> of <?= $result['total_pages'] ?>
    </span>
</div>
<?php endif; ?>

<?php endif; // end driver list ?>

<style>
/* ── Driver Card hover micro-animation ─── */
.driver-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-3px);
}
.info-link:hover {
    color: var(--accent) !important;
    text-decoration: underline !important;
}
/* Responsive: 1 column on narrow viewports */
@media (max-width:640px) {
    #driverGrid { grid-template-columns: 1fr !important; }
}
</style>

<script>
lucide.createIcons();

/** Click on a stat card → filter the table by status */
function setStatusFilter(value) {
    const sel = document.getElementById('statusSelect');
    if (sel) { sel.value = value; document.getElementById('filterForm').submit(); }
}
</script>

<?php require_once '../../includes/footer.php'; ?>