<?php
/**
 * Maintenance Schedule View
 * Path: modules/maintenance/schedule.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('maintenance.view');
$db = Database::getInstance();

try {
    $scheduled = $db->fetchAll(
        "SELECT ms.*, v.plate_number, v.brand, v.model
         FROM maintenance_schedules ms
         JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
         WHERE ms.status IN ('scheduled','in_progress')
         ORDER BY ms.next_due_date ASC"
    );
} catch (Exception $e) {
    error_log($e->getMessage());
    $scheduled = [];
}

$pageTitle = 'Maintenance Schedule';
require_once '../../includes/header.php';
?>
<div class="fade-in">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="heading">Maintenance Schedule</h1>
            <p class="text-secondary-500 font-medium">Upcoming and in-progress service appointments.</p>
        </div>
        <?php if ($authUser->hasPermission('maintenance.create')): ?>
            <a href="schedule-add.php" class="btn btn-primary gap-2 shadow-md shadow-primary-200">
                <i data-lucide="plus" class="w-4 h-4"></i> Schedule Service
            </a>
        <?php endif; ?>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="p-5 bg-secondary-900 flex items-center gap-2">
            <i data-lucide="calendar-clock" class="w-4 h-4 text-secondary-400"></i>
            <h2 class="text-xs font-black uppercase tracking-widest text-pure-white">Scheduled Services
                (<?= count($scheduled) ?>)</h2>
        </div>
        <div class="table-wrapper border-none rounded-none">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Service Type</th>
                        <th>Scheduled Date</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scheduled)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-16 text-secondary-400 font-medium">No scheduled
                                maintenance found.</td>
                        </tr>
                    <?php else:
                        foreach ($scheduled as $s):
                            $diff = ceil((strtotime($s['next_due_date']) - time()) / 86400);
                            $urgency = $diff < 0 ? 'danger' : ($diff <= 3 ? 'warning' : 'secondary');
                            ?>
                            <tr class="group">
                                <td>
                                    <div class="font-bold text-sm"><?= htmlspecialchars($s['brand'] . ' ' . $s['model']) ?>
                                    </div>
                                    <div class="text-[10px] font-bold text-primary-500 tracking-widest uppercase">
                                        <?= htmlspecialchars($s['plate_number']) ?>
                                    </div>
                                </td>
                                <td><span
                                        class="badge badge-secondary text-[10px] uppercase tracking-widest"><?= str_replace('_', ' ', ucfirst($s['service_type'] ?? $s['schedule_type'] ?? 'service')) ?></span>
                                </td>
                                <td>
                                    <div class="font-bold text-sm"><?= formatDate($s['next_due_date']) ?></div>
                                    <div class="text-[10px] font-bold text-<?= $urgency ?>-500">
                                        <?= $diff < 0 ? abs($diff) . ' days overdue' : ($diff == 0 ? 'Today' : 'in ' . $diff . ' days') ?>
                                    </div>
                                </td>
                                <td><span
                                        class="badge badge-<?= $s['status'] === 'in_progress' ? 'primary' : 'warning' ?> text-[9px] uppercase font-black tracking-widest"><?= strtoupper($s['status']) ?></span>
                                </td>
                                <td class="text-right">
                                    <a href="../asset-tracking/vehicle-details.php?id=<?= $s['vehicle_id'] ?>"
                                        class="btn btn-ghost p-2 rounded-xl hover:text-primary-600"><i data-lucide="eye"
                                            class="w-4 h-4"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>