<?php
// modules/drivers/driver-add.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$pageTitle = 'Add Driver';
$authUser->requirePermission('drivers.create');

$error = '';
$data = [
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'email' => '',
    'license_number' => '',
    'license_expiry' => '',
    'license_type' => 'professional',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $data = array_merge($data, [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'license_number' => trim($_POST['license_number'] ?? ''),
            'license_expiry' => trim($_POST['license_expiry'] ?? ''),
            'license_type' => $_POST['license_type'] ?? 'professional',
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        if (empty($data['first_name']) || empty($data['last_name']))
            $error = 'First and last name are required.';
        elseif (empty($data['phone']))
            $error = 'Phone number is required.';
        elseif (empty($data['license_number']))
            $error = 'License number is required.';
        elseif (empty($data['license_expiry']))
            $error = 'License expiry date is required.';

        if (!$error) {
            try {
                $driver = new Driver();
                $driverId = $driver->create(array_merge($data, $_FILES), $authUser->getId());
                $_SESSION['success_message'] = 'Driver added successfully.';
                header("Location: driver-view.php?id={$driverId}");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="user-plus"
                style="width:24px;height:24px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>Add Driver
        </h1>
        <p>Register a new licensed chauffeur / driver.</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Drivers
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

<form method="POST" id="driverAddForm" enctype="multipart/form-data" style="max-width:720px;">
    <?= csrfField() ?>

    <!-- Personal Info -->
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
                        value="<?= htmlspecialchars($data['first_name']) ?>" placeholder="e.g. Ramon">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required
                        value="<?= htmlspecialchars($data['last_name']) ?>" placeholder="e.g. Dela Cruz">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" required
                        value="<?= htmlspecialchars($data['phone']) ?>" placeholder="09XXXXXXXXX">
                </div>
                <div class="form-group">
                    <label for="email">Email (optional)</label>
                    <input type="email" id="email" name="email" class="form-control"
                        value="<?= htmlspecialchars($data['email']) ?>" placeholder="driver@example.com">
                </div>
            </div>

            <!-- Profile Photo Upload -->
            <div style="margin-top:1.5rem;padding:1.5rem;border-radius:12px;background:linear-gradient(to right, var(--bg-muted), transparent);border:1px solid var(--border-color);">
                <label style="display:block;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text-color);margin-bottom:1rem;">Profile Photo</label>
                <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                    <!-- Live preview avatar -->
                    <div id="driverPhotoPreview"
                        style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;flex-shrink:0;overflow:hidden;border:4px solid #fff;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);transition:transform .3s ease, box-shadow .3s ease;"
                        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 15px 35px -5px rgba(0,0,0,0.2)';"
                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 10px 25px -5px rgba(0,0,0,0.15)';"
                        title="Photo preview">
                        <i data-lucide="user" style="width:44px;height:44px;opacity:0.9;"></i>
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
                            <i data-lucide="info" style="width:14px;height:14px;"></i> JPG, PNG, WebP — max 5 MB
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
                                <div class="cam-success-badge-text">Save form to confirm upload.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- License Info -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="id-card"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Driver
                License</h2>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="license_number">License Number <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="license_number" name="license_number" class="form-control" required
                        value="<?= htmlspecialchars($data['license_number']) ?>" placeholder="A01-23-456789">
                </div>
                <div class="form-group">
                    <label for="license_expiry">Expiry Date <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="license_expiry" name="license_expiry" class="form-control" required
                        value="<?= htmlspecialchars($data['license_expiry']) ?>" min="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="license_type">License Type</label>
                <select id="license_type" name="license_type" class="form-control">
                    <option value="professional" <?= $data['license_type'] === 'professional' ? 'selected' : '' ?>
                        >Professional</option>
                    <option value="non_professional" <?= $data['license_type'] === 'non_professional' ? 'selected' : '' ?>
                        >Non-Professional</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="file-text"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Notes
            </h2>
        </div>
        <div class="card-body">
            <div class="form-group" style="margin-bottom:0;">
                <textarea id="notes" name="notes" class="form-control" rows="3"
                    placeholder="Any additional notes about this driver…"><?= htmlspecialchars($data['notes']) ?></textarea>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" style="width:16px;height:16px;"></i> Save Driver
        </button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
lucide.createIcons();
function previewDriverPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('driverPhotoPreview');
        preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">` ;
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
<?php require_once '../../includes/camera-scanner.php'; ?>
<?php require_once '../../includes/footer.php'; ?>