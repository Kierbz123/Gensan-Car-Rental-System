<?php
/**
 * Schedule Edit
 * Path: modules/maintenance/schedule-edit.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Permission check BEFORE any output
$authUser->requirePermission('maintenance.update');

$db = Database::getInstance();
$scheduleId = (int) ($_GET['id'] ?? 0);

if (!$scheduleId) {
    redirect('modules/maintenance/', 'Schedule ID missing.', 'error');
}

$schedule = $db->fetchOne("SELECT * FROM maintenance_schedules WHERE schedule_id = ?", [$scheduleId]);
if (!$schedule) {
    redirect('modules/maintenance/', 'Schedule not found.', 'error');
}

// Valid ENUM values matching the DB schema exactly
$validServiceTypes = [
    'oil_change' => 'Oil Change',
    'tire_rotation' => 'Tire Rotation',
    'brake_inspection' => 'Brake Inspection',
    'engine_tuneup' => 'Engine Tune-up',
    'transmission_service' => 'Transmission Service',
    'aircon_cleaning' => 'Aircon Cleaning',
    'battery_check' => 'Battery Check',
    'coolant_flush' => 'Coolant Flush',
    'timing_belt' => 'Timing Belt',
    'general_checkup' => 'General Checkup',
    'others' => 'Others',
];

$validStatuses = ['active', 'paused', 'completed', 'overdue', 'scheduled', 'in_progress'];

$errors = [];

// Generate CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid security token. Please try again.';
        header('Location: schedule-edit.php?id=' . $scheduleId);
        exit;
    }

    $service_type = trim($_POST['service_type'] ?? '');
    $next_due_date = trim($_POST['next_due_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Server-side validation
    if (!array_key_exists($service_type, $validServiceTypes)) {
        $errors[] = 'Invalid service type selected.';
    }
    if ($next_due_date === '' || !strtotime($next_due_date)) {
        $errors[] = 'A valid Next Due Date is required.';
    }
    if (!in_array($status, $validStatuses, true)) {
        $errors[] = 'Invalid status selected.';
    }

    if (empty($errors)) {
        try {
            $db->execute(
                "UPDATE maintenance_schedules
                 SET service_type = ?, next_due_date = ?, status = ?, notes = ?
                 WHERE schedule_id = ?",
                [$service_type, $next_due_date, $status, $notes ?: null, $scheduleId]
            );

            $_SESSION['success_message'] = 'Schedule updated successfully.';
            header('Location: service-view.php?id=' . $scheduleId);
            exit;

        } catch (Exception $e) {
            error_log('schedule-edit.php UPDATE error: ' . $e->getMessage());
            $errors[] = 'A database error occurred. Please try again.';
        }
    }

    // Merge validated POST back for repopulation
    $schedule['service_type'] = $service_type;
    $schedule['next_due_date'] = $next_due_date;
    $schedule['status'] = $status;
    $schedule['notes'] = $notes;
}

$pageTitle = 'Edit Maintenance Schedule';
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Edit Maintenance Schedule</h1>
        <p>Modify service parameters for vehicle
            <strong><?= htmlspecialchars($schedule['vehicle_id']) ?></strong>.
        </p>
    </div>
    <div class="page-actions">
        <a href="service-view.php?id=<?= $scheduleId ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Detail
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
                <i data-lucide="edit-3"
                    style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>
                Schedule Parameters
            </h2>
        </div>
        <div class="card-body">

            <!-- Row 1: Service Type + Status -->
            <div class="form-row" style="margin-bottom:1.5rem;">
                <div class="form-group">
                    <label>Service Type <span style="color:var(--danger)">*</span></label>
                    <select name="service_type" class="form-control" required>
                        <?php foreach ($validServiceTypes as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= $schedule['service_type'] === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status <span style="color:var(--danger)">*</span></label>
                    <select name="status" class="form-control" required>
                        <?php foreach ($validStatuses as $stat): ?>
                            <option value="<?= $stat ?>" <?= $schedule['status'] === $stat ? 'selected' : '' ?>>
                                <?= strtoupper(str_replace('_', ' ', $stat)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Next Due Date -->
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>Next Due Date <span style="color:var(--danger)">*</span></label>
                <input type="date" name="next_due_date" class="form-control" style="max-width:300px;" required
                    value="<?= htmlspecialchars($schedule['next_due_date'] ?? '') ?>">
            </div>

            <!-- Notes -->
            <div class="form-group" style="margin-bottom:2rem;">
                <label>Notes / Remarks</label>
                <textarea name="notes" class="form-control" rows="4"
                    style="resize:none;"><?= htmlspecialchars($schedule['notes'] ?? '') ?></textarea>
            </div>

            <!-- Actions -->
            <div style="display:flex; gap:1rem;">
                <button type="submit" class="btn btn-primary" style="padding:0.8rem 2rem;">
                    <i data-lucide="save" style="width:16px;height:16px;"></i> Save Changes
                </button>
                <a href="service-view.php?id=<?= $scheduleId ?>" class="btn btn-ghost"
                    style="padding:0.8rem 2rem;">Cancel</a>
            </div>

        </div>
    </div>
</form>

<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>