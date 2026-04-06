<?php
$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$isActive = function ($path) use ($currentUri) {
    return strpos($currentUri, $path) !== false ? 'active' : '';
};

// --- Notification Badge System ---
$navBadges = [
    'procurement' => 0,
    'inventory' => 0,
    'maintenance' => 0,
    'rentals' => 0,
    'compliance' => 0
];

try {
    $db = Database::getInstance();
    
    if ($authUser->hasPermission('rentals.view')) {
        $navBadges['rentals'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM rental_agreements WHERE status IN ('confirmed', 'active')");
    }
    if ($authUser->hasPermission('procurement.view')) {
        $navBadges['procurement'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM procurement_requests WHERE status = 'pending_approval'");
    }
    if ($authUser->hasPermission('inventory.view')) {
        $navBadges['inventory'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM parts_inventory WHERE reorder_level > 0 AND quantity_on_hand <= reorder_level");
    }
    if ($authUser->hasPermission('maintenance.view')) {
        $navBadges['maintenance'] = (int) $db->fetchColumn("
            SELECT COUNT(*) FROM maintenance_schedules ms
            JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
            WHERE ms.status IN ('scheduled', 'active', 'overdue') 
              AND (ms.next_due_date <= CURDATE() OR v.mileage >= ms.next_due_mileage)
        ");
    }
    if ($authUser->hasPermission('compliance.view')) {
        $navBadges['compliance'] = (int) $db->fetchColumn("
            SELECT COUNT(*) FROM compliance_records c 
            WHERE expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) 
              AND status NOT IN ('renewed', 'cancelled')
              AND record_id = (
                  SELECT MAX(record_id)
                  FROM compliance_records c2
                  WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
              )
        ");
    }
} catch (Exception $e) {
    // Silently continue if database connection fails
}

$renderNavBadge = function($count, $title) {
    if ($count <= 0) return '';
    $displayCount = $count > 99 ? '99+' : $count;
    return '<span class="nav-badge" title="' . htmlspecialchars($title) . '">' . $displayCount . '</span>';
};
?>
<style>
.nav-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  background: var(--danger, #ef4444);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
  box-shadow: 0 0 0 2px rgba(255,255,255,0.15);
  transition: transform 0.2s ease;
  pointer-events: none;
}
.button:hover .nav-badge, 
.button.active .nav-badge {
  transform: scale(1.1);
}
</style>
<nav>
    <strong>GCR Admin</strong>
    <ul>
        <li><a href="<?= $base ?>/modules/dashboard/index.php" class="button <?= $isActive('/modules/dashboard') ?>"><i
                    data-lucide="layout-dashboard"></i><span class="nav-label">Dashboard</span></a></li>
        <?php if ($authUser->hasPermission('vehicles.view')): ?>
            <li><a href="<?= $base ?>/modules/asset-tracking/index.php"
                    class="button <?= $isActive('/modules/asset-tracking') ?>"><i data-lucide="car"></i><span
                        class="nav-label">Fleet</span></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('rentals.view')): ?>
            <li><a href="<?= $base ?>/modules/rentals/index.php" class="button <?= $isActive('/modules/rentals') ?>" style="position: relative;"><i
                        data-lucide="calendar-check"></i><span class="nav-label">Rentals</span><?= $renderNavBadge($navBadges['rentals'], 'Active/Upcoming Rentals') ?></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('customers.view')): ?>
            <li><a href="<?= $base ?>/modules/customers/index.php" class="button <?= $isActive('/modules/customers') ?>"><i
                        data-lucide="users"></i><span class="nav-label">Clients</span></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('drivers.view')): ?>
            <li><a href="<?= $base ?>/modules/drivers/index.php" class="button <?= $isActive('/modules/drivers') ?>"><i
                        data-lucide="user-check"></i><span class="nav-label">Drivers</span></a></li>
        <?php endif; ?>
        <li>
            <hr>
        </li>
        <?php if ($authUser->hasPermission('procurement.view')): ?>
            <li><a href="<?= $base ?>/modules/procurement/index.php"
                    class="button <?= $isActive('/modules/procurement') ?>" style="position: relative;"><i data-lucide="shopping-cart"></i><span
                        class="nav-label">Procurement</span><?= $renderNavBadge($navBadges['procurement'], 'Pending Requests') ?></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('suppliers.view')): ?>
            <li><a href="<?= $base ?>/modules/suppliers/index.php"
                    class="button <?= $isActive('/modules/suppliers') ?>"><i data-lucide="truck"></i><span
                        class="nav-label">Suppliers</span></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('inventory.view')): ?>
            <li><a href="<?= $base ?>/modules/inventory/index.php" class="button <?= $isActive('/modules/inventory') ?>" style="position: relative;"><i
                        data-lucide="package"></i><span class="nav-label">Inventory</span><?= $renderNavBadge($navBadges['inventory'], 'Low Stock Items') ?></a></li>
        <?php endif; ?>
        <li>
            <hr>
        </li>
        <?php if ($authUser->hasPermission('maintenance.view')): ?>
            <li><a href="<?= $base ?>/modules/maintenance/index.php"
                    class="button <?= $isActive('/modules/maintenance') ?>" style="position: relative;"><i data-lucide="wrench"></i><span
                        class="nav-label">Maintenance</span><?= $renderNavBadge($navBadges['maintenance'], 'Due/Overdue Services') ?></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('compliance.view')): ?>
            <li><a href="<?= $base ?>/modules/compliance/index.php"
                    class="button <?= $isActive('/modules/compliance') ?>" style="position: relative;"><i data-lucide="shield-check"></i><span
                        class="nav-label">Compliance</span><?= $renderNavBadge($navBadges['compliance'], 'Breached Instruments') ?></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('documents.view') || in_array($authUser->getData()['role'], ['system_admin', 'fleet_manager', 'customer_service_staff'])): ?>
            <li><a href="<?= $base ?>/modules/documents/index.php"
                    class="button <?= $isActive('/modules/documents') ?>"><i data-lucide="folder-open"></i><span
                        class="nav-label">Documents</span></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('reports.view')): ?>
            <li><a href="<?= $base ?>/modules/reports/index.php" class="button <?= $isActive('/modules/reports') ?>"><i
                        data-lucide="bar-chart-2"></i><span class="nav-label">Reports</span></a></li>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('settings.view') || $authUser->getData()['role'] === 'system_admin'): ?>
            <li><a href="<?= $base ?>/modules/settings/index.php" class="button <?= $isActive('/modules/settings') ?>"><i
                        data-lucide="settings"></i><span class="nav-label">Settings</span></a></li>
        <?php endif; ?>
        <?php if ($authUser->getData()['role'] === 'system_admin'): ?>
            <li><a href="<?= $base ?>/modules/backups/index.php" class="button <?= $isActive('/modules/backups') ?>"><i
                        data-lucide="database"></i><span class="nav-label">Backups</span></a></li>
        <?php endif; ?>
        <li style="margin-top:auto;">
            <hr>
        </li>
        <li><a href="#" onclick="confirmLogout(event, '<?= $base ?>/logout.php')" class="button logout-btn"><i
                    data-lucide="log-out"></i><span class="nav-label">Sign Out</span></a></li>
    </ul>
</nav>

<script>
    function confirmLogout(event, logoutUrl) {
        event.preventDefault();
        if (typeof openGcrModal === 'function') {
            openGcrModal(
                'Confirm Sign Out',
                'Are you sure you want to securely end your current session?',
                function () {
                    window.location.href = logoutUrl;
                },
                {
                    variant: 'danger',
                    confirmLabel: 'Yes, Sign Out',
                    icon: 'log-out'
                }
            );
        } else {
            if (confirm('Are you sure you want to securely end your current session?')) {
                window.location.href = logoutUrl;
            }
        }
    }
</script>