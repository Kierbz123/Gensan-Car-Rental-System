<?php
/**
 * Edit Vehicle Page
 */

require_once '../../includes/session-manager.php';

// ─── All redirects must happen BEFORE any HTML output ───────────────────────
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
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }

        $action = $_POST['action'] ?? 'update';

        if ($action === 'delete') {
            $authUser->requirePermission('vehicles.delete');
            $vehicle->delete($vehicleId, $authUser->getId(), 'Deleted via edit form');
            $_SESSION['success_message'] = 'Vehicle deleted successfully!';
            redirect('modules/asset-tracking/index.php');
        } else {
            $data = $_POST;
            if (isset($_FILES['primary_photo']) && $_FILES['primary_photo']['tmp_name']) {
                $data['primary_photo'] = $_FILES['primary_photo'];
            }

            $vehicle->update($vehicleId, $data, $authUser->getId());

            if (isset($data['new_status']) && $data['new_status'] !== $vehicleData['current_status']) {
                $vehicle->updateStatus(
                    $vehicleId,
                    $data['new_status'],
                    $authUser->getId(),
                    $data['status_location'] ?? null,
                    $data['status_mileage'] ?? null,
                    $data['status_reason'] ?? null
                );
            }

            $_SESSION['success_message'] = 'Vehicle updated successfully!';
            redirect('modules/asset-tracking/vehicle-details.php?id=' . urlencode($vehicleId));
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// ─── Now safe to output HTML ─────────────────────────────────────────────────
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT * FROM vehicle_categories WHERE is_active = TRUE ORDER BY display_order");
$validTransitions = $vehicle->getValidStatusTransitions($vehicleData['current_status']);

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Edit Vehicle</h1>
        <p>Modify fleet asset <?= htmlspecialchars($vehicleData['vehicle_id']) ?> —
            <?= htmlspecialchars($vehicleData['brand'] . ' ' . $vehicleData['model']) ?></p>
    </div>
    <div class="page-actions">
        <a href="vehicle-details.php?id=<?= urlencode($vehicleId) ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Vehicle
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="card" style="margin-bottom: var(--space-8); background: var(--danger-light); border-color: var(--danger);">
        <div class="card-body">
            <h5
                style="margin-bottom: var(--space-2); font-weight: 700; display: flex; align-items: center; gap: var(--space-2); color: var(--danger);">
                <i data-lucide="alert-circle" style="width:18px;height:18px;"></i> Error
            </h5>
            <ul style="margin: 0; padding-left: var(--space-6);">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="vehicle-edit-grid">
        <div style="display: flex; flex-direction: column; gap: var(--space-6);">
            <!-- Vehicle Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Vehicle Information</h2>
                </div>
                <div class="card-body">
                    <div class="form-row form-row--two">
                        <div class="form-group">
                            <label>Vehicle ID</label>
                            <input type="text" class="form-control"
                                value="<?= htmlspecialchars($vehicleData['vehicle_id']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="plate_number">Plate Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="plate_number" name="plate_number"
                                value="<?= htmlspecialchars($vehicleData['plate_number']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row form-row--three">
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select class="form-control" id="category_id" name="category_id">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"
                                        <?= $vehicleData['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="brand">Brand <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="brand" name="brand"
                                value="<?= htmlspecialchars($vehicleData['brand']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="model">Model <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="model" name="model"
                                value="<?= htmlspecialchars($vehicleData['model']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row form-row--four">
                        <div class="form-group">
                            <label for="year_model">Year Model <span class="text-danger">*</span></label>
                            <select class="form-control" id="year_model" name="year_model" required>
                                <?php for ($y = date('Y') + 1; $y >= 1990; $y--): ?>
                                    <option value="<?= $y ?>" <?= $vehicleData['year_model'] == $y ? 'selected' : '' ?>>
                                        <?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="color">Color <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="color" name="color"
                                value="<?= htmlspecialchars($vehicleData['color']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="fuel_type">Fuel Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="fuel_type" name="fuel_type" required>
                                <option value="gasoline" <?= $vehicleData['fuel_type'] == 'gasoline' ? 'selected' : '' ?>>
                                    Gasoline</option>
                                <option value="diesel" <?= $vehicleData['fuel_type'] == 'diesel' ? 'selected' : '' ?>>
                                    Diesel</option>
                                <option value="hybrid" <?= $vehicleData['fuel_type'] == 'hybrid' ? 'selected' : '' ?>>
                                    Hybrid</option>
                                <option value="electric" <?= $vehicleData['fuel_type'] == 'electric' ? 'selected' : '' ?>>
                                    Electric</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="transmission">Transmission <span class="text-danger">*</span></label>
                            <select class="form-control" id="transmission" name="transmission" required>
                                <option value="manual" <?= $vehicleData['transmission'] == 'manual' ? 'selected' : '' ?>>
                                    Manual</option>
                                <option value="automatic" <?= $vehicleData['transmission'] == 'automatic' ? 'selected' : '' ?>>Automatic</option>
                                <option value="cvt" <?= $vehicleData['transmission'] == 'cvt' ? 'selected' : '' ?>>CVT
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row form-row--two">
                        <div class="form-group">
                            <label for="seating_capacity">Seating Capacity</label>
                            <input type="number" class="form-control" id="seating_capacity" name="seating_capacity"
                                value="<?= $vehicleData['seating_capacity'] ?>" min="1">
                        </div>
                        <div class="form-group">
                            <label for="engine_number">Engine Number</label>
                            <input type="text" class="form-control" id="engine_number" name="engine_number"
                                value="<?= htmlspecialchars($vehicleData['engine_number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="chassis_number">Chassis Number</label>
                            <input type="text" class="form-control" id="chassis_number" name="chassis_number"
                                value="<?= htmlspecialchars($vehicleData['chassis_number'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rental Rates -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Rental Rates</h2>
                </div>
                <div class="card-body">
                    <div class="form-row form-row--two">
                        <div class="form-group">
                            <label for="daily_rental_rate">Daily Rate <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                </div>
                                <input type="number" class="form-control" id="daily_rental_rate"
                                    name="daily_rental_rate" value="<?= $vehicleData['daily_rental_rate'] ?>"
                                    step="0.01" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="weekly_rental_rate">Weekly Rate</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                </div>
                                <input type="number" class="form-control" id="weekly_rental_rate"
                                    name="weekly_rental_rate" value="<?= $vehicleData['weekly_rental_rate'] ?? '' ?>"
                                    step="0.01">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="monthly_rental_rate">Monthly Rate</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                </div>
                                <input type="number" class="form-control" id="monthly_rental_rate"
                                    name="monthly_rental_rate" value="<?= $vehicleData['monthly_rental_rate'] ?? '' ?>"
                                    step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Management -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Status Management</h2>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Current Status</label>
                        <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-top:.5rem;">
                            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                                <input type="radio" name="new_status" value="<?= htmlspecialchars($vehicleData['current_status']) ?>" checked onchange="onStatusChange()">
                                <span class="badge badge-<?= htmlspecialchars($VEHICLE_STATUS_COLORS[$vehicleData['current_status']] ?? 'secondary') ?>">
                                    <?= htmlspecialchars($VEHICLE_STATUS_LABELS[$vehicleData['current_status']] ?? $vehicleData['current_status']) ?> (Current)
                                </span>
                            </label>
                            <?php foreach ($validTransitions as $status): ?>
                                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                                    <input type="radio" name="new_status" value="<?= htmlspecialchars($status) ?>" onchange="onStatusChange()">
                                    <span class="badge badge-<?= htmlspecialchars($VEHICLE_STATUS_COLORS[$status] ?? 'secondary') ?>">
                                        <?= htmlspecialchars($VEHICLE_STATUS_LABELS[$status] ?? $status) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Additional Details (Hidden by default) -->
            <div class="card" id="statusDetailsCard" style="display:none; border-top: 3px solid var(--warning); margin-top: var(--space-6);">
                <div class="card-header">
                    <h2 class="card-title"><i data-lucide="info" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--warning)"></i> Status Change Details</h2>
                </div>
                <div class="card-body">
                    <div class="form-row form-row--two">
                        <div class="form-group">
                            <label for="status_location">New Location <span class="text-danger">*</span></label>
                            <select class="form-control" id="status_location" name="status_location">
                                <option value="main_office">Main Office</option>
                                <option value="service_center">Service Center</option>
                                <option value="cleaning_bay">Cleaning Bay</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status_mileage">Current Mileage <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="status_mileage" name="status_mileage" value="<?= htmlspecialchars((int)($vehicleData['mileage'] ?? 0)) ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="status_reason">Reason for Status Change <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="status_reason" name="status_reason" placeholder="e.g. Scheduled PMS, Body work, Interior detailing...">
                    </div>
                </div>
            </div>

        </div>

        <div style="display: flex; flex-direction: column; gap: var(--space-6);">
            <!-- Current Photo — 3D Widget -->
            <div class="card" id="vehiclePhotoCard">
                <div class="card-header">
                    <h2 class="card-title">Vehicle Photo</h2>
                </div>
                <div class="card-body" style="padding-bottom:1rem;">
                    <style>
                        #vehicle3dStage{perspective:900px;width:100%;height:180px;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;}
                        #vehicle3dCard{width:220px;height:138px;border-radius:14px;background:linear-gradient(135deg,#1e293b,#334155);box-shadow:0 20px 60px rgba(0,0,0,.35),0 4px 12px rgba(0,0,0,.2);transform-style:preserve-3d;animation:spin3d 8s linear infinite;overflow:hidden;position:relative;}
                        #vehicle3dCard img{width:100%;height:100%;object-fit:cover;border-radius:14px;}
                        #vehicle3dCard:hover{animation-play-state:paused;}
                        #vehicle3dCard .car-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;}
                        #vehicle3dCard .car-placeholder svg{width:64px;height:64px;color:#94a3b8;}
                        #vehicle3dCard .car-placeholder span{font-size:.7rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;font-weight:700;}
                        @keyframes spin3d{0%{transform:rotateY(-25deg) rotateX(5deg)}50%{transform:rotateY(25deg) rotateX(-5deg)}100%{transform:rotateY(-25deg) rotateX(5deg)}}
                        #vehiclePhotoDropzone{border:2px dashed var(--border-color);border-radius:var(--radius-md);padding:1.25rem;text-align:center;cursor:pointer;transition:all .25s;background:var(--bg-muted);}
                        #vehiclePhotoDropzone:hover,#vehiclePhotoDropzone.drag-over{border-color:var(--primary);background:rgba(99,102,241,.06);box-shadow:0 0 0 4px rgba(99,102,241,.12);}
                        #vehiclePhotoDropzone .dz-label{font-size:.8rem;color:var(--text-secondary);font-weight:600;}
                        #vehiclePhotoDropzone .dz-hint{font-size:.7rem;color:var(--text-muted);margin-top:.25rem;}
                    </style>

                    <!-- 3D rotating stage -->
                    <div id="vehicle3dStage">
                        <div id="vehicle3dCard">
                            <?php if ($vehicleData['primary_photo_path']): ?>
                                <img src="<?= BASE_URL . $vehicleData['primary_photo_path'] ?>"
                                     alt="<?= htmlspecialchars($vehicleData['vehicle_id']) ?>">
                            <?php else: ?>
                                <div class="car-placeholder" id="carPlaceholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                                    <span>No Photo Yet</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Drop-zone for new/replacement photo -->
                    <div id="vehiclePhotoDropzone" onclick="document.getElementById('primary_photo').click()">
                        <div class="dz-label">
                            <i data-lucide="upload-cloud" style="width:20px;height:20px;vertical-align:-4px;margin-right:6px;color:var(--primary);"></i>
                            <?= $vehicleData['primary_photo_path'] ? 'Replace Photo — click or drag here' : 'Upload Photo — click or drag here' ?>
                        </div>
                        <div class="dz-hint">JPG, PNG, WebP &mdash; max 5 MB</div>
                    </div>
                    <input type="file" id="primary_photo" name="primary_photo" accept="image/*" style="display:none;" onchange="vehiclePhotoSelected(this)">
                    <p id="vehiclePhotoName" style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem;text-align:center;display:none;"></p>
                </div>
            </div>

            <!-- System Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">System Information</h2>
                </div>
                <div class="card-body">
                    <table class="detail-table">
                        <tr>
                            <th>Mileage</th>
                            <td><?= number_format((float) ($vehicleData['mileage'] ?? 0)) ?> km</td>
                        </tr>
                        <tr>
                            <th>Created</th>
                            <td><?= formatDateTime($vehicleData['created_at']) ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated</th>
                            <td><?= formatDateTime($vehicleData['updated_at']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Notes -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Notes</h2>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="notes" name="notes"
                        rows="5"><?= htmlspecialchars($vehicleData['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top: var(--space-8); display: flex; gap: var(--space-4); align-items: center;">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" style="width:16px;height:16px;"></i> Save Changes
        </button>
        <a href="vehicle-details.php?id=<?= urlencode($vehicleId) ?>" class="btn btn-secondary">Cancel</a>
        
        <?php if ($authUser->hasPermission('vehicles.delete')): ?>
            <button type="button" class="btn btn-danger" style="margin-left: auto;" onclick="confirmDelete()">
                <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Delete Vehicle
            </button>
        <?php endif; ?>
    </div>
</form>

<?php if ($authUser->hasPermission('vehicles.delete')): ?>
<form id="deleteForm" method="POST" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
</form>
<script>
function confirmDelete() {
    openGcrModal({
        title: 'Delete Vehicle',
        message: 'Are you sure you want to permanently delete this vehicle? This action <strong style="color:var(--danger)">cannot be undone</strong>.',
        variant: 'danger',
        confirmLabel: 'Yes, Delete',
        icon: 'trash-2',
        onConfirm: function() {
            document.getElementById('deleteForm').submit();
        }
    });
}
</script>
<?php endif; ?>

<script>
function vehiclePhotoSelected(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const card = document.getElementById('vehicle3dCard');
        card.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        card.style.animation = 'none';
        setTimeout(() => card.style.animation = 'spin3d 12s linear infinite', 600);
    };
    reader.readAsDataURL(file);
    const nameEl = document.getElementById('vehiclePhotoName');
    nameEl.textContent = '📎 ' + file.name;
    nameEl.style.display = 'block';
}
(function() {
    const dz = document.getElementById('vehiclePhotoDropzone');
    const inp = document.getElementById('primary_photo');
    if (!dz || !inp) return;
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
            const dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            inp.files = dt.files;
            vehiclePhotoSelected(inp);
        }
    });
})();
</script>

