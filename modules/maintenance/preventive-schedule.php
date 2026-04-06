<?php
/**
 * Preventive Maintenance Schedule
 * Path: modules/maintenance/preventive-schedule.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('maintenance.view');
$db = Database::getInstance();

try {
    $vehicles = $db->fetchAll(
        "SELECT v.*, v.mileage,
                (SELECT MAX(ml.service_date) FROM maintenance_logs ml
                 WHERE ml.vehicle_id = v.vehicle_id AND ml.service_type = 'oil_change'
                 AND ml.status = 'completed') AS last_oil_change,
                (SELECT MAX(ml.service_date) FROM maintenance_logs ml
                 WHERE ml.vehicle_id = v.vehicle_id AND ml.service_type = 'tire_rotation'
                 AND ml.status = 'completed') AS last_tire_rotation,
                (SELECT MAX(ml.service_date) FROM maintenance_logs ml
                 WHERE ml.vehicle_id = v.vehicle_id
                 AND ml.status = 'completed') AS last_service
         FROM vehicles v
         WHERE v.deleted_at IS NULL AND v.current_status != 'decommissioned'
         ORDER BY v.brand, v.model"
    );
} catch (Exception $e) {
    $vehicles = [];
}

$pageTitle = 'Preventive Maintenance Schedule';
require_once '../../includes/header.php';
?>
<div class="fade-in">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="heading">Preventive Maintenance Matrix</h1>
            <p class="text-secondary-500 font-medium">Track mandatory service intervals across the fleet.</p>
        </div>
        <?php if ($authUser->hasPermission('maintenance.create')): ?>
            <a href="schedule-add.php" class="btn btn-primary gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Schedule
                Service</a>
        <?php endif; ?>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="p-5 bg-secondary-900">
            <h2 class="text-xs font-black uppercase tracking-widest text-pure-white">Fleet Preventive Schedule
                (<?= count($vehicles) ?> units)</h2>
        </div>
        <div class="table-wrapper border-none rounded-none">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Mileage</th>
                        <th>Last Oil Change</th>
                        <th>Last Tire Rotation</th>
                        <th>Last Service</th>
                        <th>PM Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-16 text-secondary-400">No vehicles found.</td>
                        </tr>
                    <?php else:
                        foreach ($vehicles as $v):
                            $daysSinceOil = $v['last_oil_change'] ? ceil((time() - strtotime($v['last_oil_change'])) / 86400) : 9999;
                            $daysSinceTire = $v['last_tire_rotation'] ? ceil((time() - strtotime($v['last_tire_rotation'])) / 86400) : 9999;
                            $oilDue = $daysSinceOil >= 90;
                            $tireDue = $daysSinceTire >= 180;
                            $status = ($oilDue || $tireDue) ? 'danger' : 'success';
                            $statusTxt = ($oilDue || $tireDue) ? 'SERVICE DUE' : 'ON TRACK';
                            ?>
                            <tr class="group">
                                <td>
                                    <div class="font-bold text-sm"><?= htmlspecialchars($v['brand'] . ' ' . $v['model']) ?>
                                    </div>
                                    <div class="text-[10px] font-black text-primary-500 uppercase tracking-widest">
                                        <?= htmlspecialchars($v['plate_number']) ?>
                                    </div>
                                </td>
                                <td class="font-bold font-mono"><?= number_format($v['mileage'] ?? 0) ?> km</td>
                                <td>
                                    <div class="text-sm font-bold <?= $oilDue ? 'text-danger-600' : '' ?>">
                                        <?= $v['last_oil_change'] ? formatDate($v['last_oil_change']) : '<span class="text-secondary-400">Never</span>' ?>
                                    </div>
                                    <?php if ($oilDue): ?>
                                        <div class="text-[10px] font-bold text-danger-500 animate-pulse">OIL CHANGE DUE</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm font-bold <?= $tireDue ? 'text-danger-600' : '' ?>">
                                        <?= $v['last_tire_rotation'] ? formatDate($v['last_tire_rotation']) : '<span class="text-secondary-400">Never</span>' ?>
                                    </div>
                                    <?php if ($tireDue): ?>
                                        <div class="text-[10px] font-bold text-danger-500 animate-pulse">ROTATION DUE</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm font-bold">
                                    <?= $v['last_service'] ? formatDate($v['last_service']) : '<span class="text-secondary-400">No records</span>' ?>
                                </td>
                                <td><span
                                        class="badge badge-<?= $status ?> text-[9px] uppercase font-black tracking-widest"><?= $statusTxt ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-6 card bg-secondary-50 border-secondary-100">
        <p class="text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-3">Interval Guidelines</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs font-bold text-secondary-700">
            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 bg-warning-400 rounded-full"></span> Oil
                Change: Every 90 days</div>
            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 bg-primary-400 rounded-full"></span> Tire
                Rotation: Every 180 days</div>
            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 bg-success-400 rounded-full"></span> On Track:
                All services current</div>
            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 bg-danger-400 rounded-full"></span> Service
                Due: Action required</div>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>