<?php
/**
 * Dashboard Widget: Maintenance Alerts
 * Shows overdue and upcoming maintenance items
 * Path: modules/dashboard/widgets/maintenance-alerts.php
 */
$db = Database::getInstance();
$overdue = $db->fetchAll(
    "SELECT ms.*, v.plate_number, v.brand, v.model
     FROM maintenance_schedules ms
     JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
     WHERE ms.next_service_date < CURDATE() AND ms.status = 'pending'
     ORDER BY ms.next_service_date ASC
     LIMIT 5"
);
$upcoming = $db->fetchAll(
    "SELECT ms.*, v.plate_number, v.brand, v.model,
            DATEDIFF(ms.next_service_date, CURDATE()) AS days_left
     FROM maintenance_schedules ms
     JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
     WHERE ms.next_service_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
       AND ms.status = 'pending'
     ORDER BY ms.next_service_date ASC
     LIMIT 5"
);
?>
<div class="card">
    <div class="flex-between mb-4">
        <h3 class="font-semibold text-gray-900">Maintenance Alerts</h3>
        <a href="<?php echo BASE_URL; ?>modules/maintenance/index.php"
            class="text-sm text-blue-600 hover:underline">View All</a>
    </div>

    <?php if (!empty($overdue)): ?>
        <div class="mb-3">
            <div class="label-caption text-red-600 mb-2">⚠ OVERDUE</div>
            <?php foreach ($overdue as $item): ?>
                <div class="flex items-center justify-between py-2 border-b border-gray-50">
                    <div>
                        <div class="text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($item['plate_number']); ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo htmlspecialchars($item['service_type']); ?>
                        </div>
                    </div>
                    <span class="badge badge-danger">
                        <?php echo htmlspecialchars($item['next_service_date']); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($upcoming)): ?>
        <div>
            <div class="label-caption text-yellow-600 mb-2">DUE THIS WEEK</div>
            <?php foreach ($upcoming as $item): ?>
                <div class="flex items-center justify-between py-2 border-b border-gray-50">
                    <div>
                        <div class="text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($item['plate_number']); ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo htmlspecialchars($item['service_type']); ?>
                        </div>
                    </div>
                    <span class="badge badge-warning">in
                        <?php echo (int) $item['days_left']; ?>d
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($overdue) && empty($upcoming)): ?>
        <div class="text-center text-gray-400 py-4">No urgent maintenance</div>
    <?php endif; ?>
</div>