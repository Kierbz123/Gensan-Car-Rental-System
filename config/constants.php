<?php
// /var/www/html/gensan-car-rental-system/config/constants.php

/**
 * System Constants and Enumerations
 */

// Vehicle Status Constants
define('VEHICLE_STATUS_AVAILABLE', 'available');
define('VEHICLE_STATUS_RENTED', 'rented');
define('VEHICLE_STATUS_MAINTENANCE', 'maintenance');
define('VEHICLE_STATUS_RESERVED', 'reserved');
define('VEHICLE_STATUS_CLEANING', 'cleaning');
define('VEHICLE_STATUS_OUT_OF_SERVICE', 'out_of_service');
define('VEHICLE_STATUS_RETIRED', 'retired');

$VEHICLE_STATUS_LABELS = [
    VEHICLE_STATUS_AVAILABLE => 'Available',
    VEHICLE_STATUS_RENTED => 'Rented',
    VEHICLE_STATUS_MAINTENANCE => 'Under Maintenance',
    VEHICLE_STATUS_RESERVED => 'Reserved',
    VEHICLE_STATUS_CLEANING => 'Cleaning',
    VEHICLE_STATUS_OUT_OF_SERVICE => 'Out of Service',
    VEHICLE_STATUS_RETIRED => 'Retired'
];

$VEHICLE_STATUS_COLORS = [
    VEHICLE_STATUS_AVAILABLE => 'success',
    VEHICLE_STATUS_RENTED => 'danger',
    VEHICLE_STATUS_MAINTENANCE => 'warning',
    VEHICLE_STATUS_RESERVED => 'info',
    VEHICLE_STATUS_CLEANING => 'purple',
    VEHICLE_STATUS_OUT_OF_SERVICE => 'secondary',
    VEHICLE_STATUS_RETIRED => 'dark'
];

// Vehicle Categories
$VEHICLE_CATEGORIES = [
    'HB' => ['name' => 'Hatchback', 'seats' => 5, 'fuel' => 'gasoline'],
    'SD' => ['name' => 'Sedan', 'seats' => 5, 'fuel' => 'gasoline'],
    'MP' => ['name' => 'MPV', 'seats' => 7, 'fuel' => 'gasoline'],
    'PU' => ['name' => 'Pickup', 'seats' => 3, 'fuel' => 'diesel'],
    'SU' => ['name' => 'SUV', 'seats' => 7, 'fuel' => 'diesel'],
    'VN' => ['name' => 'Van', 'seats' => 15, 'fuel' => 'diesel'],
    'LX' => ['name' => 'Luxury', 'seats' => 5, 'fuel' => 'gasoline']
];

// User Roles
define('ROLE_SYSTEM_ADMIN', 'system_admin');
define('ROLE_FLEET_MANAGER', 'fleet_manager');
define('ROLE_PROCUREMENT_OFFICER', 'procurement_officer');
define('ROLE_MAINTENANCE_SUPERVISOR', 'maintenance_supervisor');
define('ROLE_CUSTOMER_SERVICE', 'customer_service_staff');
define('ROLE_MECHANIC', 'mechanic');
define('ROLE_VIEWER', 'viewer');

$ROLE_PERMISSIONS = [
    ROLE_SYSTEM_ADMIN => ['*'], // All permissions

    ROLE_FLEET_MANAGER => [
        'dashboard.*',
        'vehicles.*',
        'maintenance.*',
        'procurement.*',
        'customers.*',
        'compliance.*',
        'reports.*',
        'settings.*',
        'drivers.*',
        'inventory.*',
        'rentals.*',
        'suppliers.*',
        'documents.*',
        'mechanics.*'
    ],

    ROLE_PROCUREMENT_OFFICER => [
        'dashboard.view',
        'vehicles.view',
        'procurement.*',
        'suppliers.*',
        'inventory.*',
        'reports.procurement',
        'settings.view'
    ],

    ROLE_MAINTENANCE_SUPERVISOR => [
        'dashboard.view',
        'vehicles.view',
        'maintenance.*',
        'mechanics.*',
        'procurement.create',
        'procurement.view',
        'inventory.view',
        'inventory.update',
        'reports.maintenance',
        'settings.view'
    ],

    ROLE_CUSTOMER_SERVICE => [
        'dashboard.view',
        'vehicles.view',
        'customers.*',
        'rentals.*',
        'drivers.view',
        'reports.customers',
        'settings.view'
    ],

    ROLE_MECHANIC => [
        'dashboard.view',
        'vehicles.view',
        'maintenance.view',
        'maintenance.update',
        'maintenance.complete',
        'settings.view'
    ],

    ROLE_VIEWER => [
        'dashboard.view',
        'vehicles.view',
        'reports.view',
        'settings.view'
    ]
];

