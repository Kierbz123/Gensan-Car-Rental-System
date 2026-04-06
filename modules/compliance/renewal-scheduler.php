<?php
/**
 * Renewal Scheduler
 * Path: modules/compliance/renewal-scheduler.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('compliance.view');
$db = Database::getInstance();

$daysAhead = (int) ($_GET['days'] ?? 60);
$typeFilter = $_GET['type'] ?? '';

$params = [$daysAhead];
$typeSQL = '';
if ($typeFilter) {
    $typeSQL = " AND cr.compliance_type = ?";
    $params[] = $typeFilter;
}

$records = $db->fetchAll(
    "SELECT cr.*, v.plate_number, v.brand, v.model,
            DATEDIFF(cr.expiry_date, CURDATE()) AS days_left
     FROM compliance_records cr
     JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
     WHERE cr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
       AND cr.renewal_status != 'renewed'
       $typeSQL
     ORDER BY cr.expiry_date ASC",
    $params
);

$types = ['lto_registration', 'insurance', 'emission_test', 'franchise', 'business_permit', 'drivers_license', 'other'];
$pageTitle = 'Renewal Scheduler';
require_once '../../includes/header.php';
?>
<div class="fade-in">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="heading">Renewal Scheduler</h1>
            <p class="text-secondary-500 font-medium">Upcoming compliance renewals within the next <?= $daysAhead ?>
                days.</p>
        </div>
        <a href="renew-upload.php" class="btn btn-primary gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Archive New
            Instrument</a>
    </div>

    <!-- Filters -->
    <div class="card mb-6 flex flex-wrap items-end gap-4">
        <form method="GET" class="flex flex-wrap gap-4 items-end flex-1">
            <div class="min-w-[140px]">
                <label class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Days
                    Ahead</label>
                <select name="days" class="form-input rounded-2xl py-2.5 bg-secondary-50 w-full font-bold text-sm"
                    onchange="this.form.submit()">
                    <?php foreach ([30, 60, 90, 180] as $d): ?>
                        <option value="<?= $d ?>" <?= $daysAhead === $d ? 'selected' : '' ?>><?= $d ?> days</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-[180px]">
                <label
                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Type</label>
                <select name="type" class="form-input rounded-2xl py-2.5 bg-secondary-50 w-full font-bold text-sm"
                    onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>>
                            <?= str_replace('_', ' ', ucwords(str_replace('_', ' ', $t))) ?></option><?php endforeach; ?>
                </select>
            </div>
        </form>
        <div class="flex gap-2 items-center">
            <span class="text-2xl font-black text-secondary-900"><?= count($records) ?></span>
            <span class="text-xs font-bold text-secondary-400 uppercase tracking-widest">items requiring
                attention</span>
        </div>
    </div>

    <?php if (empty($records)): ?>
        <div class="card flex flex-col items-center py-20 text-center">
            <div class="w-16 h-16 bg-success-50 rounded-2xl flex items-center justify-center mb-4"><i
                    data-lucide="shield-check" class="w-8 h-8 text-success-500"></i></div>
            <h2 class="font-black text-secondary-900 mb-2">All Clear</h2>
            <p class="text-secondary-400 text-sm">No renewals due in the next <?= $daysAhead ?> days.</p>
        </div>
    <?php else: ?>
        <div class="flex flex-col gap-4">
            <?php foreach ($records as $r):
                $daysLeft = (int) $r['days_left'];
                $isExpired = $daysLeft < 0;
                $color = $isExpired ? 'danger' : ($daysLeft <= 14 ? 'warning' : 'primary');
                ?>
                <div class="card flex items-center gap-5 border-l-4 border-<?= $color ?>-500 group">
                    <div class="text-center min-w-[60px]">
                        <div class="text-2xl font-black text-<?= $color ?>-600"><?= abs($daysLeft) ?></div>
                        <div class="text-[10px] font-bold text-secondary-400 uppercase tracking-widest">
                            <?= $isExpired ? 'past' : 'days' ?></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-black text-secondary-900"><?= htmlspecialchars($r['brand'] . ' ' . $r['model']) ?></p>
                        <p class="text-xs text-secondary-400 font-bold"><span
                                class="text-primary-600"><?= htmlspecialchars($r['plate_number']) ?></span> ·
                            <?= str_replace('_', ' ', ucwords(str_replace('_', ' ', $r['compliance_type']))) ?></p>
                    </div>
                    <div class="text-sm font-bold text-<?= $color ?>-600"><?= formatDate($r['expiry_date']) ?></div>
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="vehicle-compliance.php?vehicle_id=<?= $r['vehicle_id'] ?>"
                            class="btn btn-ghost p-2 rounded-xl text-secondary-400 hover:text-primary-600"><i data-lucide="eye"
                                class="w-4 h-4"></i></a>
                        <a href="renew-upload.php?vehicle_id=<?= $r['vehicle_id'] ?>&type=<?= $r['compliance_type'] ?>"
                            class="btn btn-ghost p-2 rounded-xl text-secondary-400 hover:text-success-600"><i
                                data-lucide="refresh-cw" class="w-4 h-4"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>