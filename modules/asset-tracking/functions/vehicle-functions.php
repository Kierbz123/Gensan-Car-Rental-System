<?php
// modules/asset-tracking/functions/vehicle-functions.php
//
// NOTE: This file must be explicitly included wherever its functions are used,
// e.g.: require_once __DIR__ . '/functions/vehicle-functions.php';
// It is NOT auto-loaded. Requires: Database class, VEHICLE_STATUS_* constants.

/**
 * Get CSS badge class based on vehicle status
 */
function getVehicleStatusBadge($status)
{
    $badges = [
        'available' => '<span class="badge badge-success">Available</span>',
        'rented' => '<span class="badge badge-primary">Rented</span>',
        'maintenance' => '<span class="badge badge-warning">Maintenance</span>',
        'cleaning' => '<span class="badge badge-info text-dark">Cleaning</span>',
        'reserved'      => '<span class="badge badge-info">Reserved</span>',
        'out_of_service' => '<span class="badge badge-danger">Out of Service</span>',
        'retired' => '<span class="badge badge-secondary opacity-50">Retired</span>'
    ];

    return $badges[$status] ?? '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
}

/**
 * Get fuel type label
 */
function getFuelTypeLabel($type)
{
    $labels = [
        'gasoline' => 'Gasoline',
        'diesel' => 'Diesel',
        'electric' => 'Electric',
        'hybrid' => 'Hybrid'
    ];
    return $labels[$type] ?? ucfirst($type);
}

/**
 * Get transmission label
 */
function getTransmissionLabel($type)
{
    $labels = [
        'manual' => 'Manual',
        'automatic' => 'Automatic',
        'cvt' => 'CVT'
    ];
    return $labels[$type] ?? ucfirst($type);
}

/**
 * Get vehicle category options for select input
 */
function getVehicleCategoryOptions($selectedId = null)
{
    $db = Database::getInstance();
    $categories = $db->fetchAll("SELECT category_id, category_name FROM vehicle_categories ORDER BY category_name ASC");

    $html = '<option value="">Select Category</option>';
    foreach ($categories as $cat) {
        $id   = $cat['category_id'];
        $name = $cat['category_name'];
        $selected = ((string) $id === (string) $selectedId) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars((string) $id) . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
    }

    return $html;
}

/**
 * Get all vehicles for a select input
 */
function getVehicleOptions($selectedId = null)
{
    $db = Database::getInstance();
    $vehicles = $db->fetchAll("SELECT vehicle_id, plate_number, brand, model FROM vehicles WHERE deleted_at IS NULL ORDER BY brand ASC, model ASC");

    $html = '<option value="">Select Vehicle</option>';
    foreach ($vehicles as $v) {
        $label = htmlspecialchars("{$v['plate_number']} - {$v['brand']} {$v['model']}");
        $selected = ((string) $v['vehicle_id'] === (string) $selectedId) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars((string) $v['vehicle_id']) . '"' . $selected . '>' . $label . '</option>';
    }

    return $html;
}

/**
 * Check if vehicle is available for rental
 */
function isVehicleAvailable($vehicleId)
{
    $db = Database::getInstance();
    $status = $db->fetchColumn("SELECT current_status FROM vehicles WHERE vehicle_id = ? AND deleted_at IS NULL", [$vehicleId]);
    return $status === VEHICLE_STATUS_AVAILABLE;
}
