<?php
/**
 * Edit Customer Profile
 * Path: modules/customers/customer-edit.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('customers.update');

$db = Database::getInstance();
$customerId = (int) ($_GET['id'] ?? 0);
if (!$customerId) {
    redirect('modules/customers/', 'Customer ID missing', 'error');
}

$customer = $db->fetchOne("SELECT * FROM customers WHERE customer_id = ? AND deleted_at IS NULL", [$customerId]);
if (!$customer) {
    redirect('modules/customers/', 'Customer not found', 'error');
}

$errors = [];
$data = !empty($_POST) ? $_POST : $customer; // Start with existing data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? 'update';
        
        if ($action === 'delete') {
            $authUser->requirePermission('customers.delete');
            try {
                $cust = new Customer($customerId);
                $cust->delete($customerId, $authUser->getId());
                $_SESSION['success_message'] = 'Customer profile deleted successfully.';
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            foreach (['first_name', 'last_name', 'phone_primary'] as $f) {
                if (empty(trim($_POST[$f] ?? '')))
                    $errors[] = ucfirst(str_replace('_', ' ', $f)) . ' is required.';
            }
            if (empty($errors)) {
                try {
                    $cust = new Customer($customerId);
                    $formData = array_merge($_POST, $_FILES);
                    $cust->update($customerId, $formData, $authUser->getId());
                    $_SESSION['success_message'] = 'Customer profile updated.';
                    header('Location: customer-view.php?id=' . $customerId);
                    exit;
                } catch (Exception $e) {
                    $errors[] = DEBUG_MODE ? $e->getMessage() : 'Update failed.';
                }
            }
        }
    }
}

$pageTitle = 'Edit — ' . $customer['first_name'] . ' ' . $customer['last_name'];
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="user"
                style="width:24px;height:24px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>Edit Customer
        </h1>
        <p>Update identity profile for <strong style="color:var(--text-main);"><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></strong> (<?= htmlspecialchars($customer['customer_code'] ?? '') ?>).</p>
    </div>
    <div class="page-actions">
        <a href="customer-view.php?id=<?= $customerId ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Profile
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div
        style="margin-bottom:1.5rem;padding:1rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-weight:500;display:flex;align-items:center;gap:.5rem;">
        <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
        <ul style="margin:0;padding-left:1.5rem;font-size:0.9rem;">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    <?= csrfField() ?>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        
        <!-- Column 1: Personal, Contact, Emergency -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Personal Info -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i data-lucide="user"
                            style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>Personal
                        Information</h2>
                </div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div class="form-row">
                        <div class="form-group" style="margin: 0;">
                            <label for="first_name">First Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required
                                value="<?= htmlspecialchars($data['first_name'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="last_name">Last Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required
                                value="<?= htmlspecialchars($data['last_name'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control"
                            value="<?= htmlspecialchars($data['middle_name'] ?? '') ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="margin: 0;">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
                                value="<?= htmlspecialchars($data['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-control">
                                <option value="">— Select —</option>
                                <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($data['gender'] ?? '') === $v ? 'selected' : '' ?>>
                                        <?= $l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="margin: 0;">
                            <label for="customer_type">Customer Type <span style="color:var(--danger)">*</span></label>
                            <select id="customer_type" name="customer_type" class="form-control" required>
                                <?php foreach (['walk_in' => 'Walk-in', 'online' => 'Online', 'corporate' => 'Corporate', 'repeat' => 'Repeat', 'referral' => 'Referral'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($data['customer_type'] ?? '') === $v ? 'selected' : '' ?>>
                                        <?= $l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="is_blacklisted">Account Standing</label>
                            <select id="is_blacklisted" name="is_blacklisted" class="form-control">
                                <option value="0" <?= empty($data['is_blacklisted']) ? 'selected' : '' ?>>Good Standing</option>
                                <option value="1" <?= !empty($data['is_blacklisted']) ? 'selected' : '' ?>>Blacklisted</option>
                            </select>
                        </div>
                    </div>

                    <!-- Profile Photo Upload -->
                    <div style="padding-top: 0.5rem; border-top: 1px dashed var(--border-color); margin-top: 0.5rem;">
                        <label style="display:block;font-size:0.8125rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.75rem;">Update Profile Picture</label>
                        <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
                            <!-- Live preview avatar -->
                            <div id="customerPhotoPreview"
                                style="width:72px;height:72px;border-radius:50%;background:var(--primary-100);color:var(--primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;border:1px solid var(--border-color);"
                                title="Photo preview">
                                <?php if (!empty($customer['profile_picture_path'])): ?>
                                    <img src="<?= BASE_URL . ltrim($customer['profile_picture_path'], '/') ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <span style="font-size:1.5rem;font-weight:bold;"><?= strtoupper(substr($data['first_name'] ?? '', 0, 1) . substr($data['last_name'] ?? '', 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;display:flex;flex-direction:column;gap:0.5rem;min-width:260px;">
                                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                                    <div style="flex:1;min-width:200px;">
                                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                                            class="form-control" style="padding:0.4rem 0.5rem; font-size: 0.85rem;"
                                            onchange="previewCustomerPhoto(this)">
                                    </div>
                                    <button type="button" onclick="openCamera('profile_picture', 'Take Profile Picture')"
                                        class="btn btn-secondary" style="padding:0.4rem 0.85rem;font-size:0.85rem;display:flex;align-items:center;gap:6px;flex-shrink:0;" title="Use Camera">
                                        <i data-lucide="camera" style="width:16px;height:16px;"></i>
                                        <span>Camera</span>
                                    </button>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:4px;">
                                    <i data-lucide="info" style="width:12px;height:12px;"></i> JPG, PNG, WebP — max 5 MB
                                </div>
                                
                                <div id="cam_container_profile_picture" style="display:none; padding:0; border:none; box-shadow:none; background:transparent; align-items:flex-start; margin-top:0.25rem; width:100%;">
                                    <img id="cam_thumb_profile_picture" style="display:none;" alt="cam">
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

            <!-- Contact Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i data-lucide="phone"
                            style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--success)"></i>Contact
                        Information</h2>
                </div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div class="form-row">
                        <div class="form-group" style="margin: 0;">
                            <label for="phone_primary">Primary Phone <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="phone_primary" name="phone_primary" class="form-control" required
                                value="<?= htmlspecialchars($data['phone_primary'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="phone_secondary">Secondary Phone</label>
                            <input type="text" id="phone_secondary" name="phone_secondary" class="form-control"
                                value="<?= htmlspecialchars($data['phone_secondary'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($data['email'] ?? '') ?>">
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2" class="form-control" style="resize: none;"><?= htmlspecialchars($data['address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="margin: 0;">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" class="form-control"
                                value="<?= htmlspecialchars($data['city'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="province">Province</label>
                            <input type="text" id="province" name="province" class="form-control"
                                value="<?= htmlspecialchars($data['province'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i data-lucide="heart-pulse"
                            style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--danger)"></i>Emergency
                        Contact</h2>
                </div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div class="form-group" style="margin: 0;">
                        <label for="emergency_name">Full Name</label>
                        <input type="text" id="emergency_name" name="emergency_name" class="form-control"
                            value="<?= htmlspecialchars($data['emergency_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="margin: 0;">
                            <label for="emergency_phone">Phone</label>
                            <input type="text" id="emergency_phone" name="emergency_phone" class="form-control"
                                value="<?= htmlspecialchars($data['emergency_phone'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="emergency_relationship">Relationship</label>
                            <input type="text" id="emergency_relationship" name="emergency_relationship" class="form-control"
                                value="<?= htmlspecialchars($data['emergency_relationship'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Column 2: ID Verification, Notes -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- ID Verification -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i data-lucide="shield-check"
                            style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--warning)"></i>ID
                        Verification (Optional Update)</h2>
                </div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div class="form-row">
                        <div class="form-group" style="margin: 0;">
                            <label for="id_type">ID Type <span style="color:var(--danger)">*</span></label>
                            <select id="id_type" name="id_type" class="form-control" required>
                                <?php foreach (['drivers_license' => "Driver's License", 'passport' => 'Passport', 'national_id' => 'National ID', 'company_id' => 'Company ID'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($data['id_type'] ?? '') === $v ? 'selected' : '' ?>>
                                        <?= $l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="id_number">ID Number</label>
                            <input type="text" id="id_number" name="id_number" class="form-control"
                                value="<?= htmlspecialchars($data['id_number'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label for="id_expiry_date">ID Expiry</label>
                        <input type="date" id="id_expiry_date" name="id_expiry_date" class="form-control"
                            value="<?= htmlspecialchars($data['id_expiry_date'] ?? '') ?>">
                    </div>

                    <!-- ID Front Photo Upload -->
                    <div style="padding-top: 0.5rem; border-top: 1px dashed var(--border-color); margin-top: 0.5rem;">
                        <label style="display:block;font-size:0.8125rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.75rem;">Update ID Photo (Front)</label>
                        <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
                            <div id="idFrontPreview"
                                style="width:100px;height:72px;border-radius:6px;background:var(--primary-100);color:var(--primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;border:1px solid var(--border-color);"
                                title="ID Front preview">
                                <?php if (!empty($customer['id_photo_front_path'])): ?>
                                    <?php if(str_ends_with(strtolower($customer['id_photo_front_path']), '.pdf')): ?>
                                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--primary-600);"><i data-lucide="file-text" style="width:24px;height:24px;margin-bottom:2px;"></i><span style="font-size:0.5rem;font-weight:bold;">PDF File</span></div>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars('../../' . $customer['id_photo_front_path']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i data-lucide="credit-card" style="width:32px;height:32px;opacity:0.5;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;display:flex;flex-direction:column;gap:0.5rem;min-width:200px;">
                                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                                    <div style="flex:1;min-width:160px;">
                                        <input type="file" id="id_photo_front" name="id_photo_front" accept="image/*,application/pdf"
                                            class="form-control" style="padding:0.4rem 0.5rem; font-size: 0.85rem;"
                                            onchange="previewIDPhoto(this, 'idFrontPreview')">
                                    </div>
                                    <button type="button" onclick="openCamera('id_photo_front', 'Scan Front ID')"
                                        class="btn btn-secondary" style="padding:0.4rem 0.85rem;font-size:0.85rem;display:flex;align-items:center;gap:6px;flex-shrink:0;" title="Use Camera">
                                        <i data-lucide="camera" style="width:16px;height:16px;"></i>
                                        <span>Camera</span>
                                    </button>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:4px;">
                                    <i data-lucide="info" style="width:12px;height:12px;"></i> JPG, PNG, WebP, PDF — max 5 MB
                                </div>
                                <div id="cam_container_id_photo_front" style="display:none; padding:0; border:none; box-shadow:none; background:transparent; align-items:flex-start; margin-top:0.25rem; width:100%;">
                                    <img id="cam_thumb_id_photo_front" style="display:none;" alt="cam">
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

                    <!-- ID Back Photo Upload -->
                    <div style="padding-top: 0.5rem; border-top: 1px dashed var(--border-color); margin-top: 0.5rem;">
                        <label style="display:block;font-size:0.8125rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.75rem;">Update ID Photo (Back)</label>
                        <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
                            <div id="idBackPreview"
                                style="width:100px;height:72px;border-radius:6px;background:var(--primary-100);color:var(--primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;border:1px solid var(--border-color);"
                                title="ID Back preview">
                                <?php if (!empty($customer['id_photo_back_path'])): ?>
                                    <?php if(str_ends_with(strtolower($customer['id_photo_back_path']), '.pdf')): ?>
                                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--primary-600);"><i data-lucide="file-text" style="width:24px;height:24px;margin-bottom:2px;"></i><span style="font-size:0.5rem;font-weight:bold;">PDF File</span></div>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars('../../' . $customer['id_photo_back_path']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i data-lucide="scan-line" style="width:32px;height:32px;opacity:0.5;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;display:flex;flex-direction:column;gap:0.5rem;min-width:200px;">
                                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                                    <div style="flex:1;min-width:160px;">
                                        <input type="file" id="id_photo_back" name="id_photo_back" accept="image/*,application/pdf"
                                            class="form-control" style="padding:0.4rem 0.5rem; font-size: 0.85rem;"
                                            onchange="previewIDPhoto(this, 'idBackPreview')">
                                    </div>
                                    <button type="button" onclick="openCamera('id_photo_back', 'Scan Back ID')"
                                        class="btn btn-secondary" style="padding:0.4rem 0.85rem;font-size:0.85rem;display:flex;align-items:center;gap:6px;flex-shrink:0;" title="Use Camera">
                                        <i data-lucide="camera" style="width:16px;height:16px;"></i>
                                        <span>Camera</span>
                                    </button>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:4px;">
                                    <i data-lucide="info" style="width:12px;height:12px;"></i> JPG, PNG, WebP, PDF — max 5 MB
                                </div>
                                <div id="cam_container_id_photo_back" style="display:none; padding:0; border:none; box-shadow:none; background:transparent; align-items:flex-start; margin-top:0.25rem; width:100%;">
                                    <img id="cam_thumb_id_photo_back" style="display:none;" alt="cam">
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

            <!-- Notes -->
            <div class="card" style="flex: 1; display: flex; flex-direction: column;">
                <div class="card-header">
                    <h2 class="card-title"><i data-lucide="file-text"
                            style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>Notes
                    </h2>
                </div>
                <div class="card-body" style="flex: 1; display: flex; flex-direction: column;">
                    <div class="form-group" style="margin: 0; flex: 1; display: flex; flex-direction: column;">
                        <textarea id="notes" name="notes" class="form-control" style="flex: 1; min-height: 120px; resize: none;"
                            placeholder="Any additional notes about this customer…"><?= htmlspecialchars($data['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <div style="display:flex;gap:1rem;margin-top:2rem;flex-wrap:wrap;align-items:center;">
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-size: 0.95rem;">
            <i data-lucide="save" style="width:18px;height:18px;"></i> Save Changes
        </button>
        <a href="customer-view.php?id=<?= $customerId ?>" class="btn btn-secondary" style="padding: 0.65rem 1.5rem; font-size: 0.95rem;">Cancel</a>

        <?php if ($customer['is_blacklisted']): ?>
            <button type="button" class="btn btn-secondary" style="padding:0.65rem 1.5rem;font-size:0.95rem;border-color:var(--success);color:var(--success);" onclick="confirmBlacklist(false)">
                <i data-lucide="user-check" style="width:18px;height:18px;"></i> Remove Blacklist
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-secondary" style="padding:0.65rem 1.5rem;font-size:0.95rem;border-color:var(--danger);color:var(--danger);" onclick="confirmBlacklist(true)">
                <i data-lucide="user-x" style="width:18px;height:18px;"></i> Blacklist Customer
            </button>
        <?php endif; ?>

        <?php if ($authUser->hasPermission('customers.delete')): ?>
            <div style="margin-left:auto;">
                <button type="button" class="btn btn-danger" style="padding: 0.65rem 1.5rem; font-size: 0.95rem;" onclick="confirmDelete()">
                    <i data-lucide="trash-2" style="width:18px;height:18px;"></i> Delete Profile
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>

<!-- Hidden form: toggle blacklist status -->
<form id="blacklistForm" method="POST" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="update">
    <!-- carry all required fields with current values -->
    <input type="hidden" name="first_name"             value="<?= htmlspecialchars($customer['first_name']) ?>">
    <input type="hidden" name="last_name"              value="<?= htmlspecialchars($customer['last_name']) ?>">
    <input type="hidden" name="middle_name"            value="<?= htmlspecialchars($customer['middle_name'] ?? '') ?>">
    <input type="hidden" name="date_of_birth"          value="<?= htmlspecialchars($customer['date_of_birth'] ?? '') ?>">
    <input type="hidden" name="gender"                 value="<?= htmlspecialchars($customer['gender'] ?? '') ?>">
    <input type="hidden" name="customer_type"          value="<?= htmlspecialchars($customer['customer_type'] ?? 'walk_in') ?>">
    <input type="hidden" name="phone_primary"          value="<?= htmlspecialchars($customer['phone_primary']) ?>">
    <input type="hidden" name="phone_secondary"        value="<?= htmlspecialchars($customer['phone_secondary'] ?? '') ?>">
    <input type="hidden" name="email"                  value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
    <input type="hidden" name="address"                value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
    <input type="hidden" name="city"                   value="<?= htmlspecialchars($customer['city'] ?? '') ?>">
    <input type="hidden" name="province"               value="<?= htmlspecialchars($customer['province'] ?? '') ?>">
    <input type="hidden" name="notes"                  value="<?= htmlspecialchars($customer['notes'] ?? '') ?>">
    <input type="hidden" name="id_type"                value="<?= htmlspecialchars($customer['id_type'] ?? 'drivers_license') ?>">
    <input type="hidden" name="id_number"              value="<?= htmlspecialchars($customer['id_number'] ?? '') ?>">
    <input type="hidden" name="id_expiry_date"         value="<?= htmlspecialchars($customer['id_expiry_date'] ?? '') ?>">
    <input type="hidden" name="emergency_name"         value="<?= htmlspecialchars($customer['emergency_name'] ?? '') ?>">
    <input type="hidden" name="emergency_phone"        value="<?= htmlspecialchars($customer['emergency_phone'] ?? '') ?>">
    <input type="hidden" name="emergency_relationship" value="<?= htmlspecialchars($customer['emergency_relationship'] ?? '') ?>">
    <input type="hidden" name="is_blacklisted" id="blacklistValue" value="<?= $customer['is_blacklisted'] ? '0' : '1' ?>">
</form>

<?php if ($authUser->hasPermission('customers.delete')): ?>
<form id="deleteForm" method="POST" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
</form>
<script>
function confirmDelete() {
    openGcrModal({
        title: 'Delete Customer',
        message: 'Are you sure you want to permanently delete this customer? This action <strong class="text-danger">cannot be undone</strong>.',
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
function confirmBlacklist(blacklisting) {
    const name = <?= json_encode($customer['first_name'] . ' ' . $customer['last_name']) ?>;
    openGcrModal({
        title: blacklisting ? 'Blacklist Customer' : 'Remove Blacklist',
        message: blacklisting
            ? `Are you sure you want to blacklist <strong>${name}</strong>? They will no longer be eligible to rent vehicles.`
            : `Are you sure you want to remove the blacklist on <strong>${name}</strong>? They will be able to rent vehicles again.`,
        variant: blacklisting ? 'danger' : 'warning',
        confirmLabel: blacklisting ? 'Yes, Blacklist' : 'Yes, Remove Blacklist',
        icon: blacklisting ? 'user-x' : 'user-check',
        onConfirm: function() {
            document.getElementById('blacklistValue').value = blacklisting ? '1' : '0';
            document.getElementById('blacklistForm').submit();
        }
    });
}
</script>

<script>
lucide.createIcons();
function previewCustomerPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('customerPhotoPreview');
        if (preview) {
            preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            preview.style.background = 'transparent';
        }
    };
    reader.readAsDataURL(input.files[0]);
}
function previewIDPhoto(input, targetId) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const preview = document.getElementById(targetId);
    if (!preview) return;

    if (file.type === 'application/pdf') {
        preview.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--primary-600);"><i data-lucide="file-text" style="width:32px;height:32px;margin-bottom:4px;"></i><span style="font-size:0.6rem;font-weight:bold;">PDF File</span></div>`;
        lucide.createIcons();
        preview.style.background = 'var(--primary-100)';
    } else {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            preview.style.background = 'transparent';
        };
        reader.readAsDataURL(file);
    }
}
</script>
<?php require_once '../../includes/camera-scanner.php'; ?>
<?php require_once '../../includes/footer.php'; ?>