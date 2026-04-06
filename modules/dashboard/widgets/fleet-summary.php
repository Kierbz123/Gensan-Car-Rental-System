<?php
/**
 * Dashboard Widget: Fleet Summary
 * Renders fleet status KPI cards
 * Path: modules/dashboard/widgets/fleet-summary.php
 */
$db = Database::getInstance();
$data = $db->fetchOne(
    "SELECT
        COUNT(*) AS total,
        SUM(current_status = 'available')   AS available,
        SUM(current_status = 'rented')      AS rented,
        SUM(current_status = 'maintenance') AS maintenance,
        SUM(current_status = 'reserved')    AS reserved
     FROM vehicles WHERE deleted_at IS NULL"
);

$utilRate = $data['total'] > 0
    ? round(($data['rented'] / $data['total']) * 100, 1)
    : 0;
?>
<div class="kpi-card card">
    <div class="flex-between">
        <span class="label-caption uppercase">Total Vehicles</span>
        <span class="p-2 bg-blue-50 rounded-lg"><i data-lucide="car" class="text-blue-600 w-5 h-5"></i></span>
    </div>
    <div class="kpi-value">
        <?php echo (int) $data['total']; ?>
    </div>
    <div class="text-sm text-gray-500">Fleet size across all categories</div>
</div>

<div class="kpi-card card">
    <div class="flex-between">
        <span class="label-caption uppercase">Available Now</span>
        <span class="p-2 bg-green-50 rounded-lg"><i data-lucide="check-circle"
                class="text-green-600 w-5 h-5"></i></span>
    </div>
    <div class="kpi-value text-green-600">
        <?php echo (int) $data['available']; ?>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-1 mt-2">
        <?php $w = $data['total'] > 0 ? round(($data['available'] / $data['total']) * 100) : 0; ?>
        <div class="bg-green-500 h-1 rounded-full" style="width:<?php echo $w; ?>%"></div>
    </div>
</div>

<div class="kpi-card card">
    <div class="flex-between">
        <span class="label-caption uppercase">Currently Rented</span>
        <span class="p-2 bg-red-50 rounded-lg"><i data-lucide="user-check" class="text-red-500 w-5 h-5"></i></span>
    </div>
    <div class="kpi-value text-red-500">
        <?php echo (int) $data['rented']; ?>
    </div>
    <div class="text-sm text-gray-500">Utilization:
        <?php echo $utilRate; ?>%
    </div>
</div>

<div class="kpi-card card">
    <div class="flex-between">
        <span class="label-caption uppercase">Under Maintenance</span>
        <span class="p-2 bg-yellow-50 rounded-lg"><i data-lucide="alert-triangle"
                class="text-yellow-500 w-5 h-5"></i></span>
    </div>
    <div class="kpi-value text-yellow-500">
        <?php echo (int) $data['maintenance']; ?>
    </div>
    <div class="text-sm text-gray-500">Reserved:
        <?php echo (int) $data['reserved']; ?>
    </div>
</div>