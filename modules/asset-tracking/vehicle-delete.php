<?php
/**
 * Vehicle Delete Handler (Soft Delete)
 */

require_once '../../includes/session-manager.php';

$authUser->requirePermission('vehicles.delete');

$vehicleId = $_GET['id'] ?? '';
$reason = $_GET['reason'] ?? 'No reason provided';

if (empty($vehicleId)) {
    redirect('modules/asset-tracking/', 'Vehicle ID is required', 'error');
}

try {
    $vehicle = new Vehicle();
    $vehicle->delete($vehicleId, $authUser->getId(), $reason);

    $_SESSION['success_message'] = 'Vehicle ' . $vehicleId . ' has been deleted successfully.';
    redirect('modules/asset-tracking/');

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    redirect('modules/asset-tracking/vehicle-details.php?id=' . $vehicleId);
}
