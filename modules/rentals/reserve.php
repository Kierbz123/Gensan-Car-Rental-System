<?php
// modules/rentals/reserve.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('rentals.create');

$db = Database::getInstance();

// Load data for form dropdowns
$vehicles = $db->fetchAll("
    SELECT vehicle_id, plate_number, brand, model, year_model, daily_rental_rate, security_deposit_amount
    FROM vehicles
    WHERE current_status = ? AND deleted_at IS NULL
    ORDER BY brand, model",
    [VEHICLE_STATUS_AVAILABLE]
);

$customers = $db->fetchAll("
    SELECT customer_id, customer_code, first_name, last_name, email, phone_primary
    FROM customers
    WHERE deleted_at IS NULL AND is_blacklisted = 0
    ORDER BY first_name, last_name"
);

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        try {
            $rental = new RentalAgreement();
            $agreementId = $rental->create([
                'customer_id'      => $_POST['customer_id'],
                'vehicle_id'       => $_POST['vehicle_id'],
                'start_date'       => $_POST['start_date'],
                'end_date'         => $_POST['end_date'],
                'rental_rate'      => $_POST['rental_rate'],
                'security_deposit' => $_POST['security_deposit'],
                'pickup_location'  => $_POST['pickup_location'] ?? 'main_office',
                'return_location'  => $_POST['return_location'] ?? 'main_office',
                'rental_type'      => $_POST['rental_type']   ?? 'self_drive',
                'driver_id'        => !empty($_POST['driver_id']) ? (int) $_POST['driver_id'] : null,
                'chauffeur_fee'    => (float) ($_POST['chauffeur_fee'] ?? 0),
            ], $authUser->getId());

            $_SESSION['success_message'] = "Booking confirmed successfully.";
            header("Location: view.php?id={$agreementId}");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
}
}

$pageTitle = "New Rental Booking";
require_once '../../includes/header.php';
?>

