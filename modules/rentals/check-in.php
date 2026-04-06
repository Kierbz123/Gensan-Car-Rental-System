<?php
/**
 * Check-In Protocol — Vehicle Return
 * Path: modules/rentals/check-in.php
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
    "SELECT ra.*, v.plate_number, v.brand, v.model,
            CONCAT(c.first_name,' ',c.last_name) AS customer_name
     FROM rental_agreements ra
     JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
     JOIN customers c ON ra.customer_id = c.customer_id
     WHERE ra.agreement_id = ? AND ra.status = 'active'",
    [$rentalId]
);
if (!$rental) {
    redirect('modules/rentals/', 'Rental not found or not active', 'error');
}

$errors = [];
$today = date('Y-m-d');
$scheduledEnd = $rental['rental_end_date'];
$isLate = $today > $scheduledEnd;
$lateDays = $isLate ? ceil((strtotime($today) - strtotime($scheduledEnd)) / 86400) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $endMileage = (int) ($_POST['end_mileage'] ?? 0);
        $damageFee = max(0, (float) ($_POST['damage_fee'] ?? 0));
        $lateFee = max(0, (float) ($_POST['late_fee'] ?? 0));
        $fuelCharge = max(0, (float) ($_POST['fuel_charge'] ?? 0));
        $returnNotes = trim($_POST['return_notes'] ?? '');

        if ($endMileage < ($rental['mileage_at_pickup'] ?? 0))
            $errors[] = 'End mileage cannot be less than pickup mileage.';

        if (empty($errors)) {
            try {
                $rentalDays = max(1, ceil((strtotime($scheduledEnd) - strtotime($rental['rental_start_date'])) / 86400));
                $base = $rentalDays * $rental['daily_rate'];
                $total = $base + $rental['security_deposit'] + $damageFee + $lateFee + $fuelCharge;

                $otherCharges = $damageFee + $lateFee + $fuelCharge;
                $formattedNotes = trim($returnNotes);
                if ($otherCharges > 0) {
                    $itemized = [];
                    if ($damageFee > 0)
                        $itemized[] = "Damage: " . number_format($damageFee, 2);
                    if ($lateFee > 0)
                        $itemized[] = "Late Return: " . number_format($lateFee, 2);
                    if ($fuelCharge > 0)
                        $itemized[] = "Fuel: " . number_format($fuelCharge, 2);
                    $formattedNotes .= "\n\nAdditional Charges (" . date('Y-m-d H:i') . "): " . implode(", ", $itemized);
                }

                $db->execute(
                    "UPDATE rental_agreements SET status='returned', mileage_at_return=?, actual_return_date=NOW(),
                     other_charges=?, total_amount=?, notes=?, received_by_staff=?, updated_at=NOW()
                     WHERE agreement_id=?",
                    [$endMileage, $otherCharges, $total, trim($formattedNotes), $_SESSION['user_id'], $rentalId]
                );
                $db->execute(
                    "UPDATE vehicles SET current_status='available', mileage=? WHERE vehicle_id=?",
                    [$endMileage, $rental['vehicle_id']]
                );

                // Free the assigned chauffeur driver back to 'available'
                if (!empty($rental['driver_id'])) {
                    $db->execute(
                        "UPDATE drivers SET status = 'available', updated_at = NOW() WHERE driver_id = ?",
                        [$rental['driver_id']]
                    );
                }

                $_SESSION['success_message'] = 'Vehicle returned successfully. Rental closed.';
                header('Location: view.php?id=' . $rentalId);
                exit;
            } catch (Exception $e) {
                error_log("Check-in failed: " . $e->getMessage());
                $errors[] = 'Failed to confirm return. Please try again.';
            }
        }
    }
}

$rentalDays = max(1, ceil((strtotime($scheduledEnd) - strtotime($rental['rental_start_date'])) / 86400));
$baseAmount = $rentalDays * $rental['daily_rate'];

$pageTitle = 'Check-In — ' . $rental['agreement_number'];
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-7xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors whitespace-nowrap">Rentals</a>
        <span class="text-secondary-200">/</span>
        <a href="view.php?id=<?= $rentalId ?>"
            class="text-secondary-400 hover:text-primary-600 transition-colors whitespace-nowrap"><?= htmlspecialchars($rental['agreement_number']) ?></a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600 break-words">Check-In</span>
    </div>

    <?php if ($isLate): ?>
        <div
            class="flex items-center gap-3 p-4 mb-6 bg-danger-50 border border-danger-200 rounded-2xl text-danger-700 text-xs font-bold">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            This rental is <strong style="color: var(--danger-900);"><?= $lateDays ?> day(s) overdue</strong>. A late fee
            has been pre-calculated.
        </div>
    <?php endif; ?>

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

    <form method="POST" id="checkinForm">
        <?= csrfField() ?>
        <!-- Main Layout Grid -->
        <div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
            <!-- Summary / Auth Card (Left Column) -->
            <div class="flex flex-col gap-6">
                <!-- Vehicle Card -->
                <div class="card" style="text-align: center;">
                    <div class="card-body">
                        <div
                            style="width: 120px; height: 120px; border-radius: 50%; background: var(--success); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; margin: 0 auto var(--space-4);">
                            <i data-lucide="car-front" style="width: 60px; height: 60px;"></i>
                        </div>
                        <h2 style="margin-bottom: var(--space-2);">
                            <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?>
                        </h2>
                        <p
                            style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                            <?= htmlspecialchars($rental['plate_number']) ?>
                        </p>

                        <div
                            style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
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
                                <span style="color: var(--text-secondary);">Expected</span>
                                <strong><?= formatDate($rental['rental_end_date']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Final Settlement Card -->
                <div class="card" style="border-top: 4px solid var(--success);">
                    <div class="card-body">
                        <h2 style="margin-bottom: var(--space-4); margin-top: 0; font-size: 1rem;">Final Settlement</h2>

                        <div
                            style="display: flex; flex-direction: column; gap: 12px; font-size: 0.875rem; margin-bottom: var(--space-4);">
                            <div
                                style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">
                                <span style="color: var(--text-secondary);">Base (<?= $rentalDays ?>d)</span>
                                <strong><?= CURRENCY_SYMBOL ?><?= number_format($baseAmount, 2) ?></strong>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">
                                <span style="color: var(--text-secondary);">Security Deposit</span>
                                <strong><?= CURRENCY_SYMBOL ?><?= number_format($rental['security_deposit'], 2) ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-top: 8px;">
                                <span style="font-weight: bold; font-size: 1rem;">Total Due</span>
                                <span id="total_display"
                                    style="font-weight: 900; font-size: 1.25rem; color: var(--success);"><?= CURRENCY_SYMBOL ?>0.00</span>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                            <button type="button" id="btn-confirm-checkin" onclick="confirmCheckIn(event)" class="btn btn-success"
                                style="justify-content: center;">
                                <i data-lucide="log-in" class="w-4 h-4"></i> Confirm Return
                            </button>
                            <a href="view.php?id=<?= $rentalId ?>" class="btn btn-secondary"
                                style="justify-content: center;">
                                <i data-lucide="x" class="w-4 h-4"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Forms (Right Column) -->
            <div class="flex flex-col gap-6">
                <!-- Return Odometer -->
                <div class="card">
                    <div class="card-body">
                        <h2
                            style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="gauge" style="color: var(--primary);"></i> Return Odometer
                        </h2>

                        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: var(--space-2);">
                                    Start Mileage
                                </label>
                                <div
                                    style="padding: 12px; background: var(--bg-muted); border-radius: var(--radius-md); font-size: 1.25rem; font-weight: bold; text-align: center; color: var(--text-secondary); border: 1px solid var(--border-color);">
                                    <?= number_format($rental['mileage_at_pickup'] ?? 0) ?> km
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: var(--space-2);">
                                    End Mileage (km) <span style="color: var(--danger);">*</span>
                                </label>
                                <input type="number" name="end_mileage" id="end_mileage"
                                    min="<?= $rental['mileage_at_pickup'] ?? 0 ?>"
                                    value="<?= $_POST['end_mileage'] ?? '' ?>" required class="form-control"
                                    style="font-size: 1.25rem; font-weight: bold;" oninput="recalc()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Charges & Notes -->
                <div class="card">
                    <div class="card-body">
                        <h2
                            style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="banknote" style="color: var(--success);"></i> Additional Charges
                        </h2>

                        <div class="grid"
                            style="grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: var(--space-2);">
                                    Damage Fee (<?= CURRENCY_SYMBOL ?>)
                                </label>
                                <input type="number" name="damage_fee" id="damage_fee" min="0" step="0.01"
                                    value="<?= $_POST['damage_fee'] ?? '0' ?>" class="form-control"
                                    style="font-weight: bold;" oninput="recalc()">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: var(--space-2);">
                                    Late Fee (<?= CURRENCY_SYMBOL ?>)
                                </label>
                                <input type="number" name="late_fee" id="late_fee" min="0" step="0.01"
                                    value="<?= $_POST['late_fee'] ?? ($lateDays * ($rental['daily_rate'] ?? 0)) ?>"
                                    class="form-control" style="font-weight: bold;" oninput="recalc()">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: var(--space-2);">
                                    Fuel Charge (<?= CURRENCY_SYMBOL ?>)
                                </label>
                                <input type="number" name="fuel_charge" id="fuel_charge" min="0" step="0.01"
                                    value="<?= $_POST['fuel_charge'] ?? '0' ?>" class="form-control"
                                    style="font-weight: bold;" oninput="recalc()">
                            </div>
                        </div>

                        <div class="form-group"
                            style="margin-bottom: 0; padding-top: var(--space-4); border-top: 1px solid var(--border-color);">
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: var(--space-2);">
                                Return Notes
                            </label>
                            <textarea name="return_notes" rows="3" class="form-control" style="resize: none;"
                                placeholder="Condition notes, damage description, etc."><?= htmlspecialchars($_POST['return_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Post-Rental Documentation -->
                <div class="card">
                    <div class="card-body">
                        <h2 style="margin-bottom:var(--space-4);margin-top:0;display:flex;align-items:center;gap:8px;">
                            <i data-lucide="camera" style="color:var(--primary);"></i> Post-Rental Documentation
                        </h2>
                        <div style="font-size:.875rem;color:var(--text-secondary);margin-bottom:1rem;">
                            Upload clear photos of the vehicle's exterior and interior upon return, especially to
                            document new damage not present during dispatch.
                        </div>
                        <div id="photoUploadDropzone"
                            style="border:2px dashed var(--border-color);border-radius:var(--radius-md);padding:2rem;text-align:center;background:var(--bg-muted);cursor:pointer;transition:all .2s;">
                            <i data-lucide="upload-cloud"
                                style="width:40px;height:40px;color:var(--primary);margin-bottom:1rem;"></i>
                            <div style="font-weight:600;margin-bottom:.5rem;">Click or drag images here to upload</div>
                            <div style="font-size:.75rem;color:var(--text-muted);">Supports JPG, PNG, WebP up to 5MB
                                each</div>
                            <input type="file" id="damagePhotos" accept="image/jpeg,image/png,image/webp" multiple
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
    const BASE = <?= (float) $baseAmount ?>, DEP = <?= (float) $rental['security_deposit'] ?>;
    function recalc() {
        const dmg = parseFloat(document.getElementById('damage_fee').value) || 0;
        const late = parseFloat(document.getElementById('late_fee').value) || 0;
        const fuel = parseFloat(document.getElementById('fuel_charge').value) || 0;
        const total = BASE + DEP + dmg + late + fuel;
        document.getElementById('total_display').textContent = '<?= CURRENCY_SYMBOL ?>' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    recalc();
    lucide.createIcons();

    // Photo Upload Handler (Post-Rental)
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
            if (!file.type.match('image.*')) return alert('Only images are allowed.');
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
                thumb.innerHTML = `<img src="${d.path}" style="width:100%;height:100%;object-fit:cover;">
                                   <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.6);color:#fff;font-size:10px;text-align:center;padding:2px;">Returned</div>`;
            })
            .catch(e => {
                thumb.innerHTML = `<i data-lucide="alert-circle" style="color:var(--danger)"></i>`;
                lucide.createIcons({ root: thumb });
                alert('Upload failed: ' + e.message);
            });
    }

    // Modal Confirmation for Check-In
    function confirmCheckIn(event) {
        event.preventDefault();

        const endMileageInput = document.getElementById('end_mileage');
        if (!endMileageInput.value || parseFloat(endMileageInput.value) < parseFloat(endMileageInput.min)) {
            endMileageInput.classList.add('is-invalid');
            endMileageInput.focus();
            // Remove invalid style after correction
            endMileageInput.addEventListener('input', function() {
                endMileageInput.classList.remove('is-invalid');
            }, { once: true });
            return;
        }

        const totalText = document.getElementById('total_display').textContent;

        if (typeof openGcrModal === 'function') {
            openGcrModal(
                'Confirm Vehicle Return',
                'This will finalize the rental and mark the vehicle as <strong>AVAILABLE</strong>. ' +
                'Total settlement: <strong>' + totalText + '</strong>. This action cannot be undone.',
                function () {
                    document.getElementById('checkinForm').submit();
                },
                {
                    variant: 'success',
                    confirmLabel: 'Yes, Confirm Return',
                    icon: 'log-in'
                }
            );
        } else {
            if (confirm('Confirm vehicle return? Total: ' + totalText + '. This action cannot be undone.')) {
                document.getElementById('checkinForm').submit();
            }
        }
    }
</script>
<?php require_once '../../includes/footer.php'; ?>