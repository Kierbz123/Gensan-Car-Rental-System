<?php
/**
 * Dashboard Widget: Recent Activity
 * Shows today's key rental activity
 * Path: modules/dashboard/widgets/recent-activity.php
 */
$db = Database::getInstance();

$rentals = $db->fetchAll(
    "SELECT ra.agreement_number, ra.status, ra.rental_end_date,
            CONCAT(c.first_name,' ',c.last_name) AS customer_name,
            v.plate_number, v.brand, v.model
     FROM rental_agreements ra
     JOIN customers c ON ra.customer_id = c.customer_id
     JOIN vehicles v  ON ra.vehicle_id  = v.vehicle_id
     WHERE ra.status IN ('active','reserved')
     ORDER BY ra.rental_end_date ASC
     LIMIT 8"
);

$statusMap = [
    'active' => ['Active', 'badge-success'],
    'reserved' => ['Reserved', 'badge-info'],
    'returned' => ['Returned', 'badge-warning'],
];
?>
<div class="card">
    <div class="flex-between mb-4">
        <h3 class="font-semibold text-gray-900">Active Rentals</h3>
        <a href="<?php echo BASE_URL; ?>modules/rentals/index.php" class="text-sm text-blue-600 hover:underline">View
            All</a>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b border-gray-100">
                <th class="pb-2">Vehicle</th>
                <th class="pb-2">Customer</th>
                <th class="pb-2">Return</th>
                <th class="pb-2">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rentals as $rental):
                [$label, $cls] = $statusMap[$rental['status']] ?? [ucfirst($rental['status']), 'badge-info'];
                ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="py-3">
                        <div class="font-medium text-gray-900">
                            <?php echo htmlspecialchars($rental['brand'] . ' ' . $rental['model']); ?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?php echo htmlspecialchars($rental['plate_number']); ?>
                        </div>
                    </td>
                    <td class="py-3 text-gray-700">
                        <?php echo htmlspecialchars($rental['customer_name']); ?>
                    </td>
                    <td class="py-3 text-gray-500">
                        <?php echo htmlspecialchars($rental['rental_end_date']); ?>
                    </td>
                    <td class="py-3"><span class="badge <?php echo $cls; ?>">
                            <?php echo $label; ?>
                        </span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rentals)): ?>
                <tr>
                    <td colspan="4" class="text-center text-gray-400 py-6">No active rentals</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>