<script>
function onStatusChange() {
    const statusInputs = document.querySelectorAll('input[name="new_status"]');
    const detailsCard = document.getElementById('statusDetailsCard');
    const locInput = document.getElementById('status_location');
    const reasonInput = document.getElementById('status_reason');
    const mileageInput = document.getElementById('status_mileage');
    
    let isChanged = false;
    let selectedStatus = '';
    
    statusInputs.forEach(input => {
        if (input.checked) {
            selectedStatus = input.value;
            // The first radio button is always the current status in our layout
            isChanged = (input.value !== "<?= htmlspecialchars($vehicleData['current_status']) ?>");
        }
    });

    if (isChanged && selectedStatus !== 'available' && selectedStatus !== 'rented') {
        detailsCard.style.display = 'block';
        locInput.required = true;
        reasonInput.required = true;
        mileageInput.required = true;
    } else if (isChanged && selectedStatus === 'available') {
        detailsCard.style.display = 'block';
        locInput.value = 'main_office';
        locInput.required = true;
        reasonInput.required = false;
        mileageInput.required = true;
    } else {
        detailsCard.style.display = 'none';
        locInput.required = false;
        reasonInput.required = false;
        mileageInput.required = false;
    }
}
// Initialize on load just in case
document.addEventListener('DOMContentLoaded', onStatusChange);