<div>
    <div class="page-header">
        <div class="page-title">
            <h1>New Rental Booking</h1>
            <p>Create a new vehicle rental reservation for a registered client.</p>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Rentals
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div style="margin-bottom: 2rem; padding: 1rem; background: var(--danger-light); color: var(--danger); border-radius: var(--radius-md); font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
            <i data-lucide="alert-circle" style="width:18px;height:18px; flex-shrink: 0;"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="reserveForm">
        <?php echo csrfField(); ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">

            <!-- LEFT: Main form -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">

                <!-- Client Selection -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="user" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i> Client Profile</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="customer_id">Select Client <span style="color:var(--danger)">*</span></label>
                        <select name="customer_id" id="customer_id" required class="form-control">
                            <option value="">— Select registered client —</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['customer_id']; ?>"
                                    data-code="<?php echo htmlspecialchars($c['customer_code']); ?>"
                                    <?php echo (($_POST['customer_id'] ?? '') == $c['customer_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ' — ' . $c['customer_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($customers)): ?>
                            <p style="font-size: 0.85rem; color: var(--warning); margin-top: 0.5rem;">No eligible clients found. <a href="../customers/customer-add.php" style="text-decoration: underline;">Register a client first.</a></p>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Selection -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="car" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i> Deployment Unit</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="vehicle_id">Select Available Vehicle <span style="color:var(--danger)">*</span></label>
                            <select name="vehicle_id" id="vehicle_id" required class="form-control"
                                    onchange="updateVehicleInfo(this)">
                            <option value="">— Select available vehicle —</option>
                            <?php foreach ($vehicles as $v): 
                                $selectedV = $_POST['vehicle_id'] ?? $_GET['vehicle_id'] ?? '';
                            ?>
                                <option value="<?php echo $v['vehicle_id']; ?>"
                                    data-rate="<?php echo $v['daily_rental_rate']; ?>"
                                    data-deposit="<?php echo $v['security_deposit_amount']; ?>"
                                    <?php echo ($selectedV == $v['vehicle_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year_model']}) — {$v['plate_number']}"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                            <?php if (empty($vehicles)): ?>
                                <p style="font-size: 0.85rem; color: var(--warning); margin-top: 0.5rem;">No vehicles currently available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Rental Period -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="calendar" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i> Operation Window</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-row" style="margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label for="start_date">Pickup Date <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="start_date" id="start_date" required class="form-control"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>"
                                       onchange="recalculate()">
                            </div>
                            <div class="form-group">
                                <label for="end_date">Return Date <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="end_date" id="end_date" required class="form-control"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo htmlspecialchars($_POST['end_date'] ?? date('Y-m-d', strtotime('+1 day'))); ?>"
                                       onchange="recalculate()">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="pickup_location">Pickup Location</label>
                                <select name="pickup_location" id="pickup_location" class="form-control">
                                    <option value="main_office">Main Office</option>
                                    <option value="airport">Airport</option>
                                    <option value="hotel_delivery">Hotel Delivery</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="return_location">Return Location</label>
                                <select name="return_location" id="return_location" class="form-control">
                                    <option value="main_office">Main Office</option>
                                    <option value="airport">Airport</option>
                                    <option value="hotel_pickup">Hotel Pickup</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

                <!-- Rental Type Card -->
                <div class="card" id="rentalTypeCard">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="steering-wheel" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i> Rental Type</h2>
                    </div>
                    <div class="card-body">
                        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
                            <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;font-weight:600;">
                                <input type="radio" name="rental_type" id="type_self" value="self_drive"
                                       onchange="onRentalTypeChange()" checked>
                                <i data-lucide="car" style="width:16px;height:16px;color:var(--primary)"></i> Self-Drive
                            </label>
                            <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;font-weight:600;">
                                <input type="radio" name="rental_type" id="type_chauffeur" value="chauffeur"
                                       onchange="onRentalTypeChange()">
                                <i data-lucide="user-check" style="width:16px;height:16px;color:var(--primary)"></i> Chauffeur-Driven
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Driver Assignment Card (shown when chauffeur selected) -->
                <div class="card" id="driverAssignCard" style="display:none;border-top:3px solid var(--primary);">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="user-check" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i> Driver Assignment</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="driver_id">Assign Available Driver <span style="color:var(--danger)">*</span></label>
                            <select name="driver_id" id="driver_id" class="form-control">
                                <option value="">— Select dates first, then choose driver —</option>
                            </select>
                            <p id="driverLoadNote" style="font-size:.8rem;color:var(--text-muted);margin-top:.4rem;">Select pickup and return dates to load available drivers.</p>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="chauffeur_fee">Daily Chauffeur Fee (<?= CURRENCY_SYMBOL ?>)</label>
                            <input type="number" name="chauffeur_fee" id="chauffeur_fee" class="form-control"
                                   min="0" step="0.01" value="0" oninput="recalculate()" placeholder="0.00">
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT: Summary Panel -->
            <div style="position: sticky; top: 2rem;">
                <div class="card" style="background: var(--bg-main);">
                    <div class="card-header">
                        <h2 class="card-title">Booking Summary</h2>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Daily Rate</span>
                                <span id="summary_rate" style="font-weight: 700;">₱0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Days</span>
                                <span id="summary_days" style="font-weight: 700;">0</span>
                            </div>
                            <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-muted); font-weight: 700; font-size: 0.95rem;">Subtotal</span>
                                <span id="summary_subtotal" style="font-weight: 800; font-size: 1.1rem;">₱0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem; margin-top: 0.5rem;">
                                <span style="color: var(--text-muted); font-weight: 500;">Security Deposit</span>
                                <span id="summary_deposit" style="font-weight: 700;">₱0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem;" id="chauffeur_fee_row" style="display:none;">
                                <span style="color: var(--text-muted); font-weight: 500;">Chauffeur Fee</span>
                                <span id="summary_chauffeur" style="font-weight: 700;">₱0.00</span>
                            </div>
                            <div style="border-top: 1px dashed var(--border-color); padding-top: 1rem; margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 800; font-size: 1rem; text-transform: uppercase;">Total Due</span>
                                <span id="summary_total" style="font-weight: 800; font-size: 1.5rem; color: var(--primary);">₱0.00</span>
                            </div>
                        </div>

                        <!-- Hidden rate inputs -->
                        <input type="hidden" name="rental_rate" id="rental_rate" value="0">
                        <input type="hidden" name="security_deposit" id="security_deposit" value="0">

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1rem;">
                            <i data-lucide="calendar-check" style="width:18px;height:18px;"></i> Confirm Booking
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
function formatPHP(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateVehicleInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    const rate    = parseFloat(opt.dataset.rate    || 0);
    const deposit = parseFloat(opt.dataset.deposit || 0);
    document.getElementById('rental_rate').value    = rate;
    document.getElementById('security_deposit').value = deposit;
    document.getElementById('summary_rate').textContent    = formatPHP(rate);
    document.getElementById('summary_deposit').textContent = formatPHP(deposit);
    recalculate();
}

