<?php
/**
 * Rental Agreement Detailed View
 * Path: modules/rentals/view.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/DocumentManager.php';

$authUser->requirePermission('rentals.view');

$db = Database::getInstance();

// ── Document upload handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid security token.';
    } elseif ($authUser->hasPermission('rentals.update')) {
        try {
            $rentalId = (int)($_POST['agreement_id'] ?? 0);
            $cat      = $_POST['document_category'] ?? 'other';
            $title    = !empty($_POST['document_title']) ? $_POST['document_title'] : null;
            $exp      = !empty($_POST['expires_at'])    ? $_POST['expires_at']     : null;
            DocumentManager::uploadDocument(
                $_FILES['document_file'], 'rental_agreement', $rentalId,
                $cat, $title, $authUser->getId(), $exp
            );
            $_SESSION['success_message'] = 'Document uploaded successfully.';
            header('Location: view.php?id=' . $rentalId);
            exit;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
}

// ── Flash messages ────────────────────────────────────────────────────────────
$success = '';
if (!empty($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// ── Load agreement ────────────────────────────────────────────────────────────
$rentalId = (int)($_GET['id'] ?? 0);
if (!$rentalId) {
    redirect('../../modules/rentals/index.php', 'Agreement ID is required.', 'error');
}

try {
    $rental = $db->fetchOne("
        SELECT ra.*,
               v.plate_number, v.brand, v.model, v.year_model, v.color, v.primary_photo_path,
               CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
               c.customer_code, c.phone_primary, c.email, c.address,
               CONCAT(d.first_name, ' ', d.last_name) AS driver_name,
               d.phone AS driver_phone, d.employee_code AS driver_code,
               d.license_number AS driver_license, d.license_expiry AS driver_license_expiry,
               d.driver_id AS driver_id_val
        FROM rental_agreements ra
        JOIN  vehicles  v ON ra.vehicle_id   = v.vehicle_id
        JOIN  customers c ON ra.customer_id  = c.customer_id
        LEFT JOIN drivers d ON ra.driver_id  = d.driver_id
        WHERE ra.agreement_id = ?
    ", [$rentalId]);

    if (!$rental) {
        redirect('../../modules/rentals/index.php', 'Rental agreement not found.', 'error');
    }

    $rentalDocs = DocumentManager::getDocumentsByEntity('rental_agreement', $rentalId);

    // Fleet siblings — same customer, same date window, different vehicle, not cancelled
    $fleetSiblings = $db->fetchAll("
        SELECT ra2.agreement_id, ra2.agreement_number, ra2.status,
               v2.brand, v2.model, v2.plate_number, ra2.total_amount
        FROM rental_agreements ra2
        JOIN vehicles v2 ON ra2.vehicle_id = v2.vehicle_id
        WHERE ra2.customer_id     = ?
          AND ra2.rental_start_date = ?
          AND ra2.rental_end_date   = ?
          AND ra2.agreement_id    != ?
          AND ra2.status NOT IN ('cancelled')
        ORDER BY ra2.agreement_id ASC
    ", [
        $rental['customer_id'],
        $rental['rental_start_date'],
        $rental['rental_end_date'],
        $rentalId,
    ]);

} catch (Exception $e) {
    redirect('../../modules/rentals/index.php', 'Database Error: ' . $e->getMessage(), 'error');
}

// ── Derived values ────────────────────────────────────────────────────────────
$amountPaid   = (float)($rental['amount_paid']   ?? 0);
$totalAmount  = (float) $rental['total_amount'];
$secDeposit   = (float)($rental['security_deposit'] ?? 0);
$balanceOwing = max(0, $totalAmount - $amountPaid);
$payStatus    = $rental['payment_status'] ?? 'pending';
$payColor     = match($payStatus) { 'fully_paid' => 'success', 'partial' => 'warning', default => 'danger' };
$payLabel     = match($payStatus) { 'fully_paid' => 'Fully Paid', 'partial'   => 'Partial',     default => 'Pending' };

$statusColors = [
    'active'    => 'var(--success)',
    'reserved'  => 'var(--info, #0ea5e9)',
    'confirmed' => 'var(--secondary-500, #64748b)',
    'returned'  => 'var(--text-muted)',
    'completed' => 'var(--text-muted)',
    'cancelled' => 'var(--danger)',
];
$statusColor = $statusColors[$rental['status']] ?? 'var(--text-muted)';

$rentalDays = max(1, (int) ceil(
    (strtotime($rental['rental_end_date']) - strtotime($rental['rental_start_date'])) / 86400
));

$locationLabels = [
    'main_office'    => 'Main Office',
    'airport'        => 'Airport',
    'hotel_delivery' => 'Hotel Delivery',
    'hotel_pickup'   => 'Hotel Pickup',
    'other'          => 'Other',
];

$pageTitle = 'Agreement: ' . $rental['agreement_number'];
require_once '../../includes/header.php';
?>

<style>
.view-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    align-items: start;
}
.detail-label {
    display: block;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--text-muted);
    font-weight: 700;
    margin-bottom: .25rem;
}
.detail-value {
    font-weight: 700;
    font-size: .925rem;
    color: var(--text-main);
    margin: 0;
}
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem 1.5rem;
}
.fin-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .5rem 0;
    border-bottom: 1px dashed var(--border-color);
    font-size: .9rem;
}
.fin-row:last-child { border-bottom: none; }
.fin-row .fin-label { color: var(--text-secondary); font-weight: 600; }
.fin-row .fin-value { font-weight: 800; font-family: monospace; font-size: 1rem; }
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 12px;
    border-radius: var(--radius-full);
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #fff;
}
.status-dot {
    width: 6px; height: 6px;
    background: #fff;
    border-radius: 50%;
    flex-shrink: 0;
}
.vehicle-3d-stage {
    perspective: 900px;
    width: 100%;
    height: 130px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
}
.vehicle-3d-card {
    width: 210px; height: 130px;
    border-radius: 12px;
    background: linear-gradient(135deg, #1e293b, #334155);
    box-shadow: 0 15px 40px rgba(0,0,0,.35), 0 4px 12px rgba(0,0,0,.2);
    transform-style: preserve-3d;
    animation: spin3d 8s linear infinite;
    overflow: hidden;
}
.vehicle-3d-card:hover { animation-play-state: paused; }
.vehicle-3d-card img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
.vehicle-3d-card .car-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    color: #94a3b8;
}
@keyframes spin3d {
    0%  { transform: rotateY(-25deg) rotateX(5deg); }
    50% { transform: rotateY(25deg)  rotateX(-5deg); }
    100%{ transform: rotateY(-25deg) rotateX(5deg); }
}
.sibling-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: .6rem .75rem;
    background: var(--bg-body, #f4f6f8);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-bottom: .4rem;
    font-size: .85rem;
}
@keyframes toastSlideIn {
    from { opacity:0; transform: translateX(60px) scale(.96); }
    to   { opacity:1; transform: translateX(0)    scale(1);   }
}
</style>

<div>
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>
                <i data-lucide="file-check-2" style="width:22px;height:22px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>
                <?= htmlspecialchars($rental['agreement_number']) ?>
            </h1>
            <p>
                <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?>
                [<?= htmlspecialchars($rental['plate_number']) ?>] ·
                <?= htmlspecialchars($rental['customer_name']) ?>
            </p>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width:15px;height:15px;"></i> Back to Rentals
            </a>
            <a href="generate-pdf.php?id=<?= $rental['agreement_id'] ?>" target="_blank" class="btn btn-secondary">
                <i data-lucide="file-text" style="width:15px;height:15px;"></i> PDF
            </a>
        </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div style="margin-bottom:1.5rem;padding:1rem 1.25rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);display:flex;align-items:flex-start;gap:.6rem;font-weight:500;">
            <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;margin-top:1px;"></i>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <div class="view-grid">

        <!-- ── LEFT COLUMN ── -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">

            <!-- Vehicle Card -->
            <div class="card" style="text-align:center;">
                <div class="card-body">
                    <div class="vehicle-3d-stage">
                        <div class="vehicle-3d-card">
                            <?php if (!empty($rental['primary_photo_path'])): ?>
                                <img src="<?= htmlspecialchars(BASE_URL . ltrim($rental['primary_photo_path'], '/')) ?>"
                                     alt="<?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?>">
                            <?php else: ?>
                                <div class="car-placeholder">
                                    <i data-lucide="car" style="width:48px;height:48px;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h2 style="font-size:1.3rem;margin:0 0 .25rem;"><?= htmlspecialchars($rental['agreement_number']) ?></h2>
                    <p style="color:var(--text-muted);font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;margin:0 0 .15rem;"><?= htmlspecialchars($rental['customer_name']) ?></p>
                    <p style="font-weight:700;font-size:.875rem;color:var(--text-secondary);margin:0 0 1rem;">
                        <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?>
                        <span style="font-size:.75rem;padding:2px 6px;background:var(--bg-muted);border-radius:4px;border:1px solid var(--border-color);">
                            <?= htmlspecialchars($rental['plate_number']) ?>
                        </span>
                    </p>

                    <span class="status-pill" style="background:<?= $statusColor ?>;">
                        <span class="status-dot"></span>
                        <?= ucfirst(htmlspecialchars($rental['status'])) ?>
                    </span>

                    <!-- Quick financial -->
                    <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--border-color);text-align:left;">
                        <p style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin:0 0 .5rem;">Financial Quick View</p>
                        <div style="display:flex;justify-content:space-between;font-size:.875rem;margin-bottom:.25rem;">
                            <span style="color:var(--text-secondary);">Rental Total</span>
                            <strong style="color:var(--primary);">₱<?= number_format($totalAmount, 2) ?></strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.875rem;">
                            <span style="color:var(--text-secondary);">Security Deposit</span>
                            <strong>₱<?= number_format($secDeposit, 2) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex;flex-direction:column;gap:.6rem;">
                <?php if (in_array($rental['status'], ['confirmed', 'reserved']) && $authUser->hasPermission('rentals.update')): ?>
                    <a href="check-out.php?id=<?= $rental['agreement_id'] ?>" class="btn btn-primary" style="justify-content:center;">
                        <i data-lucide="log-out" style="width:16px;height:16px;"></i> Perform Check-out
                    </a>
                <?php endif; ?>

                <?php if ($rental['status'] === 'active' && $authUser->hasPermission('rentals.update')): ?>
                    <a href="check-in.php?id=<?= $rental['agreement_id'] ?>" class="btn btn-primary" style="justify-content:center;">
                        <i data-lucide="log-in" style="width:16px;height:16px;"></i> Perform Check-in
                    </a>
                <?php endif; ?>

                <?php if ($balanceOwing > 0 && $authUser->hasPermission('rentals.update')): ?>
                    <button onclick="openPaymentModal()" class="btn btn-primary" style="justify-content:center;">
                        <i data-lucide="credit-card" style="width:16px;height:16px;"></i> Record Payment
                    </button>
                <?php endif; ?>

                <?php if (in_array($rental['status'], ['reserved','confirmed','active']) && $authUser->hasPermission('rentals.update')): ?>
                    <button type="button" onclick="openCancelModal()" class="btn"
                            style="justify-content:center;background:transparent;border:1px solid var(--danger);color:var(--danger);">
                        <i data-lucide="x-circle" style="width:16px;height:16px;"></i> Cancel Rental
                    </button>
                <?php endif; ?>
            </div>

            <!-- Fleet Siblings -->
            <?php if (!empty($fleetSiblings)): ?>
                <div class="card" style="border-top:3px solid var(--primary);">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i data-lucide="layers" style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>
                            Fleet Booking
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:var(--primary);color:#fff;border-radius:50%;font-size:.7rem;font-weight:800;margin-left:.4rem;">
                                <?= count($fleetSiblings) + 1 ?>
                            </span>
                        </h2>
                    </div>
                    <div class="card-body" style="padding-top:.5rem;">
                        <p style="font-size:.75rem;color:var(--text-muted);margin:0 0 .75rem;">Other vehicles in this booking:</p>
                        <?php foreach ($fleetSiblings as $sib):
                            $sibColor = $statusColors[$sib['status']] ?? 'var(--text-muted)';
                        ?>
                            <div class="sibling-row">
                                <div>
                                    <div style="font-weight:700;font-size:.82rem;"><?= htmlspecialchars($sib['brand'] . ' ' . $sib['model']) ?></div>
                                    <div style="font-size:.72rem;color:var(--text-muted);"><?= htmlspecialchars($sib['plate_number']) ?> · <?= htmlspecialchars($sib['agreement_number']) ?></div>
                                </div>
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <span class="status-pill" style="background:<?= $sibColor ?>;font-size:.65rem;">
                                        <span class="status-dot"></span><?= ucfirst($sib['status']) ?>
                                    </span>
                                    <a href="view.php?id=<?= $sib['agreement_id'] ?>" class="btn btn-ghost btn-sm">
                                        <i data-lucide="external-link" style="width:13px;height:13px;"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div><!-- /left column -->

        <!-- ── RIGHT COLUMN ── -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">

            <!-- 1. Client & Vehicle Overview -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="user" style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>
                        Client & Vehicle Overview
                    </h2>
                </div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div>
                            <span class="detail-label">Customer</span>
                            <p class="detail-value">
                                <?= htmlspecialchars($rental['customer_name']) ?>
                                <span style="font-size:.75rem;color:var(--text-muted);font-weight:500;">(<?= htmlspecialchars($rental['customer_code']) ?>)</span>
                            </p>
                        </div>
                        <div>
                            <span class="detail-label">Contact Details</span>
                            <p class="detail-value" style="font-size:.85rem;line-height:1.6;">
                                <i data-lucide="phone" style="width:12px;height:12px;vertical-align:-1px;color:var(--text-muted)"></i>
                                <?= htmlspecialchars($rental['phone_primary']) ?><br>
                                <i data-lucide="mail" style="width:12px;height:12px;vertical-align:-1px;color:var(--text-muted)"></i>
                                <?= htmlspecialchars($rental['email']) ?>
                            </p>
                        </div>
                        <div>
                            <span class="detail-label">Assigned Vehicle</span>
                            <p class="detail-value">
                                <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?>
                                <span style="font-size:.72rem;padding:2px 6px;background:var(--bg-muted);border-radius:4px;border:1px solid var(--border-color);">
                                    <?= htmlspecialchars($rental['plate_number']) ?>
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="detail-label">Vehicle Details</span>
                            <p class="detail-value" style="font-size:.85rem;">
                                <?= (int)$rental['year_model'] ?> · <?= htmlspecialchars($rental['color']) ?>
                            </p>
                        </div>
                        <div>
                            <span class="detail-label">Rental Type</span>
                            <p class="detail-value">
                                <?php if ($rental['rental_type'] === 'chauffeur'): ?>
                                    <i data-lucide="user-check" style="width:13px;height:13px;vertical-align:-1px;color:var(--primary)"></i>
                                    Chauffeur-Driven
                                <?php else: ?>
                                    <i data-lucide="car" style="width:13px;height:13px;vertical-align:-1px;color:var(--primary)"></i>
                                    Self-Drive
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <span class="detail-label">Agreement Status</span>
                            <p class="detail-value">
                                <span class="status-pill" style="background:<?= $statusColor ?>;">
                                    <span class="status-dot"></span>
                                    <?= ucfirst(htmlspecialchars($rental['status'])) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Operation Window -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="calendar" style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>
                        Operation Window
                    </h2>
                    <span style="font-size:.78rem;color:var(--text-muted);font-weight:600;">
                        <?= $rentalDays ?> day<?= $rentalDays !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="detail-grid" style="background:var(--bg-body);padding:1rem;border-radius:var(--radius-md);">
                        <div>
                            <span class="detail-label">Pickup Date</span>
                            <p class="detail-value"><?= date('F j, Y', strtotime($rental['rental_start_date'])) ?></p>
                        </div>
                        <div>
                            <span class="detail-label">Return Date</span>
                            <p class="detail-value"><?= date('F j, Y', strtotime($rental['rental_end_date'])) ?></p>
                        </div>
                        <div>
                            <span class="detail-label">Pickup Location</span>
                            <p class="detail-value">
                                <i data-lucide="map-pin" style="width:12px;height:12px;vertical-align:-1px;color:var(--primary)"></i>
                                <?= htmlspecialchars($locationLabels[$rental['pickup_location']] ?? ucwords(str_replace('_', ' ', $rental['pickup_location']))) ?>
                            </p>
                        </div>
                        <div>
                            <span class="detail-label">Return Location</span>
                            <p class="detail-value">
                                <i data-lucide="map-pin" style="width:12px;height:12px;vertical-align:-1px;color:var(--primary)"></i>
                                <?= htmlspecialchars($locationLabels[$rental['return_location']] ?? ucwords(str_replace('_', ' ', $rental['return_location']))) ?>
                            </p>
                        </div>
                        <?php if (!empty($rental['mileage_at_pickup'])): ?>
                            <div>
                                <span class="detail-label">Mileage at Pickup</span>
                                <p class="detail-value"><?= number_format($rental['mileage_at_pickup']) ?> km</p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($rental['mileage_at_return'])): ?>
                            <div>
                                <span class="detail-label">Mileage at Return</span>
                                <p class="detail-value"><?= number_format($rental['mileage_at_return']) ?> km</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 3. Driver Assignment (chauffeur only) -->
            <?php if ($rental['rental_type'] === 'chauffeur' && !empty($rental['driver_name'])): ?>
                <div class="card" style="border-top:3px solid var(--primary);">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i data-lucide="user-check" style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>
                            Assigned Chauffeur
                        </h2>
                    </div>
                    <div class="card-body">
                        <div style="display:flex;align-items:center;gap:1rem;">
                            <div style="width:50px;height:50px;border-radius:50%;background:var(--primary-light,rgba(59,130,246,.1));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i data-lucide="user" style="width:22px;height:22px;color:var(--primary)"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:800;font-size:1rem;margin-bottom:.2rem;"><?= htmlspecialchars($rental['driver_name']) ?></div>
                                <div style="font-size:.8rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($rental['driver_code'] ?? '') ?>
                                    &mdash; Lic: <?= htmlspecialchars($rental['driver_license'] ?? '') ?>
                                </div>
                                <?php if (!empty($rental['driver_phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($rental['driver_phone']) ?>"
                                       style="font-size:.8rem;color:var(--primary);text-decoration:none;">
                                        <i data-lucide="phone" style="width:11px;height:11px;vertical-align:-1px;"></i>
                                        <?= htmlspecialchars($rental['driver_phone']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <a href="<?= BASE_URL ?>modules/drivers/driver-view.php?id=<?= $rental['driver_id_val'] ?>"
                               class="btn btn-sm btn-secondary">
                                <i data-lucide="external-link" style="width:13px;height:13px;"></i> Profile
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 4. Financial Summary -->
            <div class="card" style="border:2px solid var(--primary-100,rgba(59,130,246,.15));">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="banknote" style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>
                        Financial Summary
                    </h2>
                </div>
                <div class="card-body">
                    <div class="fin-row">
                        <span class="fin-label">Daily Rate</span>
                        <span class="fin-value">₱<?= number_format($rental['daily_rate'], 2) ?></span>
                    </div>
                    <div class="fin-row">
                        <span class="fin-label">Rental Days</span>
                        <span class="fin-value"><?= $rentalDays ?></span>
                    </div>
                    <?php if (!empty($rental['chauffeur_fee']) && $rental['chauffeur_fee'] > 0): ?>
                        <div class="fin-row">
                            <span class="fin-label">Chauffeur Fee / day</span>
                            <span class="fin-value">₱<?= number_format($rental['chauffeur_fee'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="fin-row" style="border-bottom:1px solid var(--border-color);">
                        <span class="fin-label">Rental Total</span>
                        <span class="fin-value" style="font-size:1.15rem;color:var(--primary);">₱<?= number_format($totalAmount, 2) ?></span>
                    </div>
                    <div class="fin-row" style="border-bottom:none;">
                        <span class="fin-label" style="font-size:.8rem;color:var(--text-muted);">Security Deposit (hold)</span>
                        <span class="fin-value" style="font-size:.9rem;color:var(--text-muted);">₱<?= number_format($secDeposit, 2) ?></span>
                    </div>

                    <!-- Payment status -->
                    <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border-color);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                            <span style="font-size:.78rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;">Payment Status</span>
                            <span class="badge badge-<?= $payColor ?>"><?= $payLabel ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.875rem;margin-bottom:.25rem;">
                            <span style="color:var(--text-muted);">Amount Paid</span>
                            <span style="font-weight:700;color:var(--success);">₱<?= number_format($amountPaid, 2) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.875rem;">
                            <span style="color:var(--text-muted);">Balance Owing</span>
                            <span style="font-weight:700;color:<?= $balanceOwing > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                ₱<?= number_format($balanceOwing, 2) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Document Repository -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="files" style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>
                        Agreement Documents
                        <?php if (!empty($rentalDocs)): ?>
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:var(--primary);color:#fff;border-radius:50%;font-size:.7rem;font-weight:800;margin-left:.4rem;">
                                <?= count($rentalDocs) ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    <?php if ($authUser->hasPermission('rentals.update')): ?>
                        <button type="button" id="showUploadBtn"
                                onclick="document.getElementById('uploadDocForm').style.display='';this.style.display='none';"
                                class="btn btn-ghost btn-sm">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Document
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($authUser->hasPermission('rentals.update')): ?>
                    <form id="uploadDocForm" method="POST" enctype="multipart/form-data"
                          style="display:none;padding:1rem;background:var(--bg-body);border-bottom:1px solid var(--border-color);">
                        <?= csrfField() ?>
                        <input type="hidden" name="agreement_id" value="<?= $rentalId ?>">
                        <p style="margin:0 0 .75rem;font-size:.85rem;font-weight:700;">Upload New Document</p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                            <div>
                                <label style="display:block;font-size:.75rem;margin-bottom:4px;font-weight:600;">File * (Max 10 MB)</label>
                                <input type="file" name="document_file" required class="form-control" style="padding:4px;">
                            </div>
                            <div>
                                <label style="display:block;font-size:.75rem;margin-bottom:4px;font-weight:600;">Category *</label>
                                <select name="document_category" required class="form-control">
                                    <option value="contract">Signed Agreement</option>
                                    <option value="identity">ID Documents</option>
                                    <option value="billing">Payment Receipt</option>
                                    <option value="inspection">Inspection Report</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div style="grid-column:1/-1;">
                                <label style="display:block;font-size:.75rem;margin-bottom:4px;font-weight:600;">Title & Expiry (optional)</label>
                                <div style="display:flex;gap:.75rem;">
                                    <input type="text" name="document_title" class="form-control" placeholder="Custom name" style="flex:1;">
                                    <input type="date" name="expires_at" class="form-control" style="width:145px;">
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:.5rem;">
                            <button type="button" class="btn btn-sm btn-ghost"
                                    onclick="document.getElementById('uploadDocForm').style.display='none';document.getElementById('showUploadBtn').style.display='';">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">Save Document</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="card-body" style="padding:0;">
                    <?php if (empty($rentalDocs)): ?>
                        <div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:.875rem;">
                            <i data-lucide="folder-open" style="width:28px;height:28px;opacity:.3;display:block;margin:0 auto .5rem;"></i>
                            No documents attached to this agreement.
                        </div>
                    <?php else: ?>
                        <table style="width:100%;border-collapse:collapse;">
                            <thead style="background:var(--bg-body);border-bottom:1px solid var(--border-color);">
                                <tr>
                                    <th style="padding:10px 16px;text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Document</th>
                                    <th style="padding:10px 16px;text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Uploaded</th>
                                    <th style="padding:10px 16px;text-align:right;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rentalDocs as $doc): ?>
                                    <tr style="border-bottom:1px solid var(--border-color);">
                                        <td style="padding:10px 16px;">
                                            <div style="font-weight:700;font-size:.85rem;display:flex;align-items:center;gap:6px;">
                                                <i data-lucide="file-text" style="width:14px;height:14px;color:var(--text-muted);flex-shrink:0;"></i>
                                                <?= htmlspecialchars($doc['title']) ?>
                                            </div>
                                            <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;margin-top:2px;">
                                                <?= htmlspecialchars($doc['document_category']) ?> &bull; <?= round($doc['file_size'] / 1024) ?> KB
                                            </div>
                                        </td>
                                        <td style="padding:10px 16px;font-size:.8rem;color:var(--text-secondary);">
                                            <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?>
                                        </td>
                                        <td style="padding:10px 16px;text-align:right;">
                                            <a href="../documents/serve.php?id=<?= $doc['document_id'] ?>" target="_blank"
                                               class="btn btn-ghost btn-sm" title="View Document">
                                                <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /right column -->
    </div>
</div>

<!-- ── Payment Modal ─────────────────────────────────────────────────────────── -->
<div id="paymentModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-surface);border-radius:var(--radius-lg);padding:2rem;width:100%;max-width:420px;box-shadow:0 25px 50px rgba(0,0,0,.25);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h3 style="margin:0;display:flex;align-items:center;gap:6px;">
                <i data-lucide="credit-card" style="width:18px;height:18px;color:var(--primary)"></i> Record Payment
            </h3>
            <button onclick="closePaymentModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <form id="paymentForm">
            <input type="hidden" name="agreement_id" value="<?= $rentalId ?>">
            <div class="form-group">
                <label>Amount (₱) <span style="color:var(--danger)">*</span></label>
                <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required
                       max="<?= $balanceOwing ?>" placeholder="Balance owing: ₱<?= number_format($balanceOwing, 2) ?>">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" class="form-control">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>Notes (optional)</label>
                <input type="text" name="notes" class="form-control" placeholder="Reference number, etc.">
            </div>
            <div id="paymentError" style="display:none;margin-bottom:1rem;padding:.75rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-size:.875rem;"></div>
            <div style="display:flex;gap:.75rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">Submit Payment</button>
                <button type="button" onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Cancel Modal ──────────────────────────────────────────────────────────── -->
<?php if (in_array($rental['status'], ['reserved','confirmed','active']) && $authUser->hasPermission('rentals.update')): ?>
<div id="cancelRentalModal" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,.55);">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-surface);border-radius:var(--radius-lg);padding:2rem;width:100%;max-width:460px;box-shadow:0 25px 50px rgba(0,0,0,.3);border-top:4px solid var(--danger);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;display:flex;align-items:center;gap:8px;color:var(--danger);">
                <i data-lucide="alert-triangle" style="width:20px;height:20px;"></i> Cancel Rental Agreement
            </h3>
            <button onclick="closeCancelModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);">
                <i data-lucide="x" style="width:20px;height:20px;"></i>
            </button>
        </div>
        <p style="color:var(--text-secondary);font-size:.9rem;margin-bottom:1.25rem;">
            You are about to cancel agreement <strong><?= htmlspecialchars($rental['agreement_number']) ?></strong>.
            The vehicle will be returned to <em>Available</em> status.
            This action <strong style="color:var(--danger);">cannot be undone</strong>.
        </p>
        <?php if (!empty($fleetSiblings)): ?>
            <div style="background:var(--warning-light,#fff7ed);border:1px solid var(--warning,#f59e0b);border-radius:var(--radius-md);padding:.75rem;margin-bottom:1rem;font-size:.82rem;color:var(--warning-dark,#92400e);">
                <i data-lucide="alert-triangle" style="width:13px;height:13px;vertical-align:-2px;"></i>
                This is part of a fleet booking with <?= count($fleetSiblings) ?> other vehicle(s). Only <em>this</em> agreement will be cancelled.
            </div>
        <?php endif; ?>
        <div class="form-group" style="margin-bottom:1.5rem;">
            <label for="cancelReasonInput" style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.4rem;">
                Reason for Cancellation <span style="color:var(--text-muted);font-weight:400;">(optional)</span>
            </label>
            <textarea id="cancelReasonInput" class="form-control" rows="3"
                      placeholder="e.g. Customer requested cancellation, vehicle unavailable…"></textarea>
        </div>
        <div id="cancelRentalError" style="display:none;margin-bottom:1rem;padding:.75rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-size:.875rem;"></div>
        <div style="display:flex;gap:.75rem;">
            <button type="button" id="cancelRentalSubmitBtn" onclick="submitCancelRental()"
                    class="btn btn-danger" style="flex:1;justify-content:center;">
                <i data-lucide="x-circle" style="width:15px;height:15px;"></i> Confirm Cancellation
            </button>
            <button type="button" onclick="closeCancelModal()" class="btn btn-secondary">Go Back</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Success Toast ──────────────────────────────────────────────────────────── -->
<?php if ($success): ?>
    <div id="booking-toast"
         style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:var(--success,#22c55e);color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn .35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($success) ?></span>
        <button onclick="document.getElementById('booking-toast').remove()"
                style="background:none;border:none;cursor:pointer;color:#fff;padding:0;opacity:.8;">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    </div>
    <script>
        setTimeout(function () {
            var t = document.getElementById('booking-toast');
            if (t) {
                t.style.transition = 'opacity .4s, transform .4s';
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                setTimeout(function () { if (t) t.remove(); }, 400);
            }
        }, 3500);
    </script>
<?php endif; ?>

<script>
const CSRF_TOKEN       = '<?= getCsrfToken() ?>';
const CANCEL_CSRF      = '<?= getCsrfToken() ?>';
const CANCEL_AGREEMENT_ID = <?= (int)$rental['agreement_id'] ?>;
const BASE_URL         = '<?= BASE_URL ?>';

// ── Payment modal ─────────────────────────────────────────────────────────────
function openPaymentModal()  { document.getElementById('paymentModal').style.display = ''; lucide.createIcons(); }
function closePaymentModal() { document.getElementById('paymentModal').style.display = 'none'; }

document.getElementById('paymentForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = new FormData(this);
    data.append('csrf_token', CSRF_TOKEN);
    const errEl = document.getElementById('paymentError');
    errEl.style.display = 'none';
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true; btn.textContent = 'Processing…';

    fetch(BASE_URL + 'modules/rentals/ajax/record-payment.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                errEl.textContent = d.message; errEl.style.display = '';
                btn.disabled = false; btn.textContent = 'Submit Payment';
                return;
            }
            closePaymentModal();
            location.reload();
        })
        .catch(() => {
            errEl.textContent = 'Network error. Please try again.';
            errEl.style.display = '';
            btn.disabled = false; btn.textContent = 'Submit Payment';
        });
});

// ── Cancel modal ──────────────────────────────────────────────────────────────
function openCancelModal() {
    const m = document.getElementById('cancelRentalModal');
    if (!m) return;
    m.style.display = '';
    document.getElementById('cancelRentalError').style.display = 'none';
    document.getElementById('cancelReasonInput').value = '';
    lucide.createIcons();
}
function closeCancelModal() {
    const m = document.getElementById('cancelRentalModal');
    if (m) m.style.display = 'none';
}
function submitCancelRental() {
    const btn   = document.getElementById('cancelRentalSubmitBtn');
    const errEl = document.getElementById('cancelRentalError');
    const reason = document.getElementById('cancelReasonInput').value.trim();

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:15px;height:15px;"></i> Cancelling…';
    lucide.createIcons();
    errEl.style.display = 'none';

    const data = new FormData();
    data.append('csrf_token',   CANCEL_CSRF);
    data.append('agreement_id', CANCEL_AGREEMENT_ID);
    data.append('reason',       reason);

    fetch(BASE_URL + 'modules/rentals/ajax/cancel-rental.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                errEl.textContent = d.message; errEl.style.display = '';
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="x-circle" style="width:15px;height:15px;"></i> Confirm Cancellation';
                lucide.createIcons();
                return;
            }
            closeCancelModal();
            location.reload();
        })
        .catch(() => {
            errEl.textContent = 'Network error. Please try again.';
            errEl.style.display = '';
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="x-circle" style="width:15px;height:15px;"></i> Confirm Cancellation';
            lucide.createIcons();
        });
}

document.getElementById('cancelRentalModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeCancelModal();
});
document.getElementById('paymentModal')?.addEventListener('click', function (e) {
    if (e.target === this) closePaymentModal();
});

lucide.createIcons();
</script>

<?php require_once '../../includes/footer.php'; ?>