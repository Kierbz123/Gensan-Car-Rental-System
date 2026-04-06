<?php
/**
 * Add New Vehicle Page
 */

require_once '../../includes/session-manager.php';

// ─── All redirects must happen BEFORE any HTML output ───────────────────────
$authUser->requirePermission('vehicles.create');

$vehicle = new Vehicle();
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }

        $data = $_POST;
        
        // Handle file upload
        if (isset($_FILES['primary_photo']) && $_FILES['primary_photo']['tmp_name']) {
            $data['primary_photo'] = $_FILES['primary_photo'];
        }

        $result = $vehicle->create($data, $authUser->getId());
        
        $_SESSION['success_message'] = 'Vehicle created successfully! Vehicle ID: ' . $result;
        redirect('modules/asset-tracking/vehicle-details.php?id=' . $result);
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Get categories for dropdown
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT * FROM vehicle_categories WHERE is_active = TRUE ORDER BY display_order");

// ─── Now safe to output HTML ─────────────────────────────────────────────────
require_once '../../includes/header.php';
?>

<div>
    <div class="page-header">
        <div class="page-title">
            <h1>Add New Vehicle</h1>
            <p>Register a new fleet asset into the system.</p>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Fleet
            </a>
        </div>
    </div>

    <div>
            <?php if (!empty($errors)): ?>
            <div style="margin-bottom: 2rem; padding: 1rem; background: var(--danger-light); color: var(--danger); border-radius: var(--radius-md); font-weight: 500;">
                <h5 style="margin-bottom: 0.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="alert-circle" style="width:18px;height:18px;"></i> Error!
                </h5>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                    
                    <!-- Basic Information & Tech Specs column -->
                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i data-lucide="car-front" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--accent)"></i> Basic Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="category_id">Vehicle Category <span class="text-danger">*</span></label>
                                    <select class="form-control" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" 
                                                data-seats="<?php echo $cat['default_seating']; ?>"
                                                data-fuel="<?php echo $cat['default_fuel_type']; ?>"
                                                <?php echo ($_POST['category_id'] ?? '') == $cat['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo $cat['category_name']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a category</div>
                                </div>

                                <div class="form-group">
                                    <label for="plate_number">Plate Number / Asset ID <span class="text-danger">*</span></label>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" class="form-control text-uppercase" id="plate_number" name="plate_number" 
                                               value="<?php echo htmlspecialchars($_POST['plate_number'] ?? ''); ?>" 
                                               placeholder="ABC 1234 or ASSET-01" required maxlength="20">
                                        <button type="button" class="btn btn-secondary" onclick="generatePlate()" style="white-space:nowrap; padding:0 12px; font-size:0.8125rem;">
                                            <i data-lucide="zap" style="width:14px;height:14px;margin-right:4px;"></i> Auto
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Plate number or Asset ID is required</div>
                                </div>

                                <div class="form-row form-row--two">
                                    <div class="form-group">
                                        <label for="brand">Brand <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="brand" name="brand" 
                                               value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>" 
                                               placeholder="Toyota, Honda, etc." required>
                                    </div>
                                    <div class="form-group">
                                        <label for="model">Model <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="model" name="model" 
                                               value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>" 
                                               placeholder="Vios, Civic, etc." required>
                                    </div>
                                </div>

                                <div class="form-group form-group--variant">
                                    <label for="variant">Variant</label>
                                    <input type="text" class="form-control" id="variant" name="variant" 
                                           value="<?php echo htmlspecialchars($_POST['variant'] ?? ''); ?>" 
                                           placeholder="1.3 E, RS, etc.">
                                </div>

                                <div class="form-row form-row--two">
                                    <div class="form-group">
                                        <label for="year_model">Year Model <span class="text-danger">*</span></label>
                                        <select class="form-control" id="year_model" name="year_model" required>
                                            <option value="">Select Year</option>
                                            <?php for ($y = date('Y') + 1; $y >= 1990; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo ($_POST['year_model'] ?? '') == $y ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="color">Color <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="color" name="color" 
                                               value="<?php echo htmlspecialchars($_POST['color'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Specifications -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i data-lucide="wrench" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--accent)"></i> Technical Specifications</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="seating_capacity">Seating Capacity</label>
                                            <input type="number" class="form-control" id="seating_capacity" name="seating_capacity" 
                                                   value="<?php echo htmlspecialchars($_POST['seating_capacity'] ?? '5'); ?>" min="1" max="50">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="fuel_type">Fuel Type <span class="text-danger">*</span></label>
                                            <select class="form-control" id="fuel_type" name="fuel_type" required>
                                                <option value="gasoline" <?php echo ($_POST['fuel_type'] ?? '') == 'gasoline' ? 'selected' : ''; ?>>Gasoline</option>
                                                <option value="diesel" <?php echo ($_POST['fuel_type'] ?? '') == 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                                <option value="hybrid" <?php echo ($_POST['fuel_type'] ?? '') == 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                                <option value="electric" <?php echo ($_POST['fuel_type'] ?? '') == 'electric' ? 'selected' : ''; ?>>Electric</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="transmission">Transmission <span class="text-danger">*</span></label>
                                            <select class="form-control" id="transmission" name="transmission" required>
                                                <option value="manual" <?php echo ($_POST['transmission'] ?? '') == 'manual' ? 'selected' : ''; ?>>Manual</option>
                                                <option value="automatic" <?php echo ($_POST['transmission'] ?? '') == 'automatic' ? 'selected' : ''; ?>>Automatic</option>
                                                <option value="cvt" <?php echo ($_POST['transmission'] ?? '') == 'cvt' ? 'selected' : ''; ?>>CVT</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="engine_number">Engine Number</label>
                                        <input type="text" class="form-control" id="engine_number" name="engine_number" 
                                               value="<?php echo htmlspecialchars($_POST['engine_number'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="chassis_number">Chassis Number</label>
                                        <input type="text" class="form-control" id="chassis_number" name="chassis_number" 
                                               value="<?php echo htmlspecialchars($_POST['chassis_number'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acquisition & Pricing column -->
                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i data-lucide="tag" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--success)"></i> Acquisition & Pricing</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="acquisition_date">Acquisition Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="acquisition_date" name="acquisition_date" 
                                           value="<?php echo htmlspecialchars($_POST['acquisition_date'] ?? date('Y-m-d')); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="acquisition_cost">Acquisition Cost (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                        </div>
                                        <input type="number" class="form-control" id="acquisition_cost" name="acquisition_cost" 
                                               value="<?php echo htmlspecialchars($_POST['acquisition_cost'] ?? ''); ?>" 
                                               step="0.01" min="0">
                                    </div>
                                </div>

                                <hr>

                                <div class="form-group" style="margin-bottom: 1.5rem;">
                                    <label for="daily_rental_rate">Daily Rental Rate <span class="text-danger">*</span></label>
                                    <div style="display:flex;align-items:center;">
                                        <span style="padding: 0.65rem; background: var(--bg-muted); border: 1px solid var(--border-color); border-right: none; border-radius: var(--radius-sm) 0 0 var(--radius-sm); color: var(--text-muted); font-weight: 700;"><?php echo CURRENCY_SYMBOL; ?></span>
                                        <input type="number" class="form-control" id="daily_rental_rate" name="daily_rental_rate" 
                                               value="<?php echo htmlspecialchars($_POST['daily_rental_rate'] ?? ''); ?>" 
                                               step="0.01" min="0" required style="border-radius: 0 var(--radius-sm) var(--radius-sm) 0;">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="weekly_rental_rate">Weekly Rental Rate</label>
                                        <div style="display:flex;align-items:center;">
                                            <span style="padding: 0.65rem; background: var(--bg-muted); border: 1px solid var(--border-color); border-right: none; border-radius: var(--radius-sm) 0 0 var(--radius-sm); color: var(--text-muted); font-weight: 700;"><?php echo CURRENCY_SYMBOL; ?></span>
                                            <input type="number" class="form-control" id="weekly_rental_rate" name="weekly_rental_rate" 
                                                   value="<?php echo htmlspecialchars($_POST['weekly_rental_rate'] ?? ''); ?>" 
                                                   step="0.01" min="0" style="border-radius: 0 var(--radius-sm) var(--radius-sm) 0;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="monthly_rental_rate">Monthly Rental Rate</label>
                                        <div style="display:flex;align-items:center;">
                                            <span style="padding: 0.65rem; background: var(--bg-muted); border: 1px solid var(--border-color); border-right: none; border-radius: var(--radius-sm) 0 0 var(--radius-sm); color: var(--text-muted); font-weight: 700;"><?php echo CURRENCY_SYMBOL; ?></span>
                                            <input type="number" class="form-control" id="monthly_rental_rate" name="monthly_rental_rate" 
                                                   value="<?php echo htmlspecialchars($_POST['monthly_rental_rate'] ?? ''); ?>" 
                                                   step="0.01" min="0" style="border-radius: 0 var(--radius-sm) var(--radius-sm) 0;">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="security_deposit_amount">Security Deposit</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                        </div>
                                        <input type="number" class="form-control" id="security_deposit_amount" name="security_deposit_amount" 
                                               value="<?php echo htmlspecialchars($_POST['security_deposit_amount'] ?? ''); ?>" 
                                               step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Photo Upload — 3D Widget -->
                        <div class="card" id="vehiclePhotoCard">
                            <div class="card-header">
                                <h3 class="card-title"><i data-lucide="image" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--warning)"></i> Vehicle Photo</h3>
                            </div>
                            <div class="card-body" style="padding-bottom:1rem;">
                                <style>
                                    #vehicle3dStage{perspective:900px;width:100%;height:180px;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;}
                                    #vehicle3dCard{width:220px;height:138px;border-radius:14px;background:linear-gradient(135deg,#1e293b,#334155);box-shadow:0 20px 60px rgba(0,0,0,.35),0 4px 12px rgba(0,0,0,.2);transform-style:preserve-3d;animation:spin3d 8s linear infinite;overflow:hidden;position:relative;}
                                    #vehicle3dCard img{width:100%;height:100%;object-fit:cover;border-radius:14px;}
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
                                        <div class="car-placeholder" id="carPlaceholder">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                                            <span>No Photo Yet</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Drop-zone -->
                                <div id="vehiclePhotoDropzone" onclick="document.getElementById('primary_photo').click()">
                                    <div class="dz-label">
                                        <i data-lucide="upload-cloud" style="width:20px;height:20px;vertical-align:-4px;margin-right:6px;color:var(--primary);"></i>
                                        Click or drag a photo here
                                    </div>
                                    <div class="dz-hint">JPG, PNG, WebP — max 5 MB &nbsp;·&nbsp; Recommended 800×600</div>
                                </div>
                                <input type="file" id="primary_photo" name="primary_photo" accept="image/*" style="display:none;" onchange="vehiclePhotoSelected(this)">
                                <p id="vehiclePhotoName" style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem;text-align:center;display:none;"></p>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Additional Notes</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <textarea class="form-control" id="notes" name="notes" rows="4" 
                                              placeholder="Enter any additional information about this vehicle..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem;">
                        <i data-lucide="save" style="width:16px;height:16px;"></i> Create Vehicle
                    </button>
                    <a href="index.php" class="btn btn-ghost" style="padding: 0.8rem 2rem;">Cancel</a>
                </div>
            </form>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// 3D Vehicle Photo Widget
