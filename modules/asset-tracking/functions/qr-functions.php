<?php
// modules/asset-tracking/functions/qr-functions.php

/**
 * Get the full URL to a vehicle's QR code image
 */
function getVehicleQRPath($vehicleId)
{
    $qrPath = QR_CODES_PATH . $vehicleId . '.png';
    if (file_exists($qrPath)) {
        return ASSETS_URL . 'images/qr-codes/' . $vehicleId . '.png';
    }

    // Return a placeholder or default if not generated
    return ASSETS_URL . 'images/defaults/qr-placeholder.png';
}

/**
 * Get the link that the QR code points to
 */
function getVehicleQRLink($vehicleId)
{
    return BASE_URL . 'modules/asset-tracking/vehicle-details.php?id=' . urlencode($vehicleId);
}

/**
 * Check if QR code file exists for a vehicle
 */
function qrCodeExists($vehicleId)
{
    return file_exists(QR_CODES_PATH . $vehicleId . '.png');
}

/**
 * Force regenerate QR code for a vehicle
 */
function regenerateQRCode($vehicleId)
{
    try {
        $vehicle = new Vehicle();
        return $vehicle->generateQRCode($vehicleId);
    } catch (Exception $e) {
        logError("Failed to regenerate QR code for $vehicleId: " . $e->getMessage());
        return false;
    }
}
