<?php
// modules/drivers/driver-edit.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('drivers.update');

$driverId = (int) ($_GET['id'] ?? 0);
if (!$driverId) {
    redirect('modules/drivers/', 'Driver ID missing', 'error');
}

$driverObj = new Driver();
$rec = $driverObj->getById($driverId);
if (!$rec) {
    redirect('modules/drivers/', 'Driver not found', 'error');
}

$pageTitle = 'Edit Driver — ' . $rec['first_name'] . ' ' . $rec['last_name'];
$error = '';
$data = $rec; // pre-fill from DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'update';

        if ($action === 'delete') {
            $authUser->requirePermission('drivers.delete');
            try {
                $driverObj->delete($driverId, $authUser->getId());
                $_SESSION['success_message'] = 'Driver deleted successfully.';
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        } else {
            $data = array_merge($data, [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'license_number' => trim($_POST['license_number'] ?? ''),
                'license_expiry' => trim($_POST['license_expiry'] ?? ''),
                'license_type' => $_POST['license_type'] ?? 'professional',
                'status' => $_POST['status'] ?? $rec['status'],
                'notes' => trim($_POST['notes'] ?? ''),
            ]);

            if (empty($data['first_name']) || empty($data['last_name']))
                $error = 'Name is required.';
            elseif (empty($data['phone']))
                $error = 'Phone is required.';
            elseif (empty($data['license_number']))
                $error = 'License number is required.';
            elseif (empty($data['license_expiry']))
                $error = 'License expiry is required.';

            if (!$error) {
                try {
                    $driverObj->update($driverId, array_merge($data, $_FILES), $authUser->getId());
                    $_SESSION['success_message'] = 'Driver updated successfully.';
                    header("Location: driver-view.php?id={$driverId}");
                    exit;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="pencil"
                style="width:22px;height:22px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>Edit Driver
        </h1>
        <p>
            <?= htmlspecialchars($rec['employee_code']) ?> —
            <?= htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="driver-view.php?id=<?= $driverId ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div
        style="margin-bottom:1.5rem;padding:1rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-weight:500;display:flex;align-items:center;gap:.5rem;">
        <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" style="max-width:720px;">
    <?= csrfField() ?>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="user"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Personal
                Information</h2>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required
                        value="<?= htmlspecialchars($data['first_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required
                        value="<?= htmlspecialchars($data['last_name']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" required
                        value="<?= htmlspecialchars($data['phone']) ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                        value="<?= htmlspecialchars($data['email'] ?? '') ?>">
                </div>
            </div>

            <!-- Profile Photo Upload -->
            <div style="margin-top:1.5rem;padding:1.5rem;border-radius:12px;background:linear-gradient(to right, var(--bg-muted), transparent);border:1px solid var(--border-color);">
                <label style="display:block;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text-color);margin-bottom:1rem;">Profile Photo</label>
                <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                    <!-- Current / live-preview avatar -->
                    <div id="driverPhotoPreview"
                        style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;flex-shrink:0;overflow:hidden;border:4px solid #fff;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);transition:transform .3s ease, box-shadow .3s ease;"
                        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 15px 35px -5px rgba(0,0,0,0.2)';"
                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 10px 25px -5px rgba(0,0,0,0.15)';"
                        title="Photo preview">
                        <?php if (!empty($rec['profile_photo_path'])): ?>
                            <img src="<?= BASE_URL . ltrim($rec['profile_photo_path'], '/') ?>"
                                style="width:100%;height:100%;object-fit:cover;" alt="Profile">
                        <?php else: ?>
                            <span><?= strtoupper(substr($rec['first_name'], 0, 1) . substr($rec['last_name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;display:flex;flex-direction:column;gap:.75rem;min-width:260px;">
                        <p style="font-size:.8rem;color:var(--text-muted);margin:0;line-height:1.4;">Upload a clear, recent photo of the driver. This will be used for identification purposes across the system.</p>
                        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                            <div style="position:relative;flex:1;min-width:200px;">
                                <input type="file" id="profile_photo" name="profile_photo" accept="image/*"
                                    style="width:100%;padding:.65rem 1rem;border:2px dashed var(--border-color);border-radius:var(--radius-md);font-size:.875rem;cursor:pointer;background:var(--bg-card);transition:all 0.2s;"
                                    onmouseover="this.style.borderColor='var(--primary)';this.style.background='var(--primary-50)';"
                                    onmouseout="this.style.borderColor='var(--border-color)';this.style.background='var(--bg-card)';"
                                    onchange="previewDriverPhoto(this)">
                            </div>
                            <button type="button" onclick="openCamera('profile_photo', 'Take Profile Photo')"
                                class="btn btn-secondary" style="padding:.65rem 1.25rem;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;gap:8px;font-weight:600;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);transition:all 0.2s;flex-shrink:0;" title="Use Camera"
                                onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 12px -2px rgba(0,0,0,0.1)';"
                                onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';">
                                <i data-lucide="camera" style="width:18px;height:18px;color:var(--primary);"></i>
                                <span>Camera</span>
                            </button>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-muted);font-weight:500;">
                        <?php if (!empty($rec['profile_photo_path'])): ?>
                            <span style="color:var(--text-success);display:inline-flex;align-items:center;gap:4px;">
                                <i data-lucide="check" style="width:14px;height:14px;"></i> Photo currently on file
                            </span>
                        <?php else: ?>
                            <span><i data-lucide="info" style="width:14px;height:14px;vertical-align:-2px;margin-right:2px;"></i> JPG, PNG, WebP — max 5 MB</span>
                        <?php endif; ?>
                        </div>
                        <div id="cam_container_profile_photo" style="display:none; padding:0; border:none; box-shadow:none; background:transparent; align-items:flex-start; margin-top:0.25rem; width:100%;">
                            <img id="cam_thumb_profile_photo" style="display:none;" alt="cam">
                            <div class="cam-success-badge" style="width:100%; margin-top:0;">
                                <div class="cam-success-badge-title">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 6 9 17l-5-5"></path>
                                    </svg>
                                    Photo Captured
                                </div>
                                <div class="cam-success-badge-text">Save changes to confirm upload.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="id-card"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>License
                & Status</h2>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="license_number">License Number <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="license_number" name="license_number" class="form-control" required
                        value="<?= htmlspecialchars($data['license_number']) ?>">
                </div>
                <div class="form-group">
                    <label for="license_expiry">Expiry Date <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="license_expiry" name="license_expiry" class="form-control" required
                        value="<?= htmlspecialchars($data['license_expiry']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="license_type">License Type</label>
                    <select id="license_type" name="license_type" class="form-control">
                        <option value="professional" <?= ($data['license_type'] ?? '') === 'professional' ? 'selected' : '' ?>>Professional</option>
                        <option value="non_professional" <?= ($data['license_type'] ?? '') === 'non_professional' ? 'selected' : '' ?>>Non-Professional</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Current Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="available" <?= ($data['status'] ?? '') === 'available' ? 'selected' : '' ?>>
                            Available</option>
                        <option value="on_duty" <?= ($data['status'] ?? '') === 'on_duty' ? 'selected' : '' ?>>On Duty
                        </option>
                        <option value="off_duty" <?= ($data['status'] ?? '') === 'off_duty' ? 'selected' : '' ?>>Off Duty
                        </option>
                        <option value="suspended" <?= ($data['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>
                            Suspended</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="file-text"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Notes
            </h2>
        </div>
        <div class="card-body">
            <div class="form-group" style="margin-bottom:0;">
                <textarea id="notes" name="notes" class="form-control" rows="3"
                    placeholder="Any additional notes about this driver…"><?= htmlspecialchars($data['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" style="width:16px;height:16px;"></i> Save Changes
        </button>
        <a href="driver-view.php?id=<?= $driverId ?>" class="btn btn-secondary">Cancel</a>
        <?php if ($authUser->hasPermission('drivers.delete')): ?>
        <button type="button" class="btn btn-danger" style="margin-left:auto;" onclick="confirmDelete()">
            <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Delete Driver
        </button>
        <?php endif; ?>
    </div>
</form>

<?php if ($authUser->hasPermission('drivers.delete')): ?>
<form id="deleteForm" method="POST" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
</form>
<script>
function confirmDelete() {
    openGcrModal({
        title: 'Delete Driver',
        message: 'Are you sure you want to permanently delete this driver? This action <strong style="color:var(--danger)">cannot be undone</strong>.',
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
lucide.createIcons();
function previewDriverPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('driverPhotoPreview');
        preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
<?php require_once '../../includes/camera-scanner.php'; ?>
<?php require_once '../../includes/footer.php'; ?>