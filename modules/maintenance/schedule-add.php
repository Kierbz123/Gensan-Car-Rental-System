<?php
/**
 * Schedule Maintenance
 * Path: modules/maintenance/schedule-add.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission check BEFORE any output
$authUser->requirePermission('maintenance.create');

$db = Database::getInstance();

// Fetch vehicles for dropdown (exclude sold/decommissioned)
$vehicles = $db->fetchAll(
    "SELECT vehicle_id, plate_number, brand, model
     FROM vehicles
     WHERE current_status NOT IN ('sold','decommissioned')
     ORDER BY brand ASC"
);

// Valid ENUM values matching the DB schema exactly
$validServiceTypes = [
    'oil_change'           => 'Oil Change',
    'tire_rotation'        => 'Tire Rotation',
    'brake_inspection'     => 'Brake Inspection',
    'engine_tuneup'        => 'Engine Tune-up',
    'transmission_service' => 'Transmission Service',
    'aircon_cleaning'      => 'Aircon Cleaning',
    'battery_check'        => 'Battery Check',
    'coolant_flush'        => 'Coolant Flush',
    'timing_belt'          => 'Timing Belt',
    'general_checkup'      => 'General Checkup',
    'others'               => 'Others',
];

$validBases = [
    'time_only'        => 'By Date Only',
    'mileage_only'     => 'By Mileage Only',
    'time_and_mileage' => 'Date & Mileage',
];

$errors = [];
$old    = [];  // repopulate inputs on error

// Generate CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid security token. Please try again.';
        header('Location: schedule-add.php');
        exit;
    }

    $old = $_POST;

    // --- Server-side validation ---
    $vehicle_id     = trim($_POST['vehicle_id']   ?? '');
    $service_type   = trim($_POST['service_type'] ?? '');
    $schedule_basis = trim($_POST['schedule_basis'] ?? '');
    $next_due_date  = trim($_POST['next_due_date'] ?? '');
    $notes          = trim($_POST['remarks']       ?? '');

    if ($vehicle_id === '') {
        $errors[] = 'Please select a vehicle.';
    }
    if (!array_key_exists($service_type, $validServiceTypes)) {
        $errors[] = 'Invalid service type selected.';
    }
    if (!array_key_exists($schedule_basis, $validBases)) {
        $errors[] = 'Please select a schedule basis.';
    }
    if ($next_due_date === '' || !strtotime($next_due_date)) {
        $errors[] = 'A valid Next Due Date is required.';
    }

    // Confirm vehicle actually exists
    if (empty($errors)) {
        $vehicleCheck = $db->fetchOne(
            "SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND current_status NOT IN ('sold','decommissioned')",
            [$vehicle_id]
        );
        if (!$vehicleCheck) {
            $errors[] = 'Selected vehicle is invalid.';
        }
    }

    if (empty($errors)) {
        try {
            $db->insert(
                "INSERT INTO maintenance_schedules
                    (vehicle_id, service_type, schedule_basis, next_due_date, status, notes)
                 VALUES (?, ?, ?, ?, 'scheduled', ?)",
                [
                    $vehicle_id,
                    $service_type,
                    $schedule_basis,
                    $next_due_date,
                    $notes ?: null,
                ]
            );

            $_SESSION['success_message'] = 'Maintenance scheduled successfully!';
            header('Location: index.php');
            exit;

        } catch (Exception $e) {
            error_log('schedule-add.php INSERT error: ' . $e->getMessage());
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Schedule Maintenance';
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Schedule New Maintenance</h1>
        <p>Create a preventative service record for a fleet unit.</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Hub
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div style="margin-bottom:1.5rem; padding:1rem; background:var(--danger-light); color:var(--danger);
                border-radius:var(--radius-md); font-weight:500; display:flex; align-items:flex-start; gap:0.5rem;">
        <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;margin-top:2px;"></i>
        <ul style="margin:0;padding-left:1rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="card" style="max-width:800px;">
        <div class="card-header">
            <h2 class="card-title">
                <i data-lucide="wrench" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>
                Service Details
            </h2>
        </div>
        <div class="card-body">

            <!-- Row 1: Vehicle + Service Type -->
            <div class="form-row" style="margin-bottom:1.5rem;">
                <div class="form-group">
                    <label>Select Vehicle <span style="color:var(--danger)">*</span></label>
                    <select name="vehicle_id" class="form-control" required>
                        <option value="">-- Select Unit --</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= htmlspecialchars($v['vehicle_id']) ?>"
                                <?= ($old['vehicle_id'] ?? '') === $v['vehicle_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['brand'] . ' ' . $v['model'] . ' (' . $v['plate_number'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Service Type <span style="color:var(--danger)">*</span></label>
                    <select name="service_type" class="form-control" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($validServiceTypes as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= ($old['service_type'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Schedule Basis + Next Due Date -->
            <div class="form-row" style="margin-bottom:1.5rem;">
                <div class="form-group">
                    <label>Schedule Basis <span style="color:var(--danger)">*</span></label>
                    <select name="schedule_basis" class="form-control" required>
                        <option value="">-- Select Basis --</option>
                        <?php foreach ($validBases as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= ($old['schedule_basis'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Next Due Date <span style="color:var(--danger)">*</span></label>
                    <input type="date" name="next_due_date" class="form-control" required
                           value="<?= htmlspecialchars($old['next_due_date'] ?? '') ?>">
                </div>
            </div>

            <!-- Remarks -->
            <div class="form-group" style="margin-bottom:2rem;">
                <label>Remarks / Instructions</label>
                <textarea name="remarks" class="form-control" rows="4"
                          style="resize:none;"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
            </div>

            <!-- Actions -->
            <div style="display:flex; gap:1rem;">
                <button type="submit" class="btn btn-primary" style="padding:0.8rem 2rem;">
                    <i data-lucide="calendar-plus" style="width:16px;height:16px;"></i> Schedule Service
                </button>
                <a href="index.php" class="btn btn-ghost" style="padding:0.8rem 2rem;">Cancel</a>
            </div>

        </div>
    </div>
</form>

<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>