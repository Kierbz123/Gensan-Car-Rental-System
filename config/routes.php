<?php
/**
 * Gensan Car Rental System - Routing Matrix
 * 
 * Maps friendly URL patterns to their physical file controllers.
 * Only routes to files that actually exist are included.
 * 
 * @package GCR_System
 */

return [
    // --- Authentication Gateway ---
    'login' => 'login.php',
    'logout' => 'logout.php',
    // 'forgot-password' and 'reset-password' — TODO: implement password recovery

    // --- Core Operations Deck ---
    'dashboard' => 'modules/dashboard/index.php',

    // --- Fleet Management Center ---
    'fleet' => 'modules/asset-tracking/index.php',
    'fleet/dossier' => 'modules/asset-tracking/vehicle-details.php',
    'fleet/register' => 'modules/asset-tracking/vehicle-add.php',
    'fleet/modify' => 'modules/asset-tracking/vehicle-edit.php',
    'fleet/status' => 'modules/asset-tracking/vehicle-status-update.php',
    'fleet/tags' => 'modules/asset-tracking/qr-generator.php',

    // --- Rental Operations Log ---
    'rentals' => 'modules/rentals/index.php',
    'rentals/schedule' => 'modules/rentals/reserve.php',
    'rentals/dispatch' => 'modules/rentals/check-out.php',
    'rentals/return' => 'modules/rentals/check-in.php',

    // --- Procurement & Supply Chain ---
    'procurement' => 'modules/procurement/index.php',
    'procurement/request' => 'modules/procurement/pr-view.php',
    'procurement/initiate' => 'modules/procurement/pr-create.php',
    'procurement/approve' => 'modules/procurement/pr-approve.php',
    'procurement/vendors' => 'modules/procurement/suppliers/index.php',

    // --- Maintenance Hub ---
    'maintenance' => 'modules/maintenance/index.php',
    'maintenance/logs' => 'modules/maintenance/service-view.php',
    'maintenance/schedule' => 'modules/maintenance/schedule.php',
    'maintenance/mechanics' => 'modules/maintenance/mechanics/index.php',

    // --- Client Intelligence ---
    'customers' => 'modules/customers/index.php',
    'customers/portfolio' => 'modules/customers/customer-view.php',
    'customers/register' => 'modules/customers/customer-add.php',
    'customers/modify' => 'modules/customers/customer-edit.php',

    // --- Regulatory & Risk ---
    'compliance' => 'modules/compliance/index.php',

    // --- Chauffeur & Driver Roster ---
    'drivers' => 'modules/drivers/index.php',
    'drivers/portfolio' => 'modules/drivers/driver-view.php',
    'drivers/register' => 'modules/drivers/driver-add.php',
    'drivers/modify' => 'modules/drivers/driver-edit.php',

    // --- Spare Parts & Inventory ---
    'inventory' => 'modules/inventory/index.php',
    'inventory/item' => 'modules/inventory/item-view.php',
    'inventory/stock' => 'modules/inventory/item-add.php',
    'inventory/modify' => 'modules/inventory/item-edit.php',

    // --- System Control & Configuration ---
    'settings' => 'modules/settings/index.php',
    'settings/profile' => 'modules/settings/profile.php',
    'settings/security' => 'modules/settings/security.php',
    'settings/users' => 'modules/settings/user-management.php',
    'settings/users/add' => 'modules/settings/user-add.php',
    'settings/users/edit' => 'modules/settings/user-edit.php',
    'settings/users/delete' => 'modules/settings/user-delete.php',

    // --- Business Insights ---
    'analytics' => 'modules/reports/index.php',
    'analytics/revenue' => 'modules/reports/revenue-summary.php',
    'analytics/maintenance' => 'modules/reports/maintenance-costs.php',
    'analytics/fleet' => 'modules/reports/fleet-utilization.php',
    'analytics/customers' => 'modules/reports/customer-analytics.php',
    'analytics/procurement' => 'modules/reports/procurement-summary.php',

    // --- System API v1 ---
    'api/v1/auth' => 'api/v1/auth/login.php',
    'api/v1/fleet' => 'api/v1/vehicles/get.php',
    'api/v1/clients' => 'api/v1/customers/get.php',
    'api/v1/engineering' => 'api/v1/maintenance/get-history.php',
    'api/v1/procurement' => 'api/v1/procurement/list-pr.php',
];
