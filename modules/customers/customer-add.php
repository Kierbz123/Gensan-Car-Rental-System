<?php
/**
 * Register New Customer
 * Path: modules/customers/customer-add.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('customers.create');

$db = Database::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $required = ['first_name', 'last_name', 'phone_primary', 'id_type', 'customer_type'];
        foreach ($required as $f) {
            if (empty(trim($_POST[$f] ?? '')))
                $errors[] = ucfirst(str_replace('_', ' ', $f)) . ' is required.';
        }

        if (empty($errors)) {
            try {
                $customer = new Customer();
                // Merge $_POST and $_FILES so files can be handled by the class
                $formData = array_merge($_POST, $_FILES);
                // Use $authUser->getId() instead of $_SESSION['user_id'] which might be missing in cookie-based sessions
                $result = $customer->create($formData, $authUser->getId());
                $id = $result['customer_id'];
                $_SESSION['success_message'] = 'Customer registered successfully.';
                header('Location: customer-view.php?id=' . $id);
                exit;
            } catch (Exception $e) {
                error_log("Customer registration failed: " . $e->getMessage());
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register New Customer';
require_once '../../includes/header.php';
?>

<div class="fade-in">
    <div class="flex justify-between items-start mb-10">
        <div>
            <div class="flex items-center gap-3 mb-3">
                <a href="index.php"
                    class="flex items-center gap-1.5 text-[10px] font-black text-secondary-400 uppercase tracking-widest hover:text-primary-600 transition-colors">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Customer Registry
                </a>
                <span class="text-secondary-200">/</span>
                <span class="text-[10px] font-black text-primary-600 uppercase tracking-widest">Register</span>
            </div>
            <h1 class="heading">Register New Customer</h1>
            <p class="text-secondary-500 font-medium">Initialize a new client identity profile.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flex gap-4 p-5 mb-8 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700">
            <i data-lucide="shield-alert" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
            <ul class="list-disc list-inside text-xs font-medium space-y-0.5">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="max-w-4xl mx-auto">
            <div class="flex flex-col gap-6">

                <!-- Personal Info -->
                <div class="card">
                    <h2
                        class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                        <i data-lucide="user" class="w-4 h-4 text-primary-600"></i> Personal Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <?php foreach ([['first_name', 'First Name', true], ['middle_name', 'Middle Name', false], ['last_name', 'Last Name', true]] as [$n, $l, $r]): ?>
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2"><?= $l ?>
                                    <?= $r ? '<span class="text-danger-500">*</span>' : '' ?></label>
                                <input type="text" name="<?= $n ?>"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                    value="<?= htmlspecialchars($_POST[$n] ?? '') ?>" <?= $r ? 'required' : '' ?>>
                            </div>
                        <?php endforeach; ?>
                        <div class="flex flex-col gap-5">
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Date
                                    of Birth</label>
                                <input type="date" name="date_of_birth"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                    value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                            </div>
                            <!-- Profile Photo Upload -->
                            <div style="margin-top:1.5rem;padding:1.5rem;border-radius:12px;background:linear-gradient(to right, var(--bg-muted), transparent);border:1px solid var(--border-color);">
                                <label style="display:block;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text-color);margin-bottom:1rem;">Profile Picture</label>
                                <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                                    <!-- Live preview avatar -->
                                    <div id="customerPhotoPreview"
                                        style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;flex-shrink:0;overflow:hidden;border:4px solid #fff;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);transition:transform .3s ease, box-shadow .3s ease;"
                                        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 15px 35px -5px rgba(0,0,0,0.2)';"
                                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 10px 25px -5px rgba(0,0,0,0.15)';"
                                        title="Photo preview">
                                        <i data-lucide="user" style="width:44px;height:44px;opacity:0.9;"></i>
                                    </div>
                                    <div style="flex:1;display:flex;flex-direction:column;gap:.75rem;min-width:260px;">
                                        <p style="font-size:.8rem;color:var(--text-muted);margin:0;line-height:1.4;">Upload a clear, recent photo. This will be used for identification purposes across the system.</p>
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
                                            <i data-lucide="info" style="width:14px;height:14px;"></i> JPG, PNG, WebP — max 5 MB
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
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Gender</label>
                            <select name="gender"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900">
                                <option value="">— Select —</option>
                                <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($_POST['gender'] ?? '') === $v ? 'selected' : '' ?>>
                                        <?= $l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Customer
                                Type <span class="text-danger-500">*</span></label>
                            <select name="customer_type"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                required>
                                <?php foreach (['walk_in' => 'Walk-in', 'online' => 'Online', 'corporate' => 'Corporate', 'repeat' => 'Repeat', 'referral' => 'Referral'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($_POST['customer_type'] ?? '') === $v ? 'selected' : '' ?>>
                                        <?= $l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="card">
                    <h2
                        class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                        <i data-lucide="phone" class="w-4 h-4 text-success-600"></i> Contact Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Primary
                                Phone <span class="text-danger-500">*</span></label>
                            <input type="text" name="phone_primary"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                value="<?= htmlspecialchars($_POST['phone_primary'] ?? '') ?>" required
                                placeholder="+63 9XX XXX XXXX">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Secondary
                                Phone</label>
                            <input type="text" name="phone_secondary"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                value="<?= htmlspecialchars($_POST['phone_secondary'] ?? '') ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Email
                                Address</label>
                            <input type="email" name="email"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Address</label>
                            <textarea name="address" rows="2"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900 resize-none"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">City</label>
                            <input type="text" name="city"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                value="<?= htmlspecialchars($_POST['city'] ?? 'General Santos City') ?>">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Province</label>
                            <input type="text" name="province"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                value="<?= htmlspecialchars($_POST['province'] ?? 'South Cotabato') ?>">
                        </div>
                    </div>
                </div>

                <!-- ID Verification -->
                <div class="card">
                    <h2
                        class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-warning-600"></i> ID Verification
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="flex flex-col gap-5">
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">ID
                                    Type <span class="text-danger-500">*</span></label>
                                <select name="id_type"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                    required>
                                    <?php foreach (['drivers_license' => "Driver's License", 'passport' => 'Passport', 'national_id' => 'National ID', 'company_id' => 'Company ID'] as $v => $l): ?>
                                        <option value="<?= $v ?>" <?= ($_POST['id_type'] ?? '') === $v ? 'selected' : '' ?>>
                                            <?= $l ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- ID Front Photo Upload -->
                            <div style="padding:1.5rem;border-radius:12px;background:linear-gradient(to right, var(--bg-muted), transparent);border:1px solid var(--border-color);">
                                <label style="display:block;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text-color);margin-bottom:1rem;">Upload ID (Front)</label>
                                <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                                    <div id="idFrontPreview"
                                        style="width:140px;height:96px;border-radius:12px;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;flex-shrink:0;overflow:hidden;border:4px solid #fff;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);transition:transform .3s ease, box-shadow .3s ease;"
                                        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 15px 35px -5px rgba(0,0,0,0.2)';"
                                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 10px 25px -5px rgba(0,0,0,0.15)';"
                                        title="ID Front preview">
                                        <i data-lucide="credit-card" style="width:44px;height:44px;opacity:0.9;"></i>
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
                                        <div id="cam_container_id_photo_front" style="display:none; padding:0; border:none; box-shadow:none; background:transparent; align-items:flex-start; margin-top:0.25rem; width:100%;">
                                            <img id="cam_thumb_id_photo_front" style="display:none;" alt="cam">
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
                        <div class="flex flex-col gap-5">
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">ID
                                    Number</label>
                                <input type="text" name="id_number"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-mono font-bold text-secondary-900"
                                    value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
                            </div>
                            <!-- ID Back Photo Upload -->
                            <div style="padding:1.5rem;border-radius:12px;background:linear-gradient(to right, var(--bg-muted), transparent);border:1px solid var(--border-color);">
                                <label style="display:block;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--text-color);margin-bottom:1rem;">Upload ID (Back)</label>
                                <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                                    <div id="idBackPreview"
                                        style="width:140px;height:96px;border-radius:12px;background:linear-gradient(135deg, var(--primary), var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;flex-shrink:0;overflow:hidden;border:4px solid #fff;box-shadow:0 10px 25px -5px rgba(0,0,0,0.15);transition:transform .3s ease, box-shadow .3s ease;"
                                        onmouseover="this.style.transform='scale(1.05)';this.style.boxShadow='0 15px 35px -5px rgba(0,0,0,0.2)';"
                                        onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 10px 25px -5px rgba(0,0,0,0.15)';"
                                        title="ID Back preview">
                                        <i data-lucide="scan-line" style="width:44px;height:44px;opacity:0.9;"></i>
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
                                        <div id="cam_container_id_photo_back" style="display:none; padding:0; border:none; box-shadow:none; background:transparent; align-items:flex-start; margin-top:0.25rem; width:100%;">
                                            <img id="cam_thumb_id_photo_back" style="display:none;" alt="cam">
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
                        <div class="flex flex-col gap-5">
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">ID
                                    Expiry</label>
                                <input type="date" name="id_expiry_date"
                                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                    value="<?= htmlspecialchars($_POST['id_expiry_date'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="card">
                    <h2
                        class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                        <i data-lucide="heart-pulse" class="w-4 h-4 text-danger-600"></i> Emergency Contact
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Full
                                Name</label>
                            <input type="text" name="emergency_name"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                value="<?= htmlspecialchars($_POST['emergency_name'] ?? '') ?>">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Phone</label>
                            <input type="text" name="emergency_phone"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                value="<?= htmlspecialchars($_POST['emergency_phone'] ?? '') ?>">
                        </div>
                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Relationship</label>
                            <input type="text" name="emergency_relationship"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-bold text-secondary-900"
                                value="<?= htmlspecialchars($_POST['emergency_relationship'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Notes Block -->
                <div class="card mb-6">
                    <div class="card-body">
                        <label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-3 flex items-center gap-2">
                            <i data-lucide="file-text" class="w-4 h-4 text-warning-600"></i> Notes
                        </label>
                        <textarea name="notes" rows="3"
                            class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 border-secondary-100 font-medium text-secondary-900 resize-none"
                            placeholder="Additional remarks…"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Submit Block moved below Notes -->
                <div class="card bg-secondary-900 text-pure-white border-transparent">
                    <div class="card-body">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-white/10 rounded-xl"><i data-lucide="user-plus"
                                        class="w-6 h-6 text-primary-400"></i></div>
                                <div>
                                    <p class="text-xs font-black uppercase tracking-widest text-pure-white mb-1">Client
                                        Registration</p>
                                    <p class="text-[10px] font-medium text-secondary-400 leading-relaxed">The system
                                        will
                                        auto-generate a unique customer code<br class="hidden md:block"> and initialize
                                        the
                                        client profile.</p>
                                </div>
                            </div>
                            <div class="flex flex-col md:items-end gap-3 w-full md:w-auto">
                                <button type="submit"
                                    class="btn btn-primary w-full md:w-auto px-8 py-3.5 rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 group">
                                    <i data-lucide="user-check" class="w-5 h-5"></i>
                                    Register Customer
                                    <i data-lucide="arrow-right"
                                        class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                                </button>
                                <a href="index.php"
                                    class="text-center text-[10px] text-secondary-500 hover:text-secondary-300 font-bold uppercase tracking-widest transition-colors">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
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