function recalculate() {
    const start       = new Date(document.getElementById('start_date').value);
    const end         = new Date(document.getElementById('end_date').value);
    const rate        = parseFloat(document.getElementById('rental_rate').value || 0);
    const deposit     = parseFloat(document.getElementById('security_deposit').value || 0);
    const chauffeurFee = parseFloat(document.getElementById('chauffeur_fee')?.value || 0);
    const isChauffeur = document.getElementById('type_chauffeur')?.checked;

    if (isNaN(start) || isNaN(end) || end <= start) {
        document.getElementById('summary_days').textContent     = '0';
        document.getElementById('summary_subtotal').textContent = formatPHP(0);
        document.getElementById('summary_total').textContent    = formatPHP(0);
        return;
    }

    const days         = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
    const subtotal     = days * rate;
    const chauffeurTotal = isChauffeur ? (chauffeurFee * days) : 0;
    const total        = subtotal + deposit + chauffeurTotal;

    document.getElementById('summary_days').textContent     = days;
    document.getElementById('summary_subtotal').textContent = formatPHP(subtotal);
    document.getElementById('summary_total').textContent    = formatPHP(total);
    const cRow = document.getElementById('chauffeur_fee_row');
    const cSpan = document.getElementById('summary_chauffeur');
    if (cRow && cSpan) {
        cRow.style.display  = isChauffeur ? 'flex' : 'none';
        cSpan.textContent   = formatPHP(chauffeurTotal);
    }

    // Load available drivers when dates & chauffeur type selected
    if (isChauffeur) loadAvailableDrivers();
}

function onRentalTypeChange() {
    const isChauffeur = document.getElementById('type_chauffeur')?.checked;
    const card = document.getElementById('driverAssignCard');
    if (card) card.style.display = isChauffeur ? '' : 'none';
    if (isChauffeur) loadAvailableDrivers();
    recalculate();
}

function loadAvailableDrivers() {
    const start = document.getElementById('start_date').value;
    const end   = document.getElementById('end_date').value;
    if (!start || !end) return;
    const sel = document.getElementById('driver_id');
    const note = document.getElementById('driverLoadNote');
    if (!sel) return;
    sel.innerHTML = '<option value="">Loading…</option>';
    fetch(`<?= BASE_URL ?>modules/rentals/ajax/get-available-drivers.php?start_date=${start}&end_date=${end}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { sel.innerHTML = '<option value="">Error loading drivers</option>'; return; }
            if (!data.drivers.length) {
                sel.innerHTML = '<option value="">No available drivers for these dates</option>';
                if (note) note.textContent = 'All drivers are booked for this period.';
                return;
            }
            sel.innerHTML = '<option value="">— Select a driver —</option>';
            data.drivers.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.driver_id;
                opt.textContent = `${d.full_name} — ${d.license_type === 'professional' ? 'Professional' : 'Non-Professional'} (Lic: ${d.license_number})`;
                sel.appendChild(opt);
            });
            if (note) note.textContent = `${data.drivers.length} driver(s) available.`;
        })
        .catch(() => { sel.innerHTML = '<option value="">Failed to load drivers</option>'; });
}

// Init on load
recalculate();
lucide.createIcons();
// Wire date change to driver reload
document.getElementById('start_date')?.addEventListener('change', () => { if (document.getElementById('type_chauffeur')?.checked) loadAvailableDrivers(); recalculate(); });
document.getElementById('end_date')?.addEventListener('change', () => { if (document.getElementById('type_chauffeur')?.checked) loadAvailableDrivers(); recalculate(); });
</script>

<?php require_once '../../includes/footer.php'; ?>
