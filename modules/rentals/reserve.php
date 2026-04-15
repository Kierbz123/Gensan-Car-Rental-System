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

// Build a keyed lookup map for fast server-side rate verification
$vehicleMap = [];
foreach ($vehicles as $v) {
    $vehicleMap[$v['vehicle_id']] = $v;
}

$customers = $db->fetchAll("
    SELECT customer_id, customer_code, first_name, last_name, email, phone_primary
    FROM customers
    WHERE deleted_at IS NULL AND is_blacklisted = 0
    ORDER BY first_name, last_name"
);

$error      = '';
$success    = '';
$createdIds = []; // holds agreement IDs created in this booking

// ─── POST handler ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {

        // --- Shared fields ---
        $postCustomerId = trim($_POST['customer_id'] ?? '');
        $postStartDate  = trim($_POST['start_date']  ?? '');
        $postEndDate    = trim($_POST['end_date']    ?? '');
        $postPickupLoc  = trim($_POST['pickup_location'] ?? 'main_office');
        $postReturnLoc  = trim($_POST['return_location'] ?? 'main_office');
        $postRentalType = trim($_POST['rental_type'] ?? 'self_drive');

        // --- Vehicle rows submitted as arrays ---
        // vehicles_selected[] = vehicle_id
        // driver_ids[]        = driver_id (per vehicle, empty string = no driver)
        // chauffeur_fees[]    = daily fee (per vehicle)
        $postedVehicleIds   = array_values((array)($_POST['vehicles_selected'] ?? []));
        $postedDriverIds    = array_values((array)($_POST['driver_ids'] ?? []));
        $postedChauffeurFees = array_values((array)($_POST['chauffeur_fees'] ?? []));

        // --- Enum whitelists ---
        $allowedLocations  = ['main_office', 'airport', 'hotel_delivery', 'hotel_pickup', 'other'];
        $allowedRentalType = ['self_drive', 'chauffeur'];
        if (!in_array($postRentalType, $allowedRentalType, true)) $postRentalType = 'self_drive';
        if (!in_array($postPickupLoc, $allowedLocations, true))   $postPickupLoc  = 'main_office';
        if (!in_array($postReturnLoc, $allowedLocations, true))   $postReturnLoc  = 'main_office';

        // --- Customer validation ---
        $validCustomerIds = array_column($customers, 'customer_id');
        if (empty($postCustomerId) || !in_array($postCustomerId, $validCustomerIds)) {
            $error = 'Please select a valid registered client.';
        }

        // --- At least one vehicle required ---
        if (!$error && empty($postedVehicleIds)) {
            $error = 'Please add at least one vehicle to the booking.';
        }

        // --- Date validation ---
        if (!$error) {
            $dStart = DateTime::createFromFormat('Y-m-d', $postStartDate);
            $dEnd   = DateTime::createFromFormat('Y-m-d', $postEndDate);
            $today  = new DateTime('today');
            if (!$dStart || !$dEnd) {
                $error = 'Invalid date format.';
            } elseif ($dStart < $today) {
                $error = 'Pickup date cannot be in the past.';
            } elseif ($dEnd <= $dStart) {
                $error = 'Return date must be after pickup date.';
            }
        }

        // --- Per-vehicle whitelist + duplicate check ---
        $selectedVehicles = []; // validated rows to pass to create()
        if (!$error) {
            $seenVehicleIds = [];
            foreach ($postedVehicleIds as $idx => $rawVid) {
                $vid = trim($rawVid);
                if (!isset($vehicleMap[$vid])) {
                    $error = "Vehicle #{$vid} is not available or does not exist.";
                    break;
                }
                if (in_array($vid, $seenVehicleIds)) {
                    $vRow = $vehicleMap[$vid];
                    $error = "Vehicle \"{$vRow['brand']} {$vRow['model']} — {$vRow['plate_number']}\" was selected more than once.";
                    break;
                }
                $seenVehicleIds[] = $vid;
                $driverId    = !empty($postedDriverIds[$idx])    ? (int)$postedDriverIds[$idx]    : null;
                $chauffFee   = isset($postedChauffeurFees[$idx]) ? (float)$postedChauffeurFees[$idx] : 0.0;
                
                // Scrub data based on rental type to prevent front-end bypasses
                if ($postRentalType === 'self_drive') {
                    $driverId = null;
                    $chauffFee = 0.0;
                } elseif ($postRentalType === 'chauffeur' && !$driverId) {
                    $vRow = $vehicleMap[$vid];
                    $error = "Please assign a driver for \"{$vRow['brand']} {$vRow['model']}\" (Chauffeur mode requires it).";
                    break;
                }
                if ($chauffFee < 0) $chauffFee = 0.0;

                $selectedVehicles[] = [
                    'vehicle_id'    => $vid,
                    'driver_id'     => $driverId,
                    'chauffeur_fee' => $chauffFee,
                    'vehicle_row'   => $vehicleMap[$vid],
                ];
            }
        }

        // --- Create one agreement per vehicle in a single transaction ---
        if (!$error) {
            $rental = new RentalAgreement();
            $db->beginTransaction();
            try {
                foreach ($selectedVehicles as $vEntry) {
                    $vRow = $vEntry['vehicle_row'];
                    $id = $rental->createInTransaction([
                        'customer_id'      => $postCustomerId,
                        'vehicle_id'       => $vEntry['vehicle_id'],
                        'start_date'       => $postStartDate,
                        'end_date'         => $postEndDate,
                        'rental_rate'      => (float)$vRow['daily_rental_rate'],
                        'security_deposit' => (float)$vRow['security_deposit_amount'],
                        'pickup_location'  => $postPickupLoc,
                        'return_location'  => $postReturnLoc,
                        'rental_type'      => $postRentalType,
                        'driver_id'        => $vEntry['driver_id'],
                        'chauffeur_fee'    => $vEntry['chauffeur_fee'],
                    ], $authUser->getId());
                    $createdIds[] = $id;
                }
                $db->commit();

                // Audit once per booking batch
                if (class_exists('AuditLogger')) {
                    AuditLogger::log(
                        $authUser->getId(), null, null,
                        'create', 'rentals', 'rental_agreements', null,
                        'Fleet booking: created ' . count($createdIds) . ' agreement(s) for customer #' . $postCustomerId,
                        null,
                        json_encode(['agreement_ids' => $createdIds, 'vehicle_count' => count($createdIds)]),
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'POST', '/rentals/reserve', 'info'
                    );
                }

                $vehicleWord = count($createdIds) === 1 ? 'vehicle' : 'vehicles';
                $_SESSION['success_message'] = count($createdIds) . " {$vehicleWord} booked successfully.";
                // Redirect to first agreement; others accessible via customer profile
                header("Location: view.php?id={$createdIds[0]}");
                exit;

            } catch (Exception $e) {
                $db->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'New Rental Booking';
require_once '../../includes/header.php';

// Build JS-safe vehicle data for live summary
$vehicleJsonMap = json_encode(array_map(fn($v) => [
    'id'      => $v['vehicle_id'],
    'label'   => "{$v['brand']} {$v['model']} ({$v['year_model']}) — {$v['plate_number']}",
    'rate'    => (float)$v['daily_rental_rate'],
    'deposit' => (float)$v['security_deposit_amount'],
], $vehicles), JSON_HEX_TAG);
?>

<style>
.fleet-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: .75rem;
    align-items: center;
    padding: .75rem;
    background: var(--bg-body, #f4f6f8);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-bottom: .5rem;
    transition: box-shadow .15s;
}
.fleet-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.fleet-row .vehicle-badge {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .8rem; font-weight: 700; color: var(--primary);
    background: rgba(59,130,246,.1); padding: .25rem .6rem;
    border-radius: var(--radius-full);
}
.fleet-row .fleet-rate {
    font-size: .85rem; font-weight: 700; color: var(--text-muted); white-space: nowrap;
}
#fleet-empty {
    padding: 2rem; text-align: center; color: var(--text-muted);
    border: 2px dashed var(--border-color); border-radius: var(--radius-md);
    font-size: .9rem;
}
.summary-vehicle-line {
    display: flex; justify-content: space-between; align-items: center;
    padding: .4rem 0; border-bottom: 1px dashed var(--border-color);
    font-size: .85rem;
}
.summary-vehicle-line:last-child { border-bottom: none; }
</style>

<div>
    <div class="page-header">
        <div class="page-title">
            <h1><i data-lucide="calendar-plus" style="width:24px;height:24px;vertical-align:-5px;margin-right:8px;color:var(--primary)"></i>New Rental Booking</h1>
            <p>Reserve one or more vehicles for a registered client under a single booking window.</p>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Rentals
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div style="margin-bottom:2rem;padding:1rem 1.25rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-weight:500;display:flex;align-items:flex-start;gap:.6rem;">
            <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;margin-top:2px;"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" id="reserveForm">
        <?= csrfField() ?>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:2rem;align-items:start;">

            <!-- ── LEFT COLUMN ── -->
            <div style="display:flex;flex-direction:column;gap:2rem;">

                <!-- 1. Client -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="user" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>Client Profile</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="customer_id">Select Client <span style="color:var(--danger)">*</span></label>
                            <select name="customer_id" id="customer_id" required class="form-control">
                                <option value="">— Select registered client —</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['customer_id'] ?>"
                                        data-code="<?= htmlspecialchars($c['customer_code']) ?>"
                                        <?= (($_POST['customer_id'] ?? '') == $c['customer_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ' — ' . $c['customer_code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($customers)): ?>
                                <p style="font-size:.85rem;color:var(--warning);margin-top:.5rem;">
                                    No eligible clients found.
                                    <a href="../customers/customer-add.php" style="text-decoration:underline;">Register a client first.</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 2. Operation Window (shared across all vehicles) -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="calendar" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>Operation Window</h2>
                        <span style="font-size:.78rem;color:var(--text-muted);font-weight:500;">Applies to all vehicles in this booking</span>
                    </div>
                    <div class="card-body">
                        <div class="form-row" style="margin-bottom:1.5rem;">
                            <div class="form-group">
                                <label for="start_date">Pickup Date <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="start_date" id="start_date" required class="form-control"
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>"
                                       onchange="onDateChange()">
                            </div>
                            <div class="form-group">
                                <label for="end_date">Return Date <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="end_date" id="end_date" required class="form-control"
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                       value="<?= htmlspecialchars($_POST['end_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>"
                                       onchange="onDateChange()">
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

                <!-- 3. Rental Type -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="steering-wheel" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>Rental Type</h2>
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
                        <p id="chauffeur-note" style="display:none;margin-top:.75rem;font-size:.82rem;color:var(--text-muted);">
                            <i data-lucide="info" style="width:13px;height:13px;vertical-align:-2px;"></i>
                            Each vehicle in chauffeur mode requires a separate driver assignment below.
                        </p>
                    </div>
                </div>

                <!-- 4. Fleet Builder — the multi-vehicle section -->
                <div class="card" style="border-top:3px solid var(--primary);">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i data-lucide="layers" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>
                            Fleet Selection
                            <span id="fleet-count-badge" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:var(--primary);color:#fff;border-radius:50%;font-size:.75rem;font-weight:800;margin-left:.5rem;">0</span>
                        </h2>
                    </div>
                    <div class="card-body">
                        <!-- Vehicle picker (not submitted — used to add rows) -->
                        <div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1.25rem;">
                            <div style="flex:1;">
                                <select id="vehicle-picker" class="form-control">
                                    <option value="">— Select a vehicle to add —</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= $v['vehicle_id'] ?>"
                                            data-rate="<?= (float)$v['daily_rental_rate'] ?>"
                                            data-deposit="<?= (float)$v['security_deposit_amount'] ?>"
                                            data-label="<?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year_model']}) — {$v['plate_number']}") ?>">
                                            <?= htmlspecialchars("{$v['brand']} {$v['model']} ({$v['year_model']}) — {$v['plate_number']}") ?>
                                            · ₱<?= number_format($v['daily_rental_rate'], 2) ?>/day
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="addFleetRow()" style="white-space:nowrap;">
                                <i data-lucide="plus-circle" style="width:15px;height:15px;"></i> Add
                            </button>
                        </div>

                        <!-- Dynamic fleet rows container -->
                        <div id="fleet-list">
                            <div id="fleet-empty">
                                <i data-lucide="car" style="width:32px;height:32px;opacity:.3;margin-bottom:.5rem;display:block;margin-inline:auto;"></i>
                                No vehicles added yet. Use the picker above to build your fleet.
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /left column -->

            <!-- ── RIGHT COLUMN – Booking Summary ── -->
            <div style="position:sticky;top:2rem;">
                <div class="card" style="background:var(--bg-main);">
                    <div class="card-header">
                        <h2 class="card-title">Booking Summary</h2>
                    </div>
                    <div class="card-body">

                        <!-- Per-vehicle breakdown -->
                        <div id="summary-vehicles" style="margin-bottom:1rem;display:flex;flex-direction:column;gap:0;"></div>

                        <div style="display:flex;flex-direction:column;gap:.75rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:.9rem;">
                                <span style="color:var(--text-muted);font-weight:500;">Vehicles</span>
                                <span id="sum-vehicle-count" style="font-weight:700;">0</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:.9rem;">
                                <span style="color:var(--text-muted);font-weight:500;">Rental Days</span>
                                <span id="sum-days" style="font-weight:700;">0</span>
                            </div>
                            <div style="border-top:1px solid var(--border-color);padding-top:.75rem;display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-muted);font-weight:700;font-size:.9rem;">Rental Subtotal</span>
                                <span id="sum-subtotal" style="font-weight:800;font-size:1.05rem;">₱0.00</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:.9rem;">
                                <span style="color:var(--text-muted);font-weight:500;">Total Deposits</span>
                                <span id="sum-deposits" style="font-weight:700;">₱0.00</span>
                            </div>
                            <div id="sum-chauffeur-row" style="display:none;justify-content:space-between;align-items:center;font-size:.9rem;">
                                <span style="color:var(--text-muted);font-weight:500;">Chauffeur Fees</span>
                                <span id="sum-chauffeur" style="font-weight:700;">₱0.00</span>
                            </div>
                            <div style="border-top:2px solid var(--primary);padding-top:.75rem;display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-weight:800;font-size:1rem;text-transform:uppercase;">Total Due</span>
                                <span id="sum-total" style="font-weight:800;font-size:1.5rem;color:var(--primary);">₱0.00</span>
                            </div>
                        </div>

                        <button type="submit" id="submit-btn" class="btn btn-primary"
                                style="width:100%;padding:1rem;font-size:1rem;margin-top:1.5rem;" disabled>
                            <i data-lucide="calendar-check" style="width:18px;height:18px;"></i>
                            Confirm Booking
                        </button>
                        <p id="submit-hint" style="font-size:.78rem;text-align:center;color:var(--text-muted);margin-top:.5rem;">
                            Add at least one vehicle to enable booking.
                        </p>

                    </div>
                </div>
            </div><!-- /right column -->

        </div>
    </form>
</div>

<script>
(function () {
'use strict';

// ── Config from PHP ──────────────────────────────────────────────────────────
const vehicleMap   = <?= $vehicleJsonMap ?>;
const BASE_URL     = '<?= BASE_URL ?>';
const CURRENCY_SYM = '₱';

// ── State ────────────────────────────────────────────────────────────────────
let fleetRows = [];  // [{vid, rate, deposit, driverFee}, …]

// ── Helpers ──────────────────────────────────────────────────────────────────
function fmt(n) {
    return CURRENCY_SYM + parseFloat(n || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function getDays() {
    const s = new Date(document.getElementById('start_date').value + 'T00:00:00');
    const e = new Date(document.getElementById('end_date').value   + 'T00:00:00');
    if (isNaN(s) || isNaN(e) || e <= s) return 0;
    return Math.ceil((e - s) / 86400000);
}

function isChauffeur() {
    return document.getElementById('type_chauffeur')?.checked === true;
}

// ── Summary recalculation ────────────────────────────────────────────────────
function recalculate() {
    const days = getDays();
    let totalRental   = 0;
    let totalDeposit  = 0;
    let totalChauffeur = 0;

    // Per-vehicle breakdown lines
    const summaryVehicles = document.getElementById('summary-vehicles');
    summaryVehicles.innerHTML = '';

    fleetRows.forEach(row => {
        const v = vehicleMap.find(x => x.id == row.vid);
        if (!v) return;
        const rental = days * v.rate;
        const chauffFee = isChauffeur() ? (days * (row.driverFee || 0)) : 0;
        totalRental   += rental;
        totalDeposit  += v.deposit;
        totalChauffeur += chauffFee;

        const line = document.createElement('div');
        line.className = 'summary-vehicle-line';
        line.innerHTML = `
            <span style="max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;font-size:.82rem;">
                ${v.label.split('—')[0].trim()}
            </span>
            <span style="font-weight:700;color:var(--primary);font-size:.82rem;">${fmt(rental + chauffFee)}</span>
        `;
        summaryVehicles.appendChild(line);
    });

    const grandTotal = totalRental + totalChauffeur;

    document.getElementById('sum-vehicle-count').textContent = fleetRows.length;
    document.getElementById('sum-days').textContent          = days;
    document.getElementById('sum-subtotal').textContent      = fmt(totalRental);
    document.getElementById('sum-deposits').textContent      = fmt(totalDeposit);
    document.getElementById('sum-chauffeur').textContent     = fmt(totalChauffeur);

    const chauffRow = document.getElementById('sum-chauffeur-row');
    if (chauffRow) chauffRow.style.display = isChauffeur() ? 'flex' : 'none';

    document.getElementById('sum-total').textContent = fmt(grandTotal + totalDeposit);
}

// ── Picker option visibility ─────────────────────────────────────────────────
// Disable options for vehicles already in the fleet so they can't be double-added.
function syncPickerOptions() {
    const picker = document.getElementById('vehicle-picker');
    const addedIds = new Set(fleetRows.map(r => String(r.vid)));
    Array.from(picker.options).forEach(opt => {
        if (!opt.value) return; // skip placeholder
        opt.disabled = addedIds.has(String(opt.value));
        opt.style.color = opt.disabled ? 'var(--text-muted, #999)' : '';
    });
    // If the currently-selected value was just disabled, reset to placeholder
    if (picker.value && addedIds.has(String(picker.value))) picker.value = '';
}

// ── Fleet row management ─────────────────────────────────────────────────────
window.addFleetRow = function () {
    const picker = document.getElementById('vehicle-picker');
    const vid    = picker.value;
    if (!vid) { picker.focus(); return; }

    // Prevent duplicates (guard, though picker option is already disabled)
    if (fleetRows.some(r => r.vid == vid)) {
        alert('This vehicle is already in the fleet list.');
        return;
    }

    const opt     = picker.options[picker.selectedIndex];
    const rate    = parseFloat(opt.dataset.rate    || 0);
    const deposit = parseFloat(opt.dataset.deposit || 0);

    const rowId = 'fleet-row-' + Date.now();
    // driverId: null until user picks one; isNew: true so AJAX loads drivers for this row
    fleetRows.push({ vid, rate, deposit, driverFee: 0, driverId: null, isNew: true, rowId });

    renderFleetList();
    picker.value = '';  // reset picker to placeholder
    syncPickerOptions();  // disable the option we just added
    recalculate();
    lucide.createIcons();
};

window.removeFleetRow = function (rowId) {
    fleetRows = fleetRows.filter(r => r.rowId !== rowId);
    renderFleetList();
    syncPickerOptions();  // re-enable the removed vehicle's option
    recalculate();
    lucide.createIcons();
};

window.updateDriverFee = function (rowId, val) {
    const row = fleetRows.find(r => r.rowId === rowId);
    if (row) { row.driverFee = parseFloat(val) || 0; recalculate(); }
};

function renderFleetList() {
    const container = document.getElementById('fleet-list');
    const emptyEl   = document.getElementById('fleet-empty');
    const badge      = document.getElementById('fleet-count-badge');
    const submitBtn  = document.getElementById('submit-btn');
    const hint       = document.getElementById('submit-hint');

    // Remove all existing rows (keep #fleet-empty)
    container.querySelectorAll('.fleet-row').forEach(el => el.remove());

    if (fleetRows.length === 0) {
        emptyEl.style.display = '';
        badge.textContent     = '0';
        submitBtn.disabled    = true;
        hint.style.display    = '';
        return;
    }

    emptyEl.style.display = 'none';
    badge.textContent     = fleetRows.length;
    submitBtn.disabled    = false;
    hint.style.display    = 'none';

    const showDriver = isChauffeur();

    fleetRows.forEach((row, idx) => {
        const v = vehicleMap.find(x => x.id == row.vid);
        if (!v) return;

        const div = document.createElement('div');
        div.className = 'fleet-row';
        div.id = row.rowId;

        // Saved driver ID from state (survives re-renders)
        const savedDriverId = row.driverId || '';

        div.innerHTML = `
            <!-- hidden inputs submitted with the form -->
            <input type="hidden" name="vehicles_selected[]" value="${v.id}">
            <input type="hidden" name="driver_ids[]"        value="${savedDriverId}" id="hidden-driver-${row.rowId}">
            <input type="hidden" name="chauffeur_fees[]"    value="${row.driverFee}" id="hidden-fee-${row.rowId}">

            <div>
                <div style="font-weight:700;font-size:.9rem;margin-bottom:.2rem;">${v.label}</div>
                <div class="vehicle-badge">
                    <i data-lucide="banknote" style="width:11px;height:11px;"></i>
                    ₱${v.rate.toLocaleString('en-PH',{minimumFractionDigits:2})}/day · Deposit ₱${v.deposit.toLocaleString('en-PH',{minimumFractionDigits:2})}
                </div>
            </div>

            ${showDriver ? `
            <div id="driver-slot-${row.rowId}" style="display:flex;flex-direction:column;gap:.3rem;min-width:180px;">
                <label style="font-size:.75rem;font-weight:700;color:var(--text-muted);margin:0;">Driver</label>
                <select class="form-control" style="font-size:.82rem;"
                        onchange="setDriverForRow('${row.rowId}', this.value)"
                        id="driver-sel-${row.rowId}">
                    <option value="">Loading…</option>
                </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:.3rem;min-width:120px;">
                <label style="font-size:.75rem;font-weight:700;color:var(--text-muted);margin:0;">Daily Driver Fee</label>
                <input type="number" class="form-control" style="font-size:.82rem;"
                       placeholder="0.00" min="0" step="0.01"
                       value="${row.driverFee}"
                       oninput="updateDriverFee('${row.rowId}', this.value)">
            </div>
            ` : `<span></span><span></span>`}

            <div style="display:flex;align-items:center;gap:.4rem;">
                <span class="fleet-rate" id="rate-${row.rowId}">—</span>
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="removeFleetRow('${row.rowId}')"
                        style="color:var(--danger);padding:.25rem .5rem;"
                        title="Remove vehicle">
                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                </button>
            </div>
        `;

        container.insertBefore(div, document.getElementById('fleet-empty'));
    });

    // Only load drivers for rows flagged as new (avoid wiping existing selections)
    if (showDriver) loadNewDriverSelects();

    // Update per-row rate display
    updateRowRates();
}

function updateRowRates() {
    const days = getDays();
    fleetRows.forEach(row => {
        const el = document.getElementById('rate-' + row.rowId);
        if (el) {
            const v = vehicleMap.find(x => x.id == row.vid);
            el.textContent = v ? fmt(days * v.rate) : '—';
        }
    });
}

// ── Driver loading ───────────────────────────────────────────────────────────
window.setDriverForRow = function(rowId, driverId) {
    // Persist into state so it survives re-renders
    const row = fleetRows.find(r => r.rowId === rowId);
    if (row) row.driverId = driverId || null;

    // Update the hidden driver_ids[] input
    const hiddenInput = document.getElementById('hidden-driver-' + rowId);
    if (hiddenInput) hiddenInput.value = driverId || '';

    // Refresh all OTHER rows so the chosen driver disappears from their lists
    syncAllDriverSelects(rowId);
};

// Returns a Set of driverIds currently assigned to other rows (exclude self).
function getUsedDriverIds(exceptRowId) {
    return new Set(
        fleetRows
            .filter(r => r.rowId !== exceptRowId && r.driverId)
            .map(r => String(r.driverId))
    );
}

// Shared: populate a single driver <select> with the available driver list,
// - disabling options already assigned to OTHER rows
// - restoring the saved driverId from state for THIS row.
function populateDriverSelect(sel, drivers, savedDriverId, usedDriverIds) {
    const used = usedDriverIds || new Set();
    if (!drivers || !drivers.length) {
        sel.innerHTML = '<option value="">No drivers available for these dates</option>';
        return;
    }
    sel.innerHTML = '<option value="">— Select driver —</option>';
    drivers.forEach(d => {
        const opt      = document.createElement('option');
        const dIdStr   = String(d.driver_id);
        const isUsed   = used.has(dIdStr) && dIdStr !== String(savedDriverId);
        opt.value      = d.driver_id;
        opt.textContent = `${d.full_name} (${d.license_type === 'professional' ? 'Pro' : 'Non-Pro'}) — Lic: ${d.license_number}`;
        opt.disabled   = isUsed;
        // Grey out disabled options visually for clarity
        if (isUsed) opt.style.color = 'var(--text-muted, #999)';
        // Restore previously picked driver
        if (dIdStr === String(savedDriverId)) opt.selected = true;
        sel.appendChild(opt);
    });
    // Sync hidden input with the now-selected value
    const rowId = sel.id.replace('driver-sel-', '');
    const hiddenInput = document.getElementById('hidden-driver-' + rowId);
    if (hiddenInput) hiddenInput.value = sel.value;
}

// Re-render driver selects for all rows EXCEPT the one that just changed.
// Only updates the disabled state of options — does NOT wipe/refetch anything.
function syncAllDriverSelects(changedRowId) {
    fleetRows.forEach(row => {
        if (row.rowId === changedRowId) return; // skip the row that triggered the change
        const sel = document.getElementById('driver-sel-' + row.rowId);
        if (!sel || sel.options.length <= 1) return; // not yet populated
        const usedIds = getUsedDriverIds(row.rowId);
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return; // skip placeholder
            const isUsed = usedIds.has(String(opt.value));
            opt.disabled   = isUsed;
            opt.style.color = isUsed ? 'var(--text-muted, #999)' : '';
        });
    });
}

// Only fetch for rows where the driver select still shows "Loading…" (new rows).
// Rows that already have options are left completely untouched.
function loadNewDriverSelects() {
    const newRows = fleetRows.filter(row => {
        const sel = document.getElementById('driver-sel-' + row.rowId);
        if (!sel) return false;
        // A row with only one option whose value is empty is still un-populated
        return sel.options.length === 1 && sel.options[0].value === '';
    });
    if (!newRows.length) return;

    const start = document.getElementById('start_date').value;
    const end   = document.getElementById('end_date').value;
    if (!start || !end) return;

    fetch(`${BASE_URL}modules/rentals/ajax/get-available-drivers.php?start_date=${start}&end_date=${end}`)
        .then(r => r.json())
        .then(data => {
            const drivers = (data.success && data.drivers) ? data.drivers : [];
            newRows.forEach(row => {
                const sel = document.getElementById('driver-sel-' + row.rowId);
                if (!sel) return;
                populateDriverSelect(sel, drivers, row.driverId, getUsedDriverIds(row.rowId));
                row.isNew = false;
            });
        })
        .catch(() => {
            newRows.forEach(row => {
                const sel = document.getElementById('driver-sel-' + row.rowId);
                if (sel) sel.innerHTML = '<option value="">Failed to load drivers</option>';
            });
        });
}

// Full reload for all rows (used when dates change — all driver lists may be stale).
function reloadAllDriverSelects() {
    const start = document.getElementById('start_date').value;
    const end   = document.getElementById('end_date').value;
    if (!start || !end) return;

    // Mark every row as new so loadNewDriverSelects will re-populate all of them
    fleetRows.forEach(row => {
        row.isNew = true;
        const sel = document.getElementById('driver-sel-' + row.rowId);
        if (sel) sel.innerHTML = '<option value="">Loading…</option>';
    });

    fetch(`${BASE_URL}modules/rentals/ajax/get-available-drivers.php?start_date=${start}&end_date=${end}`)
        .then(r => r.json())
        .then(data => {
            const drivers = (data.success && data.drivers) ? data.drivers : [];
            fleetRows.forEach(row => {
                const sel = document.getElementById('driver-sel-' + row.rowId);
                if (!sel) return;
                // Restore saved driver ID — user keeps their selection if driver is still available
                populateDriverSelect(sel, drivers, row.driverId, getUsedDriverIds(row.rowId));
                row.isNew = false;
            });
        })
        .catch(() => {
            fleetRows.forEach(row => {
                const sel = document.getElementById('driver-sel-' + row.rowId);
                if (sel) sel.innerHTML = '<option value="">Failed to load drivers</option>';
            });
        });
}

// ── Event wiring ─────────────────────────────────────────────────────────────
window.onDateChange = function () {
    recalculate();
    updateRowRates();
    // Dates changed — all driver availability lists are stale, reload everything
    if (isChauffeur() && fleetRows.length > 0) reloadAllDriverSelects();
};

window.onRentalTypeChange = function () {
    const isCh = isChauffeur();
    document.getElementById('chauffeur-note').style.display = isCh ? '' : 'none';
    // Mark all rows as new so driver selects are populated when chauffeur is turned on
    if (isCh) fleetRows.forEach(row => { row.isNew = true; });
    renderFleetList();  // re-render to show/hide driver columns
    recalculate();
    lucide.createIcons();
};

// Validate on submit: ensure each chauffeur vehicle has a driver selected
document.getElementById('reserveForm').addEventListener('submit', function (e) {
    if (fleetRows.length === 0) {
        e.preventDefault();
        alert('Please add at least one vehicle before confirming the booking.');
        return;
    }
    if (getDays() <= 0) {
        e.preventDefault();
        alert('Please select valid pickup and return dates.');
        return;
    }
    // Disable button to prevent double-submit
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-circle" style="width:16px;height:16px;animation:spin 1s linear infinite;"></i> Processing…';
    lucide.createIcons();
});

// ── Init ─────────────────────────────────────────────────────────────────────
recalculate();
lucide.createIcons();

const style = document.createElement('style');
style.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(style);

})();
</script>

<?php require_once '../../includes/footer.php'; ?>