function vehiclePhotoSelected(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const card = document.getElementById('vehicle3dCard');
        card.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        card.style.animation = 'none'; // pause spin to show photo clearly
        setTimeout(() => card.style.animation = 'spin3d 12s linear infinite', 600);
    };
    reader.readAsDataURL(file);
    const nameEl = document.getElementById('vehiclePhotoName');
    nameEl.textContent = '📎 ' + file.name;
    nameEl.style.display = 'block';
}

// Drag-and-drop on drop-zone
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

// Auto-fill defaults based on category
document.getElementById('category_id').addEventListener('change', function() {
    var option = this.options[this.selectedIndex];
    var seats = option.getAttribute('data-seats');
    var fuel = option.getAttribute('data-fuel');
    
    if (seats) document.getElementById('seating_capacity').value = seats;
    if (fuel) document.getElementById('fuel_type').value = fuel;
});

// Auto Generate Plate/Asset ID
function generatePlate() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = 'AST-';
    for (let i = 0; i < 5; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const plateInput = document.getElementById('plate_number');
    plateInput.value = result;
    // Trigger input event to run duplicate check
    plateInput.dispatchEvent(new Event('input'));
}

// Real-time unique field validation
document.addEventListener('DOMContentLoaded', function() {
    const fieldsToCheck = ['plate_number', 'engine_number', 'chassis_number'];
    const excludeId = ''; // Empty for add mode
    
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
                const url = `ajax/check-duplicate-vehicle.php?field=${fieldId}&value=${encodeURIComponent(value)}`;
                
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

<?php
require_once '../../includes/footer.php';
?>