// PR Status
define('PR_STATUS_DRAFT', 'draft');
define('PR_STATUS_PENDING', 'pending_approval');
define('PR_STATUS_APPROVED', 'approved');
define('PR_STATUS_REJECTED', 'rejected');
define('PR_STATUS_ORDERED', 'ordered');
define('PR_STATUS_PARTIALLY_RECEIVED', 'partially_received');
define('PR_STATUS_FULLY_RECEIVED', 'fully_received');
define('PR_STATUS_CANCELLED', 'cancelled');
define('PR_STATUS_CLOSED', 'closed');

// Maintenance Service Types
$MAINTENANCE_SERVICE_TYPES = [
    'oil_change' => 'Oil Change',
    'tire_rotation' => 'Tire Rotation',
    'brake_inspection' => 'Brake Inspection',
    'engine_tuneup' => 'Engine Tune-up',
    'transmission_service' => 'Transmission Service',
    'aircon_cleaning' => 'Aircon Cleaning',
    'battery_check' => 'Battery Check',
    'coolant_flush' => 'Coolant Flush',
    'timing_belt' => 'Timing Belt Replacement',
    'general_checkup' => 'General Check-up',
    'emergency_repair' => 'Emergency Repair',
    'body_repair' => 'Body Repair',
    'detailing' => 'Detailing',
    'others' => 'Others'
];

// Compliance Types
$COMPLIANCE_TYPES = [
    'lto_registration' => 'LTO Registration',
    'insurance_comprehensive' => 'Insurance (Comprehensive)',
    'insurance_tpl' => 'Insurance (TPL)',
    'emission_test' => 'Emission Test',
    'franchise_ltfrb' => 'LTFRB Franchise',
    'pnp_clearance' => 'PNP Clearance',
    'mayors_permit' => 'Mayor\'s Permit'
];

// Rental Status
define('RENTAL_STATUS_RESERVED', 'reserved');
define('RENTAL_STATUS_CONFIRMED', 'confirmed');
define('RENTAL_STATUS_ACTIVE', 'active');
define('RENTAL_STATUS_RETURNED', 'returned');
define('RENTAL_STATUS_COMPLETED', 'completed');
define('RENTAL_STATUS_CANCELLED', 'cancelled');
define('RENTAL_STATUS_NO_SHOW', 'no_show');

// Notification Types
$NOTIFICATION_TYPES = [
    'maintenance_due' => ['icon' => 'wrench', 'color' => 'warning'],
    'maintenance_overdue' => ['icon' => 'exclamation-triangle', 'color' => 'danger'],
    'compliance_expiring' => ['icon' => 'calendar', 'color' => 'warning'],
    'compliance_expired' => ['icon' => 'times-circle', 'color' => 'danger'],
    'pr_pending_approval' => ['icon' => 'clipboard', 'color' => 'info'],
    'pr_approved' => ['icon' => 'check-circle', 'color' => 'success'],
    'pr_rejected' => ['icon' => 'times-circle', 'color' => 'danger'],
    'vehicle_returned' => ['icon' => 'car', 'color' => 'success'],
    'new_rental' => ['icon' => 'plus-circle', 'color' => 'primary'],
    'system_alert' => ['icon' => 'bell', 'color' => 'warning'],
    'message' => ['icon' => 'envelope', 'color' => 'info']
];
