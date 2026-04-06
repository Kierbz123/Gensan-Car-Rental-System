<?php
// modules/asset-tracking/vehicle-status-update.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('vehicles.update');

$vehicleId = $_GET['id'] ?? '';
if (empty($vehicleId)) {
    redirect('modules/asset-tracking/', 'Vehicle ID is required', 'error');
}

$vehicle = new Vehicle();
$vehicleData = $vehicle->getById($vehicleId);

if (!$vehicleData) {
    redirect('modules/asset-tracking/', 'Vehicle not found', 'error');
}

$errors = [];

// ── Handle POST before any output is sent ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }

        $newStatus      = $_POST['new_status'] ?? '';
        $mileage        = $_POST['mileage'] ?? null;
        $currentLocation = $_POST['current_location'] ?? null;
        $reason         = $_POST['reason'] ?? null;

        if (empty($newStatus)) {
            throw new Exception('Please select a new status.');
        }

        $vehicle->updateStatus(
            $vehicleId,
            $newStatus,
            $authUser->getId(),
            $currentLocation,
            $mileage ? floatval($mileage) : null,
            $reason
        );

        $_SESSION['success_message'] = 'Vehicle status updated successfully!';
        redirect('modules/asset-tracking/vehicle-details.php?id=' . urlencode($vehicleId));
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
// ── End POST handling ───────────────────────────────────────────────────────

$pageTitle = "Log Status Change - " . htmlspecialchars($vehicleData['brand'] . ' ' . $vehicleData['model']);
require_once '../../includes/header.php';

// Get available statuses for the dropdown (needs $VEHICLE_STATUS_LABELS from constants.php via config)
$validTransitions = $vehicle->getValidStatusTransitions($vehicleData['current_status']);

?>

<div class="page-header">
    <div class="page-title">
        <h1>Log Status Change</h1>
        <p>Update operational state for vehicle <?php echo htmlspecialchars($vehicleData['vehicle_id']); ?></p>
    </div>
    <div class="page-actions">
        <a href="vehicle-details.php?id=<?php echo urlencode($vehicleId); ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Profile
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="card" style="margin-bottom: var(--space-8); background: var(--danger-light); border-color: var(--danger);">
        <div class="card-body">
            <h5 style="margin-bottom: var(--space-2); font-weight: 700; display: flex; align-items: center; gap: var(--space-2); color: var(--danger);">
                <i data-lucide="alert-circle" style="width:18px;height:18px;"></i> Error
            </h5>
            <ul style="margin: 0; padding-left: var(--space-6);">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 42rem; margin-left: auto; margin-right: auto;">
    <div class="card-header">
        <h2 class="card-title">Update Operational Vector</h2>
    </div>
    <div class="card-body">
        <div class="grid" style="grid-template-columns: repeat(2, 1fr); gap: var(--space-4); margin-bottom: var(--space-6); padding: var(--space-4); background: var(--secondary-50); border-radius: var(--radius-md);">
            <div>
                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: bold; margin-bottom: 4px;">Current Status</label>
                <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                    <?php echo htmlspecialchars($VEHICLE_STATUS_LABELS[$vehicleData['current_status']] ?? $vehicleData['current_status']); ?>
                </div>
            </div>
            <div>
                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: bold; margin-bottom: 4px;">Recorded Mileage</label>
                <div style="font-size: 1.1rem; font-weight: 800; color: var(--text-main);">
                    <?php echo number_format($vehicleData['mileage']); ?> KM
                </div>
            </div>
        </div>

        <?php if (empty($validTransitions)): ?>
            <div style="text-align:center; padding: 2rem; color: var(--text-muted);">
                <i data-lucide="lock" style="width: 32px; height: 32px; margin-bottom: 1rem;"></i>
                <p>This vehicle is currently in a terminal state (e.g. Retired) and its status cannot be changed.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                
                <div class="form-group" style="margin-bottom: var(--space-4);">
                    <label for="new_status">New Status <span class="text-danger">*</span></label>
                    <select class="form-control" id="new_status" name="new_status" required>
                        <option value="">-- Select New Status --</option>
                        <?php foreach ($validTransitions as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>">
                                <?php echo htmlspecialchars($VEHICLE_STATUS_LABELS[$status] ?? $status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row form-row--two" style="margin-bottom: var(--space-4); align-items: start;">
                    <div class="form-group">
                        <label for="mileage">Update Mileage <span style="font-weight:normal;color:var(--text-muted);">(Optional)</span></label>
                        <input type="number" class="form-control" id="mileage" name="mileage" 
                            min="<?php echo $vehicleData['mileage']; ?>" 
                            placeholder="Current: <?php echo $vehicleData['mileage']; ?>">
                        <small style="color: var(--text-muted); font-size: 0.7rem; display: block; margin-top: 4px;">Must be &ge; current mileage.</small>
                    </div>
                    <div class="form-group">
                        <label for="current_location">Current Location <span style="font-weight:normal;color:var(--text-muted);">(Optional)</span></label>
                        <input type="text" class="form-control" id="current_location" name="current_location" 
                            value="<?php echo htmlspecialchars($vehicleData['current_location'] ?? ''); ?>">
                        <small style="color: transparent; font-size: 0.7rem; display: block; margin-top: 4px; user-select: none;">&nbsp;</small>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: var(--space-6);">
                    <label for="reason">Reason / Remarks <span style="font-weight:normal;color:var(--text-muted);">(Optional)</span></label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Explain the reason for status change..."></textarea>
                </div>

                <div style="display: flex; gap: var(--space-3); align-items: center;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">
                        <i data-lucide="activity" style="width: 16px; height: 16px;"></i> Commit Status Update
                    </button>
                    <a href="vehicle-details.php?id=<?php echo urlencode($vehicleId); ?>" class="btn btn-secondary" style="flex: 1; justify-content: center;">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
