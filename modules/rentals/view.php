<?php
/**
 * Rental Agreement Detailed View
 * Path: modules/rentals/view.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/DocumentManager.php';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    if ($authUser->hasPermission('rentals.update')) {
        try {
            $rentalId = (int) ($_POST['agreement_id'] ?? 0);
            $cat = $_POST['document_category'] ?? 'other';
            $title = !empty($_POST['document_title']) ? $_POST['document_title'] : null;
            $exp = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            DocumentManager::uploadDocument($_FILES['document_file'], 'rental_agreement', $rentalId, $cat, $title, $authUser->getId(), $exp);
            $_SESSION['success_message'] = "Document uploaded successfully.";
            header("Location: view.php?id=" . $rentalId);
            exit;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
}

$success = '';
if (!empty($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$authUser->requirePermission('rentals.view');

$rentalId = (int) ($_GET['id'] ?? 0);
if (!$rentalId) {
    redirect('../../modules/rentals/index.php', 'Agreement ID is required.', 'error');
}

try {
    $rental = $db->fetchOne("
        SELECT ra.*, 
               v.plate_number, v.brand, v.model, v.year_model, v.color, v.primary_photo_path,
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               c.customer_code, c.phone_primary, c.email, c.address,
               CONCAT(d.first_name,' ',d.last_name) as driver_name,
               d.phone as driver_phone, d.employee_code as driver_code,
               d.license_number as driver_license, d.license_expiry as driver_license_expiry,
               d.driver_id as driver_id_val
        FROM rental_agreements ra
        JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
        JOIN customers c ON ra.customer_id = c.customer_id
        LEFT JOIN drivers d ON ra.driver_id = d.driver_id
        WHERE ra.agreement_id = ?
    ", [$rentalId]);

    if (!$rental) {
        redirect('../../modules/rentals/index.php', 'Rental agreement not found.', 'error');
    }

    $rentalDocs = DocumentManager::getDocumentsByEntity('rental_agreement', $rentalId);
} catch (Exception $e) {
    redirect('../../modules/rentals/index.php', 'Database Error: ' . $e->getMessage(), 'error');
}

$pageTitle = "View Agreement: " . $rental['agreement_number'];
require_once '../../includes/header.php';
?>

<div class="fade-in max-w-6xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 mb-6 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Management Hub
        </a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600">Agreement Profile</span>
    </div>

    <!-- Main Layout Grid -->
    <div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
        <!-- Summary Card (Left Column) -->
        <div class="flex flex-col gap-6">
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <style>
                        #vehicle3dStage {
                            perspective: 900px;
                            width: 100%;
                            height: 140px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-bottom: var(--space-4);
                        }

                        #vehicle3dCard {
                            width: 220px;
                            height: 140px;
                            border-radius: 14px;
                            background: linear-gradient(135deg, #1e293b, #334155);
                            box-shadow: 0 15px 40px rgba(0, 0, 0, .35), 0 4px 12px rgba(0, 0, 0, .2);
                            transform-style: preserve-3d;
                            animation: spin3d 8s linear infinite;
                            overflow: hidden;
                            position: relative;
                        }

                        #vehicle3dCard img {
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            border-radius: 14px;
                        }

                        #vehicle3dCard:hover {
                            animation-play-state: paused;
                        }

                        #vehicle3dCard .car-placeholder {
                            width: 100%;
                            height: 100%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: #94a3b8;
                        }

                        @keyframes spin3d {
                            0% {
                                transform: rotateY(-25deg) rotateX(5deg)
                            }

                            50% {
                                transform: rotateY(25deg) rotateX(-5deg)
                            }

                            100% {
                                transform: rotateY(-25deg) rotateX(5deg)
                            }
                        }
                    </style>

                    <div id="vehicle3dStage">
                        <div id="vehicle3dCard">
                            <?php if (!empty($rental['primary_photo_path'])): ?>
                                <img src="<?php echo BASE_URL . ltrim($rental['primary_photo_path'], '/'); ?>"
                                    alt="<?php echo htmlspecialchars($rental['vehicle_id'] ?? ''); ?>">
                            <?php else: ?>
                                <div class="car-placeholder">
                                    <i data-lucide="file-check-2" style="width: 48px; height: 48px;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h2 style="margin-bottom: var(--space-2); font-size: 1.5rem; line-height: 1.2;">
                        <?= htmlspecialchars($rental['agreement_number']) ?>
                    </h2>
                    <p
                        style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                        <?= htmlspecialchars($rental['customer_name']) ?>
                    </p>
                    <p
                        style="color: var(--text-secondary); font-size: 0.875rem; font-weight: bold; margin-bottom: var(--space-4);">
                        <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?>
                        [<?= htmlspecialchars($rental['plate_number']) ?>]
                    </p>

                    <?php
                    $statusColor = 'var(--secondary-500)';
                    if ($rental['status'] === 'active')
                        $statusColor = 'var(--primary)';
                    if (in_array($rental['status'], ['completed', 'confirmed']))
                        $statusColor = 'var(--success)';
                    if ($rental['status'] === 'cancelled')
                        $statusColor = 'var(--danger)';
                    ?>
                    <div
                        style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: <?= $statusColor ?>; color: white; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">
                        <span style="width: 6px; height: 6px; background: white; border-radius: 50%;"></span>
                        <?= htmlspecialchars($rental['status']) ?>
                    </div>

                    <div
                        style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
                        <p
                            style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                            Financial Quick View</p>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Total Amount</span>
                            <strong
                                style="font-size: 1rem; color: var(--primary-600);">₱<?= number_format($rental['total_amount'], 2) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php if ($rental['status'] === 'confirmed' || $rental['status'] === 'reserved'): ?>
                    <a href="check-out.php?id=<?= $rental['agreement_id'] ?>" class="btn btn-primary"
                        style="justify-content: center;">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Perform Check-out
                    </a>
                <?php endif; ?>

                <?php if ($rental['status'] === 'active'): ?>
                    <a href="check-in.php?id=<?= $rental['agreement_id'] ?>" class="btn btn-primary"
                        style="justify-content: center;">
                        <i data-lucide="log-in" class="w-4 h-4"></i> Perform Check-in
                    </a>
                <?php endif; ?>

                <a href="generate-pdf.php?id=<?= $rental['agreement_id'] ?>" target="_blank" class="btn btn-secondary"
                    style="justify-content: center;">
                    <i data-lucide="file-text" class="w-4 h-4"></i> Generate PDF Agreement
                </a>

                <?php if (in_array($rental['status'], ['reserved', 'confirmed', 'active']) && $authUser->hasPermission('rentals.update')): ?>
                    <button type="button" id="cancelRentalBtn" onclick="openCancelModal()" class="btn"
                        style="justify-content: center; background: transparent; border: 1px solid var(--danger); color: var(--danger);">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> Cancel Rental
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detail Sections (Right Column) -->
        <div class="flex flex-col gap-6">

            <!-- Client & Vehicle Overview -->
            <div class="card">
                <div class="card-body">
                    <h2
                        style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="user" style="color: var(--primary);"></i> Client & Vehicle Overview
                    </h2>
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Customer
                                Info</label>
                            <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                <?= htmlspecialchars($rental['customer_name']) ?> <span
                                    style="font-size: 0.75rem; color: var(--text-muted);">(<?= htmlspecialchars($rental['customer_code']) ?>)</span>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Contact
                                Details</label>
                            <p style="font-weight: bold; margin: 0; font-size: 0.875rem; line-height: 1.5;">
                                P: <?= htmlspecialchars($rental['phone_primary']) ?><br>
                                E: <?= htmlspecialchars($rental['email']) ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Assigned
                                Vehicle</label>
                            <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?> <span
                                    style="font-size: 0.75rem; padding: 2px 6px; background: var(--secondary-100); border-radius: 4px; border: 1px solid var(--border-color);">
                                    <?= htmlspecialchars($rental['plate_number']) ?></span>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Vehicle
                                Stats</label>
                            <p style="font-weight: bold; margin: 0; font-size: 0.875rem; line-height: 1.5;">
                                Year: <?= $rental['year_model'] ?> | Color: <?= htmlspecialchars($rental['color']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rental Period & Terms -->
            <div class="card">
                <div class="card-body">
                    <h2
                        style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="calendar" style="color: var(--primary);"></i> Rental Period & Terms
                    </h2>
                    <div class="grid"
                        style="grid-template-columns: 1fr 1fr; gap: var(--space-4); background: var(--secondary-50); padding: var(--space-4); border-radius: var(--radius-md);">
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Start
                                Date</label>
                            <p style="font-weight: bold; margin: 0; font-size: 0.875rem; color: var(--text-main);">
                                <?= date('F j, Y g:i A', strtotime($rental['rental_start_date'])) ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">End
                                Date</label>
                            <p style="font-weight: bold; margin: 0; font-size: 0.875rem; color: var(--text-main);">
                                <?= date('F j, Y g:i A', strtotime($rental['rental_end_date'])) ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Pickup
                                Location</label>
                            <p style="font-weight: bold; margin: 0; font-size: 0.875rem; color: var(--text-secondary);">
                                <?= htmlspecialchars($rental['pickup_location']) ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Return
                                Location</label>
                            <p style="font-weight: bold; margin: 0; font-size: 0.875rem; color: var(--text-secondary);">
                                <?= htmlspecialchars($rental['return_location']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="card"
                style="border: 2px solid var(--primary-100); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);">
                <div class="card-body">
                    <h2
                        style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="banknote" style="color: var(--primary);"></i> Financial Summary
                    </h2>

                    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; padding-bottom: var(--space-3); border-bottom: 1px dashed var(--border-color);">
                            <span style="font-weight: bold; color: var(--text-secondary);">Daily Rental Rate</span>
                            <span
                                style="font-weight: bold; font-family: monospace; font-size: 1.1rem;">₱<?= number_format($rental['daily_rate'], 2) ?></span>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; padding-bottom: var(--space-3); border-bottom: 1px dashed var(--border-color);">
                            <span style="font-weight: bold; color: var(--text-secondary);">Security Deposit</span>
                            <span
                                style="font-weight: bold; font-family: monospace; font-size: 1.1rem;">₱<?= number_format($rental['security_deposit'], 2) ?></span>
                        </div>
                        <?php if (!empty($rental['chauffeur_fee']) && $rental['chauffeur_fee'] > 0): ?>
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; padding-bottom: var(--space-3); border-bottom: 1px dashed var(--border-color);">
                                <span style="font-weight: bold; color: var(--text-secondary);">Chauffeur Fee (daily)</span>
                                <span
                                    style="font-weight: bold; font-family: monospace; font-size: 1.1rem;">₱<?= number_format($rental['chauffeur_fee'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; padding-top: var(--space-2);">
                            <span style="font-weight: 800; font-size: 1.1rem; color: var(--text-main);">Total Estimated
                                Amount</span>
                            <span
                                style="font-weight: 900; font-size: 1.5rem; color: var(--primary-600);">₱<?= number_format($rental['total_amount'], 2) ?></span>
                        </div>

                        <!-- Payment Status (Gap 2) -->
                        <?php
                        $amountPaid = (float) ($rental['amount_paid'] ?? 0);
                        $totalAmount = (float) $rental['total_amount'];
                        $balanceOwing = max(0, $totalAmount - $amountPaid);
                        $payStatus = $rental['payment_status'] ?? 'pending';
                        $payColor = match ($payStatus) { 'fully_paid' => 'success', 'partial' => 'warning', default => 'danger'};
                        $payLabel = match ($payStatus) { 'fully_paid' => 'Fully Paid', 'partial' => 'Partial', default => 'Pending'};
                        ?>
                        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border-color);">
                            <div
                                style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;">
                                <span
                                    style="font-size:.8rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;">Payment
                                    Status</span>
                                <span class="badge badge-<?= $payColor ?>"><?= $payLabel ?></span>
                            </div>
                            <div
                                style="display:flex;justify-content:space-between;font-size:.875rem;margin-bottom:.25rem;">
                                <span style="color:var(--text-muted);">Amount Paid</span>
                                <span
                                    style="font-weight:700;color:var(--success);">₱<?= number_format($amountPaid, 2) ?></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:.875rem;">
                                <span style="color:var(--text-muted);">Balance Owing</span>
                                <span
                                    style="font-weight:700;color:<?= $balanceOwing > 0 ? 'var(--danger)' : 'var(--success)' ?>;">₱<?= number_format($balanceOwing, 2) ?></span>
                            </div>
                            <?php if ($balanceOwing > 0 && $authUser->hasPermission('rentals.update')): ?>
                                <button onclick="openPaymentModal()" class="btn btn-primary"
                                    style="width:100%;margin-top:.75rem;justify-content:center;">
                                    <i data-lucide="credit-card" style="width:15px;height:15px;"></i> Record Payment
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($rental['rental_type']) && $rental['rental_type'] === 'chauffeur' && !empty($rental['driver_name'])): ?>
                <!-- Driver Assignment Card (Gap 1) -->
                <div class="card" style="border-top:3px solid var(--primary);">
                    <div class="card-body">
                        <h2 style="margin:0 0 var(--space-3);display:flex;align-items:center;gap:8px;">
                            <i data-lucide="user-check" style="color:var(--primary);"></i> Assigned Chauffeur
                        </h2>
                        <div style="display:flex;align-items:center;gap:1rem;">
                            <div
                                style="width:48px;height:48px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i data-lucide="user" style="width:22px;height:22px;color:var(--primary)"></i>
                            </div>
                            <div>
                                <div style="font-weight:800;font-size:1rem;"><?= htmlspecialchars($rental['driver_name']) ?>
                                </div>
                                <div style="font-size:.8rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($rental['driver_code'] ?? '') ?> — Lic:
                                    <?= htmlspecialchars($rental['driver_license'] ?? '') ?>
                                </div>
                                <?php if (!empty($rental['driver_phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($rental['driver_phone']) ?>"
                                        style="font-size:.8rem;color:var(--primary);"><?= htmlspecialchars($rental['driver_phone']) ?></a>
                                <?php endif; ?>
                            </div>
                            <a href="<?= BASE_URL ?>modules/drivers/driver-view.php?id=<?= $rental['driver_id_val'] ?>"
                                class="btn btn-sm btn-secondary" style="margin-left:auto;">
                                View Profile
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($errorMsg)): ?>
                <div
                    style="padding:1rem; background:var(--danger-light); color:var(--danger); border-radius:var(--radius-md); margin-bottom:var(--space-4);">
                    <i data-lucide="alert-circle"
                        style="width:16px;height:16px; display:inline-block; vertical-align:-3px; margin-right:4px;"></i>
                    <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>

            <!-- Document Repository -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom: 1px solid var(--border-color); padding: var(--space-4); margin: -var(--space-4) -var(--space-4) var(--space-4) -var(--space-4); display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title" style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="files" style="width: 18px; height: 18px; color: var(--primary);"></i> Agreement Documents
                    </h2>
                    <?php if ($authUser->hasPermission('rentals.update')): ?>
                        <button type="button" onclick="document.getElementById('uploadDocForm').style.display='block'; this.style.display='none';" class="btn btn-ghost btn-sm" style="font-size:0.75rem;" title="Upload New Document">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Document
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($authUser->hasPermission('rentals.update')): ?>
                    <form id="uploadDocForm" method="POST" enctype="multipart/form-data"
                        style="display:none; padding:1rem; background:var(--bg-muted); border-radius:var(--radius-md); margin-bottom:1rem; border:1px solid var(--border-color);">
                        <input type="hidden" name="agreement_id" value="<?= $rentalId ?>">
                        <p style="margin:0 0 1rem 0; font-size:0.875rem; font-weight:700;">Upload New Document</p>
                        <div class="grid" style="grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom:0.75rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">File *
                                    (Max 10MB)</label>
                                <input type="file" name="document_file" required class="form-control" style="padding:4px;">
                            </div>
                            <div>
                                <label
                                    style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">Category
                                    *</label>
                                <select name="document_category" required class="form-control" style="padding:6px;">
                                    <option value="contract">Signed Agreement</option>
                                    <option value="identity">ID Documents</option>
                                    <option value="billing">Payment Receipt</option>
                                    <option value="inspection">Inspection Report</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">Title &
                                    Expiry Date (Optional)</label>
                                <div style="display:flex; gap:0.75rem;">
                                    <input type="text" name="document_title" class="form-control" placeholder="Custom name"
                                        style="flex:1;">
                                    <input type="date" name="expires_at" class="form-control" style="width:150px;">
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <button type="button"
                                onclick="document.getElementById('uploadDocForm').style.display='none'; document.querySelector('#uploadDocForm').previousElementSibling.querySelector('button').style.display='inline-flex';"
                                class="btn btn-sm btn-ghost">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Save Document</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="table-container"
                    style="border:none; margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <?php if (empty($rentalDocs)): ?>
                        <div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:0.875rem;">No documents
                            attached to this agreement.</div>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead style="background: var(--secondary-50); border-bottom: 1px solid var(--border-color);">
                                <tr>
                                    <th
                                        style="padding: 10px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase;">
                                        Document</th>
                                    <th
                                        style="padding: 10px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase;">
                                        Date</th>
                                    <th
                                        style="padding: 10px 16px; text-align: right; font-size: 0.75rem; text-transform: uppercase;">
                                        Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rentalDocs as $doc): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 10px 16px;">
                                            <div
                                                style="font-weight:700; font-size:0.85rem; color:var(--text-main); display:flex; align-items:center; gap:6px;">
                                                <i data-lucide="file-text"
                                                    style="width:14px;height:14px;color:var(--text-muted);"></i>
                                                <?= htmlspecialchars($doc['title']) ?>
                                            </div>
                                            <div
                                                style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; margin-top:2px;">
                                                <?= htmlspecialchars($doc['document_category']) ?> &bull;
                                                <?= round($doc['file_size'] / 1024) ?> KB
                                            </div>
                                        </td>
                                        <td style="padding: 10px 16px; font-size:0.8rem;">
                                            <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?>
                                        </td>
                                        <td style="padding: 10px 16px; text-align:right;">
                                            <a href="../documents/serve.php?id=<?= $doc['document_id'] ?>" target="_blank"
                                                class="btn btn-sm btn-ghost" title="View"><i data-lucide="external-link"
                                                    style="width:14px;height:14px;"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);">
    <div
        style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-surface);border-radius:var(--radius-lg);padding:2rem;width:100%;max-width:420px;box-shadow:0 25px 50px rgba(0,0,0,.25);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h3 style="margin:0;"><i data-lucide="credit-card"
                    style="width:18px;height:18px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Record
                Payment</h3>
            <button onclick="closePaymentModal()"
                style="background:none;border:none;cursor:pointer;color:var(--text-muted);"><i data-lucide="x"
                    style="width:20px;height:20px;"></i></button>
        </div>
        <form id="paymentForm">
            <input type="hidden" name="agreement_id" value="<?= $rentalId ?>">
            <div class="form-group">
                <label>Amount (₱) <span style="color:var(--danger)">*</span></label>
                <input type="number" name="amount" class="form-control" min="1" step="0.01" required
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
            <div id="paymentError"
                style="display:none;margin-bottom:1rem;padding:.75rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-size:.875rem;">
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">Submit
                    Payment</button>
                <button type="button" onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
    const CSRF_TOKEN = '<?= getCsrfToken() ?>';
    function openPaymentModal() { document.getElementById('paymentModal').style.display = ''; }
    function closePaymentModal() { document.getElementById('paymentModal').style.display = 'none'; }
    document.getElementById('paymentForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.append('csrf_token', CSRF_TOKEN);
        const errEl = document.getElementById('paymentError');
        errEl.style.display = 'none';
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true; btn.textContent = 'Processing…';
        fetch('<?= BASE_URL ?>modules/rentals/ajax/record-payment.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { errEl.textContent = d.message; errEl.style.display = ''; btn.disabled = false; btn.textContent = 'Submit Payment'; return; }
                closePaymentModal();
                location.reload();
            })
            .catch(() => { errEl.textContent = 'Network error. Please try again.'; errEl.style.display = ''; btn.disabled = false; btn.textContent = 'Submit Payment'; });
    });
</script>

<?php if ($success): ?>
    <div id="booking-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($success) ?></span>
        <button onclick="document.getElementById('booking-toast').remove()"
            style="background:none;border:none;cursor:pointer;color:#fff;padding:0;margin:0;display:flex;align-items:center;opacity:0.8;"
            aria-label="Dismiss">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    </div>
    <style>
        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(60px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }
    </style>
    <script>
        setTimeout(function () {
            var t = document.getElementById('booking-toast');
            if (t) {
                t.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                setTimeout(function () { if (t) t.remove(); }, 400);
            }
        }, 3500);
    </script>
<?php endif; ?>

<?php if (in_array($rental['status'], ['reserved', 'confirmed', 'active']) && $authUser->hasPermission('rentals.update')): ?>
    <!-- Cancel Rental Modal -->
    <div id="cancelRentalModal" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,.55);">
        <div
            style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-surface);border-radius:var(--radius-lg);padding:2rem;width:100%;max-width:460px;box-shadow:0 25px 50px rgba(0,0,0,.3);border-top:4px solid var(--danger);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                <h3 style="margin:0;display:flex;align-items:center;gap:8px;color:var(--danger);">
                    <i data-lucide="alert-triangle" style="width:20px;height:20px;"></i> Cancel Rental Agreement
                </h3>
                <button onclick="closeCancelModal()"
                    style="background:none;border:none;cursor:pointer;color:var(--text-muted);"><i data-lucide="x"
                        style="width:20px;height:20px;"></i></button>
            </div>
            <p style="color:var(--text-secondary);font-size:.9rem;margin-bottom:1.25rem;">
                You are about to cancel agreement <strong><?= htmlspecialchars($rental['agreement_number']) ?></strong>.
                The vehicle will be returned to <em>Available</em> status.
                This action <strong style="color:var(--danger);">cannot be undone</strong>.
            </p>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label for="cancelReasonInput"
                    style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.4rem;">Reason for Cancellation
                    <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                <textarea id="cancelReasonInput" class="form-control" rows="3"
                    placeholder="e.g. Customer requested cancellation, vehicle unavailable…"></textarea>
            </div>
            <div id="cancelRentalError"
                style="display:none;margin-bottom:1rem;padding:.75rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-size:.875rem;">
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="button" id="cancelRentalSubmitBtn" onclick="submitCancelRental()" class="btn btn-danger"
                    style="flex:1;justify-content:center;">
                    <i data-lucide="x-circle" style="width:15px;height:15px;"></i> Confirm Cancellation
                </button>
                <button type="button" onclick="closeCancelModal()" class="btn btn-secondary">Go Back</button>
            </div>
        </div>
    </div>
    <script>
        const CANCEL_CSRF = '<?= getCsrfToken() ?>';
        const CANCEL_AGREEMENT_ID = <?= (int) $rental['agreement_id'] ?>;
        const CANCEL_AJAX_URL = '<?= BASE_URL ?>modules/rentals/ajax/cancel-rental.php';

        function openCancelModal() {
            document.getElementById('cancelRentalModal').style.display = '';
            document.getElementById('cancelRentalError').style.display = 'none';
            document.getElementById('cancelReasonInput').value = '';
            lucide.createIcons();
        }
        function closeCancelModal() {
            document.getElementById('cancelRentalModal').style.display = 'none';
        }
        function submitCancelRental() {
            const btn = document.getElementById('cancelRentalSubmitBtn');
            const errEl = document.getElementById('cancelRentalError');
            const reason = document.getElementById('cancelReasonInput').value.trim();

            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" style="width:15px;height:15px;"></i> Cancelling…';
            lucide.createIcons();
            errEl.style.display = 'none';

            const data = new FormData();
            data.append('csrf_token', CANCEL_CSRF);
            data.append('agreement_id', CANCEL_AGREEMENT_ID);
            data.append('reason', reason);

            fetch(CANCEL_AJAX_URL, { method: 'POST', body: data })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        errEl.textContent = d.message;
                        errEl.style.display = '';
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
        // Close on backdrop click
        document.getElementById('cancelRentalModal').addEventListener('click', function (e) {
            if (e.target === this) closeCancelModal();
        });
    </script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>