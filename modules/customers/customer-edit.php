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
<div class="fade-in max-w-7xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors whitespace-nowrap">Customers</a>
        <span class="text-secondary-200">/</span>
        <a href="customer-view.php?id=<?= $customerId ?>"
            class="text-secondary-400 hover:text-primary-600 transition-colors break-words"><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600">Edit</span>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flex gap-3 p-5 mb-6 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700">
            <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
            <ul class="text-xs font-medium list-disc list-inside space-y-0.5"><?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
            <!-- Left Column: Avatar & Actions -->
            <div class="flex flex-col gap-6">
                <!-- Avatar Card -->
                <div class="card" style="text-align: center;">
                    <div class="card-body">
                        <?php if (!empty($customer['profile_picture_path'])): ?>
                            <img src="<?= BASE_URL . ltrim($customer['profile_picture_path'], '/') ?>"
                                style="width: 120px; height: 120px; border-radius: 50%; margin: 0 auto var(--space-4); object-fit: cover; border: 4px solid var(--primary-100);"
                                alt="Profile Picture">
                        <?php else: ?>
                            <div
                                style="width: 120px; height: 120px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; margin: 0 auto var(--space-4);">
                                <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <h2 style="margin-bottom: var(--space-2);">
                            <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                        </h2>
                        <p
                            style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                            <?= str_replace('_', ' ', ucfirst($customer['customer_type'] ?? 'Walk-in')) ?>
                        </p>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">
                            <?= htmlspecialchars($customer['customer_code'] ?? '') ?>
                        </p>
                    </div>
                </div>

                <!-- Notes Card -->
                <div class="card">
                    <div class="card-body">
                        <label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-3">Notes</label>
                        <textarea name="notes" rows="6"
                            class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none"><?= htmlspecialchars($data['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Page Actions via Template -->
                <div
                    style="display: flex; flex-direction: column; gap: var(--space-3); position: sticky; top: var(--space-6);">
                    <button type="submit" class="btn btn-primary" style="justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            data-lucide="save" aria-hidden="true" class="lucide lucide-save w-4 h-4">
                            <path
                                d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z">
                            </path>
                            <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                            <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                        </svg> Save Changes
                    </button>
                    <a href="customer-view.php?id=<?= $customerId ?>" class="btn btn-secondary"
                        style="justify-content: center;">
                        <i data-lucide="x" class="w-4 h-4"></i> Cancel
                    </a>
                    
                    <?php if ($authUser->hasPermission('customers.delete')): ?>
                    <hr style="margin: var(--space-2) 0; border: none; border-top: 1px dashed var(--border-color);">
                    <button type="button" class="btn btn-danger" style="justify-content: center;" onclick="confirmDelete()">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Profile
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Form Fields -->
            <div class="flex flex-col gap-6">
                <!-- Personal Info -->
                <div class="card">
                    <div class="card-body">
                        <h2
                            class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                            <i data-lucide="user" class="w-4 h-4 text-primary-600"></i> Personal Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <?php foreach ([['first_name', 'First Name', true], ['middle_name', 'Middle Name', false], ['last_name', 'Last Name', true]] as [$n, $l, $r]): ?>
                                <div>
                                    <label
                                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2"><?= $l ?><?= $r ? ' <span class="text-danger-500">*</span>' : '' ?></label>
                                    <input type="text" name="<?= $n ?>"
                                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                        value="<?= htmlspecialchars($data[$n] ?? '') ?>" <?= $r ? 'required' : '' ?>>
                                </div>
                            <?php endforeach; ?>
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label
                                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Date
                                        of Birth</label>
                                    <input type="date" name="date_of_birth"
                                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                        value="<?= htmlspecialchars($data['date_of_birth'] ?? '') ?>">
                                </div>
                                <!-- Profile Photo Upload -->
                                <div style="margin-top:1.5rem;padding:1.5rem;border-radius:12px;background:linear-gradient(to right, var(--bg-muted), transparent);border:1px solid var(--border-color);">
                                    <label style="display:block;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text-color);margin-bottom:1rem;">Update Profile Picture</label>
                                    <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                                        <!-- Current / live-preview avatar -->
                                        <div id="customerPhotoPreview"
                                            style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;flex-shrink:0;overflow:hidden;border:4px solid #fff;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);transition:transform .3s ease, box-shadow .3s ease;"
                                            onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 15px 35px -5px rgba(0,0,0,0.2)';"
                                            onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 10px 25px -5px rgba(0,0,0,0.15)';"
                                            title="Photo preview">
                                            <?php if (!empty($customer['profile_picture_path'])): ?>
                                                <img src="<?= BASE_URL . ltrim($customer['profile_picture_path'], '/') ?>"
                                                    style="width:100%;height:100%;object-fit:cover;" alt="Profile">
                                            <?php else: ?>
                                                <span><?= strtoupper(substr($data['first_name'] ?? '', 0, 1) . substr($data['last_name'] ?? '', 0, 1)) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="flex:1;display:flex;flex-direction:column;gap:.75rem;min-width:260px;">
                                            <p style="font-size:.8rem;color:var(--text-muted);margin:0;line-height:1.4;">Upload a new photo to update the profile.</p>
                                            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                                                <div style="position:relative;flex:1;min-width:200px;">
                                                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                                                        style="width:100%;padding:.65rem 1rem;border:2px dashed var(--border-color);border-radius:var(--radius-md);font-size:.875rem;cursor:pointer;background:var(--bg-card);transition:all 0.2s;"
                                                        onmouseover="this.style.borderColor='var(--primary)';this.style.background='var(--primary-50)';"
                                                        onmouseout="this.style.borderColor='var(--border-color)';this.style.background='var(--bg-card)';"
                                                        onchange="previewCustomerPhoto(this)">
                                                </div>
                                                <button type="button" onclick="openCamera('profile_picture', 'Take Profile Picture')"
                                                    class="btn btn-secondary" style="padding:.65rem 1.25rem;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;gap:8px;font-weight:600;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);transition:all 0.2s;flex-shrink:0;" title="Use Camera"
                                                    onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 12px -2px rgba(0,0,0,0.1)';"
                                                    onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';">
                                                    <i data-lucide="camera" style="width:18px;height:18px;color:var(--primary);"></i>
                                                    <span>Camera</span>
                                                </button>
                                            </div>
                                            <div style="display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-muted);font-weight:500;">
                                            <?php if (!empty($customer['profile_picture_path'])): ?>
                                                <span style="color:var(--text-success);display:inline-flex;align-items:center;gap:4px;">
                                                    <i data-lucide="check" style="width:14px;height:14px;"></i> Photo currently on file
                                                </span>
                                            <?php else: ?>
                                                <span><i data-lucide="info" style="width:14px;height:14px;vertical-align:-2px;margin-right:2px;"></i> JPG, PNG, WebP — max 5 MB</span>
                                            <?php endif; ?>
                                            </div>
                                            
                                            <!-- Pre-defined camera success container to integrate smoothly -->
                                            <div id="cam_container_profile_picture" style="display:none; padding:0; border:none; box-shadow:none; background:transparent; align-items:flex-start; margin-top:0.25rem; width:100%;">
                                                <img id="cam_thumb_profile_picture" style="display:none;" alt="cam">
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
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Customer
                                    Type</label>
                                <select name="customer_type"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50">
                                    <?php foreach (['walk_in' => 'Walk-in', 'online' => 'Online', 'corporate' => 'Corporate', 'repeat' => 'Repeat', 'referral' => 'Referral'] as $v => $l): ?>
                                        <option value="<?= $v ?>" <?= ($data['customer_type'] ?? '') === $v ? 'selected' : '' ?>>
                                            <?= $l ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Status</label>
                                <select name="status" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50">
                                    <?php foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $v => $l): ?>
                                        <option value="<?= $v ?>" <?= ($data['status'] ?? '') === $v ? 'selected' : '' ?>>
                                            <?= $l ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="card">
                    <div class="card-body">
                        <h2
                            class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                            <i data-lucide="phone" class="w-4 h-4 text-success-600"></i> Contact Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div><label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Primary
                                    Phone <span class="text-danger-500">*</span></label><input type="text"
                                    name="phone_primary" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                    value="<?= htmlspecialchars($data['phone_primary'] ?? '') ?>" required></div>
                            <div><label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Secondary
                                    Phone</label><input type="text" name="phone_secondary"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                    value="<?= htmlspecialchars($data['phone_secondary'] ?? '') ?>"></div>
                            <div class="md:col-span-2"><label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Email</label><input
                                    type="email" name="email"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                    value="<?= htmlspecialchars($data['email'] ?? '') ?>"></div>
                            <div class="md:col-span-2"><label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Address</label><textarea
                                    name="address" rows="2"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none"><?= htmlspecialchars($data['address'] ?? '') ?></textarea>
                            </div>
                            <div><label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">City</label><input
                                    type="text" name="city" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                    value="<?= htmlspecialchars($data['city'] ?? '') ?>"></div>
                            <div><label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Province</label><input
                                    type="text" name="province"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                    value="<?= htmlspecialchars($data['province'] ?? '') ?>"></div>
                        </div>
                    </div>
                </div>

                <!-- ID Verification -->
                <div class="card">
                    <div class="card-body">
                        <h2
                            class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-4 h-4 text-warning-600"></i> ID Verification
                            (Optional Update)
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- ID Front Photo Upload -->
                            <div style="padding:1.5rem;border-radius:12px;background:linear-gradient(to right, var(--bg-muted), transparent);border:1px solid var(--border-color);">
                                <label style="display:block;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text-color);margin-bottom:1rem;">Update ID Photo (Front)</label>
                                <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                                    <div id="idFrontPreview"
                                        style="width:140px;height:96px;border-radius:12px;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;flex-shrink:0;overflow:hidden;border:4px solid #fff;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);transition:transform .3s ease, box-shadow .3s ease;"
                                        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 15px 35px -5px rgba(0,0,0,0.2)';"
                                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 10px 25px -5px rgba(0,0,0,0.15)';"
                                        title="ID Front preview">
                                        <?php if (!empty($customer['id_photo_front_path'])): ?>
                                            <?php if(str_ends_with(strtolower($customer['id_photo_front_path']), '.pdf')): ?>
                                                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;"><i data-lucide="file-text" style="width:32px;height:32px;margin-bottom:4px;"></i><span style="font-size:0.6rem;font-weight:bold;">PDF File</span></div>
                                            <?php else: ?>
                                                <img src="<?= htmlspecialchars('../../' . $customer['id_photo_front_path']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.outerHTML='<i data-lucide=\'credit-card\' style=\'width:44px;height:44px;opacity:0.9;\'></i>'">
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <i data-lucide="credit-card" style="width:44px;height:44px;opacity:0.9;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex:1;display:flex;flex-direction:column;gap:.75rem;min-width:200px;">
                                        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                                            <div style="position:relative;flex:1;min-width:160px;">
                                                <input type="file" id="id_photo_front" name="id_photo_front" accept="image/*,application/pdf"
                                                    style="width:100%;padding:.65rem 1rem;border:2px dashed var(--border-color);border-radius:var(--radius-md);font-size:.875rem;cursor:pointer;background:var(--bg-card);transition:all 0.2s;"
                                                    onmouseover="this.style.borderColor='var(--primary)';this.style.background='var(--primary-50)';"
                                                    onmouseout="this.style.borderColor='var(--border-color)';this.style.background='var(--bg-card)';"
                                                    onchange="previewIDPhoto(this, 'idFrontPreview')">
                                            </div>
                                            <button type="button" onclick="openCamera('id_photo_front', 'Scan Front ID')"
                                                class="btn btn-secondary" style="padding:.65rem 1.25rem;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;gap:8px;font-weight:600;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);transition:all 0.2s;flex-shrink:0;" title="Use Camera"
                                                onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 12px -2px rgba(0,0,0,0.1)';"
                                                onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';">
                                                <i data-lucide="camera" style="width:18px;height:18px;color:var(--primary);"></i>
                                                <span>Camera</span>
                                            </button>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-muted);font-weight:500;">
                                            <i data-lucide="info" style="width:14px;height:14px;"></i> JPG, PNG, WebP, PDF — max 5 MB
                                        </div>
                                        <?php if (!empty($customer['id_photo_front_path'])): ?>
                                            <div style="display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--success);font-weight:700;">
                                                <i data-lucide="check-circle" style="width:14px;height:14px;"></i> Front photo is currently on file.
                                            </div>
                                        <?php endif; ?>
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
                            <div style="padding:1.5rem;border-radius:12px;background:linear-gradient(to right, var(--bg-muted), transparent);border:1px solid var(--border-color);">
                                <label style="display:block;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text-color);margin-bottom:1rem;">Update ID Photo (Back)</label>
                                <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                                    <div id="idBackPreview"
                                        style="width:140px;height:96px;border-radius:12px;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;flex-shrink:0;overflow:hidden;border:4px solid #fff;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);transition:transform .3s ease, box-shadow .3s ease;"
                                        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 15px 35px -5px rgba(0,0,0,0.2)';"
                                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 10px 25px -5px rgba(0,0,0,0.15)';"
                                        title="ID Back preview">
                                        <?php if (!empty($customer['id_photo_back_path'])): ?>
                                            <?php if(str_ends_with(strtolower($customer['id_photo_back_path']), '.pdf')): ?>
                                                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;"><i data-lucide="file-text" style="width:32px;height:32px;margin-bottom:4px;"></i><span style="font-size:0.6rem;font-weight:bold;">PDF File</span></div>
                                            <?php else: ?>
                                                <img src="<?= htmlspecialchars('../../' . $customer['id_photo_back_path']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.outerHTML='<i data-lucide=\'scan-line\' style=\'width:44px;height:44px;opacity:0.9;\'></i>'">
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <i data-lucide="scan-line" style="width:44px;height:44px;opacity:0.9;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex:1;display:flex;flex-direction:column;gap:.75rem;min-width:200px;">
                                        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                                            <div style="position:relative;flex:1;min-width:160px;">
                                                <input type="file" id="id_photo_back" name="id_photo_back" accept="image/*,application/pdf"
                                                    style="width:100%;padding:.65rem 1rem;border:2px dashed var(--border-color);border-radius:var(--radius-md);font-size:.875rem;cursor:pointer;background:var(--bg-card);transition:all 0.2s;"
                                                    onmouseover="this.style.borderColor='var(--primary)';this.style.background='var(--primary-50)';"
                                                    onmouseout="this.style.borderColor='var(--border-color)';this.style.background='var(--bg-card)';"
                                                    onchange="previewIDPhoto(this, 'idBackPreview')">
                                            </div>
                                            <button type="button" onclick="openCamera('id_photo_back', 'Scan Back ID')"
                                                class="btn btn-secondary" style="padding:.65rem 1.25rem;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;gap:8px;font-weight:600;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);transition:all 0.2s;flex-shrink:0;" title="Use Camera"
                                                onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 12px -2px rgba(0,0,0,0.1)';"
                                                onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';">
                                                <i data-lucide="camera" style="width:18px;height:18px;color:var(--primary);"></i>
                                                <span>Camera</span>
                                            </button>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-muted);font-weight:500;">
                                            <i data-lucide="info" style="width:14px;height:14px;"></i> JPG, PNG, WebP, PDF — max 5 MB
                                        </div>
                                        <?php if (!empty($customer['id_photo_back_path'])): ?>
                                            <div style="display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--success);font-weight:700;">
                                                <i data-lucide="check-circle" style="width:14px;height:14px;"></i> Back photo is currently on file.
                                            </div>
                                        <?php endif; ?>
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
                </div>

            </div>
        </div>
</div>
</div>
</div>
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
        message: 'Are you sure you want to permanently delete this customer? This action <strong class="text-danger-600">cannot be undone</strong>.',
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

</div>
<script>
lucide.createIcons();
function previewCustomerPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('customerPhotoPreview');
        if (preview) {
            preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
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
        preview.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;"><i data-lucide="file-text" style="width:32px;height:32px;margin-bottom:4px;"></i><span style="font-size:0.6rem;font-weight:bold;">PDF File</span></div>`;
        lucide.createIcons();
    } else {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
        };
        reader.readAsDataURL(file);
    }
}
</script>
<?php require_once '../../includes/camera-scanner.php'; ?>
<?php require_once '../../includes/footer.php'; ?>