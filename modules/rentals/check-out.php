<?php
/**
 * Check-Out Protocol — Vehicle Dispatch
 * Path: modules/rentals/check-out.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('rentals.update');

$db = Database::getInstance();
$rentalId = (int) ($_GET['id'] ?? 0);
if (!$rentalId) {
    redirect('modules/rentals/', 'Rental ID missing', 'error');
}

$rental = $db->fetchOne(
    "SELECT ra.*, v.plate_number, v.brand, v.model, v.mileage AS current_mileage, v.year_model, v.fuel_type, v.transmission, v.primary_photo_path, v.current_status, vc.category_name,
            CONCAT(c.first_name,' ',c.last_name) AS customer_name
     FROM rental_agreements ra
     JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
     JOIN vehicle_categories vc ON v.category_id = vc.category_id
     JOIN customers c ON ra.customer_id = c.customer_id
     WHERE ra.agreement_id = ? AND ra.status IN ('reserved', 'confirmed')",
    [$rentalId]
);
if (!$rental) {
    redirect('modules/rentals/', 'Rental not found or not in dispatchable state', 'error');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $startMileage = (int) ($_POST['start_mileage'] ?? 0);
        if ($startMileage < 0)
            $errors[] = 'Start mileage must be a positive number.';

        if (empty($errors)) {
            try {
                $rentalObj = new RentalAgreement();
                $rentalObj->checkout($rentalId, $startMileage, $authUser->getId());

                $_SESSION['success_message'] = 'Vehicle dispatched successfully. Rental is now active.';
                header('Location: view.php?id=' . $rentalId);
                exit;
            } catch (Exception $e) {
                error_log("Check-out failed: " . $e->getMessage());
                $errors[] = 'Failed to confirm dispatch. Please try again.';
            }
        }
    }
}

$pageTitle = 'Check-Out — ' . $rental['agreement_number'];
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-7xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors whitespace-nowrap">Rentals</a>
        <span class="text-secondary-200">/</span>
        <a href="view.php?id=<?= $rentalId ?>"
            class="text-secondary-400 hover:text-primary-600 transition-colors whitespace-nowrap"><?= htmlspecialchars($rental['agreement_number']) ?></a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600 break-words">Check-Out</span>
    </div>

    <?php if (!empty($errors)): ?>
        <div
            class="flex items-center gap-3 p-4 mb-6 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700 text-xs font-bold">
            <i data-lucide="alert-circle" class="w-4 h-4"></i>
            <ul style="margin:0; padding-left: 1rem; list-style-type: disc;">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">
        <?= csrfField() ?>
        <!-- Main Layout Grid -->
        <div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
            <!-- Summary / Info Card (Left Column) -->
            <div class="flex flex-col gap-6">
                <!-- Vehicle Card -->
                <div class="card" style="text-align: center;">
                    <style>
                        #vehicle3dStage { perspective: 900px; width: 100%; height: 140px; display: flex; align-items: center; justify-content: center; margin-bottom: var(--space-4); }
                        #vehicle3dCard { width: 220px; height: 140px; border-radius: 14px; background: linear-gradient(135deg, #1e293b, #334155); box-shadow: 0 15px 40px rgba(0, 0, 0, .35), 0 4px 12px rgba(0, 0, 0, .2); transform-style: preserve-3d; animation: spin3d 8s linear infinite; overflow: hidden; position: relative; }
                        #vehicle3dCard img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
                        #vehicle3dCard:hover { animation-play-state: paused; }
                        #vehicle3dCard .car-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
                        @keyframes spin3d { 0% { transform: rotateY(-25deg) rotateX(5deg) } 50% { transform: rotateY(25deg) rotateX(-5deg) } 100% { transform: rotateY(-25deg) rotateX(5deg) } }
                    </style>
                    <div class="card-body">
                        <div id="vehicle3dStage">
                            <div id="vehicle3dCard">
                                <?php if (!empty($rental['primary_photo_path'])): ?>
                                    <img src="<?= BASE_URL . ltrim($rental['primary_photo_path'], '/') ?>" alt="<?= htmlspecialchars($rental['vehicle_id']) ?>">
                                <?php else: ?>
                                    <div class="car-placeholder"><i data-lucide="car-front" style="width: 48px; height: 48px;"></i></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h2 style="margin-bottom: var(--space-2); font-size: 1.5rem; line-height: 1.2;">
                            <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?>
                        </h2>
                        <p style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                            <?= htmlspecialchars($rental['plate_number']) ?>
                        </p>
                        <p style="color: var(--primary-600); font-size: 0.875rem; font-weight: bold; margin-bottom: var(--space-4);">
                            <?= htmlspecialchars($rental['category_name']) ?> • <?= htmlspecialchars($rental['year_model']) ?>
                        </p>

                        <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: var(--<?= $rental['current_status'] === 'reserved' ? 'warning' : ($rental['current_status'] === 'available' ? 'success' : 'primary') ?>); color: white; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">
                            <span style="width: 6px; height: 6px; background: white; border-radius: 50%;"></span>
                            <?= htmlspecialchars(str_replace('_', ' ', $rental['status'])) ?>
                        </div>

                        <div style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
                            <p style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                                Integrity Matrix</p>
                            <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                                <span style="color: var(--text-secondary);">Odometer</span>
                                <strong><?= number_format($rental['current_mileage']) ?> KM</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                                <span style="color: var(--text-secondary);">Powertrain</span>
                                <strong><?= htmlspecialchars($rental['fuel_type']) ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                                <span style="color: var(--text-secondary);">Transmission</span>
                                <strong><?= htmlspecialchars($rental['transmission']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <p
                            style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                            Summary</p>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Client</span>
                            <strong
                                style="text-align: right;"><?= htmlspecialchars($rental['customer_name']) ?></strong>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Start Date</span>
                            <strong><?= formatDate($rental['rental_start_date']) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Return Date</span>
                            <strong><?= formatDate($rental['rental_end_date']) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Page Actions / Authorization -->
                <div class="card" style="border-top: 4px solid var(--primary);">
                    <div class="card-body">
                        <h2 style="margin-bottom: var(--space-3); margin-top: 0; font-size: 1rem;">Departure
                            Authorization</h2>
                        <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: var(--space-4);">
                            This will mark the rental as <strong>ACTIVE</strong> and the vehicle as <strong
                                style="color: var(--primary);">RENTED</strong>. This action is logged.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                            <button type="button" id="btn-confirm-checkout" onclick="confirmCheckOut(event)" class="btn btn-primary" style="justify-content: center;">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Confirm Dispatch
                            </button>
                            <a href="view.php?id=<?= $rentalId ?>" class="btn btn-secondary"
                                style="justify-content: center;">
                                <i data-lucide="x" class="w-4 h-4"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Sections (Right Column) -->
            <div class="flex flex-col gap-6">
                <!-- Departure Reading -->
                <div class="card">
                    <div class="card-body">
                        <h2
                            style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="gauge" style="color: var(--primary);"></i> Departure Reading
                        </h2>
                        <div class="form-group" style="max-width: 300px; margin-bottom: 0;">
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: var(--space-2);">
                                Start Mileage (km) <span style="color: var(--danger);">*</span>
                            </label>
                            <input type="number" name="start_mileage"
                                value="<?= htmlspecialchars($rental['current_mileage']) ?>" min="0" required
                                class="form-control" style="font-size: 1.25rem; font-weight: bold;">
                            <p
                                style="font-size: 0.75rem; color: var(--text-muted); margin-top: var(--space-2); display: flex; align-items: center; gap: 4px;">
                                <i data-lucide="info" style="width: 14px; height: 14px;"></i> Current vehicle odometer:
                                <strong><?= number_format($rental['current_mileage']) ?> km</strong>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Pre-Dispatch Checklist -->
                <div class="card">
                    <div class="card-body">
                        <h2
                            style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="clipboard-list" style="color: var(--success);"></i> Pre-Dispatch Checklist
                        </h2>
                        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <?php
                            $checks = [
                                'Vehicle exterior inspected',
                                'Interior clean and undamaged',
                                'Fuel level noted',
                                'Spare tire present',
                                'Documents returned to client',
                                'Payment confirmed',
                            ];
                            foreach ($checks as $i => $check):
                                ?>
                                <label
                                    style="display: flex; align-items: center; gap: 12px; padding: var(--space-3); border-radius: var(--radius-md); background: var(--secondary-50); border: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                                    <input type="checkbox" name="checklist[]" value="<?= $i ?>"
                                        style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                                    <span style="font-weight: 500; color: var(--text-primary);"><?= $check ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!-- Damage & Condition Documentation -->
                <div class="card">
                    <div class="card-body">
                        <h2 style="margin-bottom:var(--space-4);margin-top:0;display:flex;align-items:center;gap:8px;">
                            <i data-lucide="camera" style="color:var(--primary);"></i> Pre-Dispatch Documentation
                        </h2>
                        <div style="font-size:.875rem;color:var(--text-secondary);margin-bottom:1rem;">
                            Upload clear photos of the vehicle's exterior and interior before dispatch. Existing damages
                            must be documented to avoid customer disputes upon return.
                        </div>
                        <div id="photoUploadDropzone"
                            style="border:2px dashed var(--border-color);border-radius:var(--radius-md);padding:2rem;text-align:center;background:var(--bg-muted);cursor:pointer;transition:all .2s;">
                            <i data-lucide="upload-cloud"
                                style="width:40px;height:40px;color:var(--primary);margin-bottom:1rem;"></i>
                            <div style="font-weight:600;margin-bottom:.5rem;">Click or drag images here to upload</div>
                            <div style="font-size:.75rem;color:var(--text-muted);">Supports JPG, PNG, WebP, PDF up to 5MB
                                each</div>
                            <input type="file" id="damagePhotos" accept="image/jpeg,image/png,image/webp,application/pdf" multiple
                                style="display:none;">
                        </div>
                        <div id="photoGallery" style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1.5rem;"></div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>
<script>
    lucide.createIcons();

    // Modal Confirmation for Check-Out
    function confirmCheckOut(event) {
        event.preventDefault();

        const startMileageInput = document.querySelector('input[name="start_mileage"]');
        if (!startMileageInput.value || parseFloat(startMileageInput.value) < 0) {
            startMileageInput.classList.add('is-invalid');
            startMileageInput.focus();
            startMileageInput.addEventListener('input', function () {
                startMileageInput.classList.remove('is-invalid');
            }, { once: true });
            return;
        }

        if (typeof openGcrModal === 'function') {
            openGcrModal(
                'Confirm Dispatch',
                'This will mark the rental as <strong>ACTIVE</strong> and the vehicle as <strong>RENTED</strong>. ' +
                'This action is logged and <strong>cannot be undone</strong>.',
                function () {
                    document.getElementById('checkoutForm').submit();
                },
                {
                    variant: 'primary',
                    confirmLabel: 'Yes, Dispatch Vehicle',
                    icon: 'log-out'
                }
            );
        } else {
            if (confirm('Dispatch this vehicle? This will mark the rental as ACTIVE and cannot be undone.')) {
                document.getElementById('checkoutForm').submit();
            }
        }
    }

    // Photo Upload Handler (Pre-Dispatch)
    const dropzone = document.getElementById('photoUploadDropzone');
    const fileInput = document.getElementById('damagePhotos');
    const gallery = document.getElementById('photoGallery');
    const CSRF_TOKEN = '<?= getCsrfToken() ?>';
    const AGREEMENT_ID = <?= $rentalId ?>;

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.style.borderColor = 'var(--primary)'; dropzone.style.background = 'var(--primary-light)'; });
    dropzone.addEventListener('dragleave', e => { e.preventDefault(); dropzone.style.borderColor = 'var(--border-color)'; dropzone.style.background = 'var(--bg-muted)'; });
    dropzone.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--border-color)'; dropzone.style.background = 'var(--bg-muted)';
        if (e.dataTransfer.files.length) handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', function () {
        if (this.files.length) handleFiles(this.files);
    });

    function handleFiles(files) {
        Array.from(files).forEach(file => {
            if (!file.type.match('image.*') && file.type !== 'application/pdf') return alert('Only images and PDFs are allowed.');
            if (file.size > 5 * 1024 * 1024) return alert('File ' + file.name + ' exceeds 5MB limit.');
            uploadBlob(file);
        });
        fileInput.value = ''; // reset
    }

    function uploadBlob(file) {
        const thumb = document.createElement('div');
        thumb.style.cssText = 'width:80px;height:80px;border-radius:var(--radius-sm);background:#f1f5f9;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;border:1px solid var(--border-color);';
        thumb.innerHTML = '<i data-lucide="loader-2" class="lucide-spin" style="width:20px;height:20px;color:var(--primary);"></i>';
        gallery.appendChild(thumb);
        lucide.createIcons({ root: thumb });

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('agreement_id', AGREEMENT_ID);
        formData.append('photo', file);

        fetch('<?= BASE_URL ?>modules/rentals/ajax/upload-damage-photo.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                if (!d.success) throw new Error(d.message);
                if (d.path.endsWith('.pdf')) {
                    thumb.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--primary);font-size:0.75rem;width:100%;background:var(--secondary-50);"><i data-lucide="file-text" style="width:24px;height:24px;margin-bottom:4px;"></i>PDF</div>
                                       <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.6);color:#fff;font-size:10px;text-align:center;padding:2px;">Dispatched</div>`;
                } else {
                    thumb.innerHTML = `<img src="${d.path}" style="width:100%;height:100%;object-fit:cover;">
                                       <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.6);color:#fff;font-size:10px;text-align:center;padding:2px;">Dispatched</div>`;
                }
                lucide.createIcons({ root: thumb });
            })
            .catch(e => {
                thumb.innerHTML = `<i data-lucide="alert-circle" style="color:var(--danger)"></i>`;
                lucide.createIcons({ root: thumb });
                alert('Upload failed: ' + e.message);
            });
    }
</script>
<?php require_once '../../includes/footer.php'; ?>