// Real-time unique field validation
document.addEventListener('DOMContentLoaded', function() {
    const fieldsToCheck = ['plate_number', 'engine_number', 'chassis_number'];
    const excludeId = '<?= addslashes($vehicleId) ?>'; // Exclude current vehicle
    
    fieldsToCheck.forEach(fieldId => {
        const input = document.getElementById(fieldId);
        if (!input) return;
        
        let timeout = null;
        
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const value = this.value.trim();
            
            // Remove custom error message
            const existingMsg = input.closest('.form-group').querySelector('.dup-error-msg');
            if (existingMsg) existingMsg.remove();
            
            this.setCustomValidity('');
            this.classList.remove('is-invalid');
            this.style.borderColor = '';
            
            if (!value) return;
            
            timeout = setTimeout(() => {
                const url = `ajax/check-duplicate-vehicle.php?field=${fieldId}&value=${encodeURIComponent(value)}&exclude_id=${encodeURIComponent(excludeId)}`;
                
                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        if (data.exists) {
                            const fieldName = fieldId.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                            
                            const msg = document.createElement('div');
                            msg.className = 'dup-error-msg text-danger mt-1';
                            msg.style.fontSize = '0.875rem';
                            msg.style.color = 'var(--danger)';
                            msg.style.fontWeight = '500';
                            msg.style.position = 'absolute';
                            msg.style.top = '100%';
                            msg.style.left = '0';
                            msg.style.marginTop = '2px';
                            msg.style.width = '100%';
                            msg.innerHTML = `<i data-lucide="alert-circle" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"></i> ${fieldName} already registered!`;
                            
                            const formGroup = input.closest('.form-group');
                            formGroup.style.position = 'relative';
                            formGroup.appendChild(msg);
                            
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                            
                            input.setCustomValidity(`${fieldName} already exists.`);
                            input.classList.add('is-invalid');
                            input.style.borderColor = 'var(--danger)';
                        }
                    })
                    .catch(err => console.error(err));
            }, 600);
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>