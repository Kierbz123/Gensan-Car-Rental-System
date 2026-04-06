<?php
// modules/asset-tracking/vehicle-details.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/DocumentManager.php';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    if ($authUser->hasPermission('vehicles.update')) {
        try {
            $vId = $_POST['vehicle_id'] ?? '';
            $cat = $_POST['document_category'] ?? 'other';
            $title = !empty($_POST['document_title']) ? $_POST['document_title'] : null;
            $exp = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            DocumentManager::uploadDocument($_FILES['document_file'], 'vehicle', $vId, $cat, $title, $authUser->getId(), $exp);
            $_SESSION['success_message'] = "Document uploaded successfully.";
            header("Location: vehicle-details.php?id=" . $vId);
            exit;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
}

$pageTitle = "Vehicle Intelligence Profile";
require_once '../../includes/header.php';

$authUser->requirePermission('vehicles.view');

$vehicleId = $_GET['id'] ?? '';
if (empty($vehicleId)) {
    redirect('modules/asset-tracking/', 'Vehicle ID is required', 'error');
}

$db = Database::getInstance();

$vehicleDocs = DocumentManager::getDocumentsByEntity('vehicle', $vehicleId);

$success = '';
if (!empty($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Fetch vehicle master data
$vehicleData = $db->fetchOne("
    SELECT v.*, c.category_name 
    FROM vehicles v 
    JOIN vehicle_categories c ON v.category_id = c.category_id 
    WHERE v.vehicle_id = ? AND v.deleted_at IS NULL",
    [$vehicleId]
);

if (!$vehicleData) {
    redirect('modules/asset-tracking/', 'Vehicle profile not found in active registry.', 'warning');
}

// Operational Data Retrieval
$statusHistory = $db->fetchAll("
    SELECT vsl.*, CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
    FROM vehicle_status_logs vsl
    LEFT JOIN users u ON vsl.changed_by = u.user_id
    WHERE vsl.vehicle_id = ? 
    ORDER BY vsl.changed_at DESC LIMIT 5",
    [$vehicleId]
);

$currentRental = $db->fetchOne("
    SELECT ra.*, c.first_name, c.last_name, c.phone_primary
    FROM rental_agreements ra
    JOIN customers c ON ra.customer_id = c.customer_id
    WHERE ra.vehicle_id = ? AND ra.status IN ('reserved', 'active')
    ORDER BY ra.created_at DESC LIMIT 1",
    [$vehicleId]
);

$maintenanceHistory = $db->fetchAll("
    SELECT * FROM maintenance_logs 
    WHERE vehicle_id = ? AND status = 'completed'
    ORDER BY service_date DESC LIMIT 5",
    [$vehicleId]
);

$complianceRecords = $db->fetchAll("
    SELECT * FROM compliance_records 
    WHERE vehicle_id = ?
    ORDER BY expiry_date ASC",
    [$vehicleId]
);

$rentalHistory = $db->fetchAll("
    SELECT ra.*, c.first_name, c.last_name
    FROM rental_agreements ra
    JOIN customers c ON ra.customer_id = c.customer_id
    WHERE ra.vehicle_id = ?
    ORDER BY ra.rental_start_date DESC",
    [$vehicleId]
);

?>

<div class="fade-in max-w-7xl mx-auto">
    <!-- Breadcrumb / Header area, simplified to match pattern -->
    <div class="flex items-center gap-3 mb-6 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Asset Registry
        </a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600">Intelligence Profile</span>
    </div>

    <!-- Main Layout Grid -->
    <div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
        <!-- Avatar & Summary Card (Left Column) -->
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
                            <?php if ($vehicleData['primary_photo_path']): ?>
                                <img src="<?php echo BASE_URL . $vehicleData['primary_photo_path']; ?>"
                                    alt="<?php echo htmlspecialchars($vehicleData['vehicle_id']); ?>">
                            <?php else: ?>
                                <div class="car-placeholder">
                                    <i data-lucide="truck" style="width: 48px; height: 48px;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h2 style="margin-bottom: var(--space-2); font-size: 1.5rem; line-height: 1.2;">
                        <?php echo htmlspecialchars($vehicleData['brand'] . ' ' . $vehicleData['model']); ?>
                    </h2>
                    <p
                        style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                        <?php echo htmlspecialchars($vehicleData['plate_number']); ?>
                    </p>
                    <p
                        style="color: var(--primary-600); font-size: 0.875rem; font-weight: bold; margin-bottom: var(--space-4);">
                        <?php echo htmlspecialchars($vehicleData['category_name']); ?> •
                        <?php echo htmlspecialchars($vehicleData['year_model']); ?>
                    </p>

                    <?php
                    $statusColor = 'var(--secondary-500)';
                    if ($vehicleData['current_status'] === 'available')
                        $statusColor = 'var(--success)';
                    if ($vehicleData['current_status'] === 'rented')
                        $statusColor = 'var(--warning)';
                    if ($vehicleData['current_status'] === 'maintenance')
                        $statusColor = 'var(--danger)';
                    ?>
                    <div
                        style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: <?= $statusColor ?>; color: white; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">
                        <span style="width: 6px; height: 6px; background: white; border-radius: 50%;"></span>
                        <?php echo htmlspecialchars($vehicleData['current_status']); ?>
                    </div>

                    <div
                        style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
                        <p
                            style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                            Integrity Matrix</p>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Odometer</span>
                            <strong><?php echo number_format($vehicleData['mileage']); ?> KM</strong>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Powertrain</span>
                            <strong><?php echo htmlspecialchars($vehicleData['fuel_type']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Transmission</span>
                            <strong><?php echo htmlspecialchars($vehicleData['transmission']); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php if ($vehicleData['current_status'] === 'available'): ?>
                    <a href="../rentals/reserve.php?vehicle_id=<?php echo urlencode($vehicleId); ?>" class="btn btn-primary"
                        style="justify-content: center;">
                        <i data-lucide="key" class="w-4 h-4"></i> Deploy Asset
                    </a>
                <?php endif; ?>
                <?php if ($authUser->hasPermission('vehicles.update')): ?>
                    <a href="vehicle-edit.php?id=<?php echo urlencode($vehicleId); ?>" class="btn btn-secondary"
                        style="justify-content: center;">
                        <i data-lucide="edit-3" class="w-4 h-4"></i> Modify Registry
                    </a>
                <?php endif; ?>
                <a href="qr-generator.php?id=<?php echo urlencode($vehicleId); ?>" class="btn btn-ghost"
                    style="justify-content: center;">
                    <i data-lucide="qr-code" class="w-4 h-4"></i> Asset Tag
                </a>
                <?php if ($authUser->hasPermission('vehicles.delete')): ?>
                    <button type="button" onclick="confirmDelete('<?php echo urlencode($vehicleId); ?>')" class="btn"
                        style="justify-content: center; background:transparent; border:1px solid var(--danger); color:var(--danger);">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> Decommission
                    </button>
                <?php endif; ?>
            </div>

            <!-- Asset Tag Preview -->
            <div class="card" style="text-align: center;">
                <div class="card-body" style="display: flex; flex-direction: column; align-items: center;">
                    <div
                        style="font-size: 0.75rem; font-weight: bold; text-transform: uppercase; color: var(--text-muted); margin-bottom: var(--space-4); letter-spacing: 0.05em;">
                        Digital Asset Token</div>
                    <div
                        style="background: white; padding: var(--space-3); border-radius: var(--radius-md); border: 1px solid var(--border-color); display: inline-block;">
                        <?php
                        $qrSrc = '';
                        if (!empty($vehicleData['qr_code_path'])) {
                            $qrPhysFile = BASE_PATH . ltrim($vehicleData['qr_code_path'], '/\\');
                            $qrSrc = BASE_URL . $vehicleData['qr_code_path']
                                . (is_file($qrPhysFile) ? '?v=' . filemtime($qrPhysFile) : '');
                        }
                        ?>
                        <?php if (!empty($qrSrc)): ?>
                            <img src="<?= $qrSrc ?>" style="width: 120px; height: 120px; opacity: 0.9;" alt="Asset QR">
                        <?php else: ?>
                            <div
                                style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; background: var(--secondary-50); color: var(--text-muted); font-size: 0.75rem; font-weight: bold;">
                                QR NOT GEN
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Sections (Right Column) -->
        <div class="flex flex-col gap-6">

            <!-- Live Status Alert -->
            <?php if ($currentRental): ?>
                <div class="card"
                    style="background: #064e3b; color: white; border: none; overflow: hidden; position: relative;">
                    <i data-lucide="gauge"
                        style="position: absolute; right: -40px; bottom: -40px; width: 200px; height: 200px; color: rgba(255,255,255,0.05); transform: rotate(15deg);"></i>
                    <div class="card-body" style="position: relative; z-index: 1;">
                        <h2
                            style="margin-bottom: var(--space-4); margin-top: 0; color: white; display: flex; align-items: center; gap: 8px; font-size: 1rem;">
                            <span
                                style="background: rgba(255,255,255,0.2); padding: 6px; border-radius: 8px; display: inline-flex;"><i
                                    data-lucide="navigation" style="width: 16px; height: 16px;"></i></span> Active Mission
                            Protocol
                        </h2>
                        <div class="grid"
                            style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-4);">
                            <div>
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: rgba(167,243,208,0.85); margin-bottom: 4px;">Contractor</label>
                                <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                    <?php echo htmlspecialchars($currentRental['first_name'] . ' ' . $currentRental['last_name']); ?>
                                </p>
                            </div>
                            <div>
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: rgba(167,243,208,0.85); margin-bottom: 4px;">Agreement
                                    Ref</label>
                                <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                    #<?php echo htmlspecialchars($currentRental['agreement_number']); ?>
                                </p>
                            </div>
                            <div>
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: rgba(167,243,208,0.85); margin-bottom: 4px;">Return
                                    ETD</label>
                                <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                    <?php echo formatDate($currentRental['rental_end_date']); ?>
                                </p>
                            </div>
                        </div>
                        <div style="margin-top: var(--space-5); text-align: right;">
                            <a href="../rentals/view.php?id=<?php echo $currentRental['agreement_id']; ?>"
                                style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background: white; color: #064e3b; text-decoration: none; font-weight: bold; font-size: 0.75rem; text-transform: uppercase; border-radius: var(--radius-md);">
                                Inspect Logistics Agreement
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Technical Specifications -->
            <div class="card">
                <div class="card-body">
                    <h2 style="margin-bottom: var(--space-4); margin-top: 0;">Technical Specifications Dossier</h2>
                    <div class="grid"
                        style="grid-template-columns: 1fr 1fr; gap: var(--space-4); background: var(--secondary-50); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Chassis
                                Registry</label>
                            <p style="font-weight: bold; margin: 0; font-family: monospace; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($vehicleData['chassis_number'] ?? '') ?: 'NOT_LOGGED'; ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Engine
                                Reference</label>
                            <p style="font-weight: bold; margin: 0; font-family: monospace; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($vehicleData['engine_number'] ?? '') ?: 'NOT_LOGGED'; ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Purchase
                                Value</label>
                            <p style="font-weight: bold; margin: 0;">
                                <?php echo formatCurrency($vehicleData['acquisition_cost']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Regulatory Compliance -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom: 1px solid var(--border-color); padding: var(--space-4); margin: -var(--space-4) -var(--space-4) var(--space-4) -var(--space-4); display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title"
                        style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;"><i
                            data-lucide="shield-check" style="width:18px;height:18px;color:var(--success);"></i>
                        Compliance Registry</h2>
                    <?php if ($authUser->hasPermission('compliance.create')): ?>
                        <a href="../../modules/compliance/renew-upload.php?vehicle_id=<?php echo urlencode($vehicleId); ?>"
                            class="btn btn-ghost btn-sm" style="font-size:0.75rem;" title="Archive New Instrument">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Record
                        </a>
                    <?php endif; ?>
                </div>
                <div style="margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <?php if (empty($complianceRecords)): ?>
                        <div
                            style="text-align:center;padding:3rem;color:var(--text-muted);font-weight:bold;font-size:0.875rem;">
                            No active instruments logged.</div>
                    <?php else: ?>
                        <div style="padding: var(--space-4); display: flex; flex-direction: column; gap: var(--space-3);">
                            <?php foreach ($complianceRecords as $comp): ?>
                                <?php
                                $expiryTime = strtotime($comp['expiry_date']);
                                $isExpired = $expiryTime < time();
                                $isWarning = $expiryTime < (time() + (30 * 24 * 60 * 60));

                                $bgStatus = $isExpired ? 'var(--danger-50)' : ($isWarning ? 'var(--warning-50)' : 'var(--secondary-50)');
                                $borderStatus = $isExpired ? 'var(--danger-200)' : ($isWarning ? 'var(--warning-200)' : 'var(--secondary-200)');
                                $textStatus = $isExpired ? 'var(--danger-700)' : ($isWarning ? 'var(--warning-700)' : 'var(--success-700)');
                                ?>
                                <a href="<?= BASE_URL ?>modules/compliance/instrument-view.php?id=<?= $comp['record_id'] ?>"
                                    style="display: block; background: <?= $bgStatus ?>; border: 1px solid <?= $borderStatus ?>; border-radius: var(--radius-md); padding: var(--space-3); display: flex; justify-content: space-between; align-items: center; text-decoration: none; cursor: pointer; transition: filter 0.15s, box-shadow 0.15s;"
                                    onmouseover="this.style.filter='brightness(0.96)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'"
                                    onmouseout="this.style.filter='';this.style.boxShadow=''">
                                    <div>
                                        <div
                                            style="font-weight: bold; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-main); margin-bottom: 4px;">
                                            <?php echo str_replace('_', ' ', $comp['compliance_type']); ?>
                                        </div>
                                        <div style="font-weight: 800; font-size: 0.9em; color: var(--text-secondary);">
                                            <?php echo formatDate($comp['expiry_date']); ?>
                                        </div>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <div
                                            style="font-weight: bold; font-size: 0.7em; text-transform: uppercase; color: <?= $textStatus ?>; padding: 4px 8px; background: white; border-radius: 4px; border: 1px solid <?= $borderStatus ?>;">
                                            <?php echo $isExpired ? 'BREACHED' : ($isWarning ? 'EXPIRING' : 'VALID'); ?>
                                        </div>
                                        <i data-lucide="chevron-right"
                                            style="width:14px;height:14px;color:<?= $textStatus ?>;flex-shrink:0;"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Maintenance History -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom: 1px solid var(--border-color); padding: var(--space-4); margin: -var(--space-4) -var(--space-4) var(--space-4) -var(--space-4); display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title"
                        style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;"><i
                            data-lucide="tool" style="width:18px;height:18px;color:var(--warning-600);"></i> Maintenance
                        Records</h2>
                    <?php if ($authUser->hasPermission('maintenance.view')): ?>
                        <a href="../../modules/maintenance/history.php?vehicle_id=<?php echo urlencode($vehicleId); ?>"
                            class="btn btn-ghost btn-sm" style="font-size:0.75rem;" title="View all maintenance">
                            <i data-lucide="external-link" style="width:14px;height:14px;"></i> View All
                        </a>
                    <?php endif; ?>
                </div>
                <div style="margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <?php if (empty($maintenanceHistory)): ?>
                        <div
                            style="text-align:center;padding:3rem;color:var(--text-muted);font-weight:bold;font-size:0.875rem;">
                            No completed maintenance records found.</div>
                    <?php else: ?>
                        <div style="padding: var(--space-4); display: flex; flex-direction: column; gap: var(--space-3);">
                            <?php foreach ($maintenanceHistory as $maint): ?>
                                <div
                                    style="background: var(--secondary-50); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: var(--space-3); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div
                                            style="font-weight: bold; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-main); margin-bottom: 4px;">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $maint['service_type'])); ?>
                                        </div>
                                        <div
                                            style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 4px; font-weight: 500;">
                                            <?php echo htmlspecialchars($maint['service_description'] ?? 'Routine service'); ?>
                                        </div>
                                        <div style="font-weight: 800; font-size: 0.8em; color: var(--text-muted);">
                                            <?= formatDate($maint['service_date']) ?> &bull;
                                            <?= number_format($maint['mileage_at_service']) ?> KM
                                        </div>
                                    </div>
                                    <div style="font-weight: 900; font-size: 0.9em; color: var(--text-main);">
                                        <?= formatCurrency($maint['total_cost']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Operational History -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom: 1px solid var(--border-color); padding: var(--space-4); margin: -var(--space-4) -var(--space-4) var(--space-4) -var(--space-4); display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title"
                        style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;"><i
                            data-lucide="history" style="width:18px;height:18px;color:var(--primary);"></i> Recent
                        Technical Evolution</h2>
                    <?php if ($authUser->hasPermission('vehicles.update')): ?>
                        <a href="vehicle-status-update.php?id=<?php echo urlencode($vehicleId); ?>"
                            class="btn btn-ghost btn-sm" style="font-size:0.75rem;" title="Log Status Change">
                            <i data-lucide="activity" style="width:14px;height:14px;"></i> Log Status
                        </a>
                    <?php endif; ?>
                </div>
                <div class="table-container"
                    style="border:none; margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: var(--secondary-50); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th
                                    style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Terminal Change</th>
                                <th
                                    style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Previous State</th>
                                <th
                                    style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    New Vector</th>
                                <th
                                    style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Intelligence Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($statusHistory)): ?>
                                <tr>
                                    <td colspan="4"
                                        style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 0.875rem; font-weight: bold;">
                                        No terminal changes logged.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($statusHistory as $hist): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td
                                            style="padding: 12px 16px; font-weight: bold; font-size: 0.8rem; color: var(--text-main);">
                                            <?php echo formatDateTime($hist['changed_at']); ?>
                                        </td>
                                        <td
                                            style="padding: 12px 16px; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: bold;">
                                            <?php echo htmlspecialchars($hist['previous_status'] ?? 'NONE'); ?>
                                        </td>
                                        <td style="padding: 12px 16px;">
                                            <span class="badge badge-secondary"
                                                style="font-size: 0.7rem; font-weight: 800;"><?php echo htmlspecialchars($hist['new_status'] ?? ''); ?></span>
                                        </td>
                                        <td
                                            style="padding: 12px 16px; font-size: 0.8rem; color: var(--text-secondary); max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600;">
                                            <?php echo htmlspecialchars($hist['changed_by_name'] ?? '') ?: 'SYSTEM_AUTOMATION'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Asset Deployment History -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom: 1px solid var(--border-color); padding: var(--space-4); margin: -var(--space-4) -var(--space-4) var(--space-4) -var(--space-4); display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title"
                        style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;"><i
                            data-lucide="book-open" style="width:18px;height:18px;color:var(--primary);"></i> Deployment
                        History (Dispatch &amp; Return)</h2>
                    <?php if ($authUser->hasPermission('rentals.create')): ?>
                        <a href="../../modules/rentals/rental-create.php?vehicle_id=<?php echo urlencode($vehicleId); ?>"
                            class="btn btn-ghost btn-sm" style="font-size:0.75rem;" title="Create Reservation">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Booking
                        </a>
                    <?php endif; ?>
                </div>
                <div class="table-container"
                    style="border:none; margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: var(--secondary-50); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th
                                    style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Agreement</th>
                                <th
                                    style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Contractor</th>
                                <th
                                    style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Timeline (Dispatch &rarr; Return)</th>
                                <th
                                    style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Financials</th>
                                <th
                                    style="padding: 12px 16px; text-align: center; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                    Docs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rentalHistory)): ?>
                                <tr>
                                    <td colspan="5"
                                        style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 0.875rem; font-weight: bold;">
                                        No deployment records matched.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rentalHistory as $rh): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td
                                            style="padding: 12px 16px; font-weight: bold; font-size: 0.8rem; color: var(--primary-600);">
                                            <a href="../rentals/view.php?id=<?= $rh['agreement_id'] ?>"
                                                style="color: inherit; text-decoration: none;">
                                                #<?= htmlspecialchars($rh['agreement_number']) ?>
                                            </a>
                                            <div
                                                style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; font-weight: 800;">
                                                <?= htmlspecialchars($rh['status']) ?>
                                            </div>
                                        </td>
                                        <td
                                            style="padding: 12px 16px; font-size: 0.8rem; font-weight: bold; color: var(--text-main);">
                                            <?= htmlspecialchars($rh['first_name'] . ' ' . $rh['last_name']) ?>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 0.8rem; color: var(--text-main);">
                                            <div
                                                style="color: var(--warning-600); font-weight: 800; font-size: 0.75rem; margin-bottom: 2px;">
                                                DISPATCH: <?= formatDate($rh['rental_start_date']) ?></div>
                                            <div style="color: var(--success-600); font-weight: 800; font-size: 0.75rem;">
                                                RETURN:
                                                <?= $rh['actual_return_date'] ? formatDate($rh['actual_return_date']) : 'PENDING' ?>
                                            </div>
                                        </td>
                                        <td
                                            style="padding: 12px 16px; font-size: 0.8rem; font-weight: 900; color: var(--text-main);">
                                            <?= formatCurrency($rh['total_amount'] ?? $rh['base_amount']) ?>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <a href="../rentals/view.php?id=<?= $rh['agreement_id'] ?>"
                                                    class="btn btn-ghost btn-sm" title="View Agreement Details">
                                                    <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                                                </a>
                                                <?php if ($rh['agreement_pdf_path']): ?>
                                                    <a href="<?= BASE_URL . $rh['agreement_pdf_path'] ?>" target="_blank"
                                                        class="btn btn-ghost btn-sm" title="Print Contract">
                                                        <i data-lucide="printer" style="width: 14px; height: 14px;"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

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
                        <i data-lucide="files" style="width: 18px; height: 18px; color: var(--primary);"></i> Vehicle Documents
                    </h2>
                    <?php if ($authUser->hasPermission('vehicles.update')): ?>
                        <button type="button" onclick="document.getElementById('uploadDocForm').style.display='block'; this.style.display='none';" class="btn btn-ghost btn-sm" style="font-size:0.75rem;" title="Upload New Document">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Document
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($authUser->hasPermission('vehicles.update')): ?>
                    <form id="uploadDocForm" method="POST" enctype="multipart/form-data" style="display:none; padding:1rem; background:var(--bg-muted); border-radius:var(--radius-md); margin-bottom:1rem; border:1px solid var(--border-color);">
                        <input type="hidden" name="vehicle_id" value="<?= htmlspecialchars($vehicleId) ?>">
                        <p style="margin:0 0 1rem 0; font-size:0.875rem; font-weight:700;">Upload New Document</p>
                        <div class="grid" style="grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom:0.75rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">File * (Max 10MB)</label>
                                <input type="file" name="document_file" required class="form-control" style="padding:4px;">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">Category *</label>
                                <select name="document_category" required class="form-control" style="padding:6px;">
                                    <option value="registration">OR/CR (Registration)</option>
                                    <option value="insurance">Insurance Policy</option>
                                    <option value="permit">Mayor's/Business Permit</option>
                                    <option value="inspection">Emission/Inspection</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">Title & Expiry Date (Optional)</label>
                                <div style="display:flex; gap:0.75rem;">
                                    <input type="text" name="document_title" class="form-control" placeholder="e.g. 2024 LTO Registration" style="flex:1;">
                                    <input type="date" name="expires_at" class="form-control" style="width:150px;">
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <button type="button" onclick="document.getElementById('uploadDocForm').style.display='none'; document.querySelector('#uploadDocForm').previousElementSibling.querySelector('button').style.display='inline-flex';" class="btn btn-sm btn-ghost">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Save Document</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="table-container" style="border:none; margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <?php if (empty($vehicleDocs)): ?>
                            <div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:0.875rem;">No documents attached to this vehicle.</div>
                    <?php else: ?>
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: var(--secondary-50); border-bottom: 1px solid var(--border-color);">
                                    <tr>
                                        <th style="padding: 10px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase;">Document</th>
                                        <th style="padding: 10px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase;">Date</th>
                                        <th style="padding: 10px 16px; text-align: right; font-size: 0.75rem; text-transform: uppercase;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehicleDocs as $doc): ?>
                                            <tr style="border-bottom: 1px solid var(--border-color);">
                                                <td style="padding: 10px 16px;">
                                                    <div style="font-weight:700; font-size:0.85rem; color:var(--text-main); display:flex; align-items:center; gap:6px;">
                                                        <i data-lucide="file-text" style="width:14px;height:14px;color:var(--text-muted);"></i>
                                                        <?= htmlspecialchars($doc['title']) ?>
                                                    </div>
                                                    <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; margin-top:2px;">
                                                        <?= htmlspecialchars($doc['document_category']) ?> &bull; <?= round($doc['file_size'] / 1024) ?> KB
                                                    </div>
                                                </td>
                                                <td style="padding: 10px 16px; font-size:0.8rem;">
                                                    <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?>
                                                </td>
                                                <td style="padding: 10px 16px; text-align:right;">
                                                    <a href="../documents/serve.php?id=<?= $doc['document_id'] ?>" target="_blank" class="btn btn-sm btn-ghost" title="View"><i data-lucide="external-link" style="width:14px;height:14px;"></i></a>
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

<script>
    lucide.createIcons();

    function confirmDelete(id) {
        openGcrModal({
            title: 'Decommission Asset',
            message: 'Are you sure you want to decommission this asset? This vector will be archived from the active fleet registry.',
            variant: 'danger',
            confirmLabel: 'Execute Archive',
            icon: 'trash-2',
            onConfirm: function () {
                window.location.href = 'vehicle-delete.php?id=' + id;
            }
        });
    }
</script>

<?php if ($success): ?>
        <div id="vehicle-toast"
            style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
            <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
            <span style="flex:1;"><?= htmlspecialchars($success) ?></span>
            <button onclick="document.getElementById('vehicle-toast').remove()"
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
                var t = document.getElementById('vehicle-toast');
                if (t) {
                    t.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    t.style.opacity = '0';
                    t.style.transform = 'translateX(60px)';
                    setTimeout(function () { if (t) t.remove(); }, 400);
                }
            }, 3500);
        </script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>