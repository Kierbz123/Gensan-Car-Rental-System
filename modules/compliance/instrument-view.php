<?php
/**
 * View Archived Compliance Instrument
 * Path: modules/compliance/instrument-view.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('compliance.view');

$db = Database::getInstance();
$recordId = $_GET['id'] ?? 0;

try {
    $record = $db->fetchOne(
        "SELECT c.*, v.plate_number, v.brand, v.model, v.primary_photo_path,
                u.first_name as archiver_first, u.last_name as archiver_last
         FROM compliance_records c
         JOIN vehicles v ON c.vehicle_id = v.vehicle_id
         LEFT JOIN users u ON c.created_by = u.user_id
         WHERE c.record_id = ?",
        [$recordId]
    );

    if (!$record) {
        $_SESSION['error_message'] = 'Compliance instrument not found.';
        header('Location: index.php');
        exit;
    }

    /* ── Renewal / transaction history for this type on this vehicle ── */
    $renewalHistory = $db->fetchAll(
        "SELECT cr.*, CONCAT(u.first_name,' ',u.last_name) AS created_by_name
         FROM compliance_records cr
         LEFT JOIN users u ON cr.created_by = u.user_id
         WHERE cr.vehicle_id = ? AND cr.compliance_type = ?
         ORDER BY cr.expiry_date DESC",
        [$record['vehicle_id'], $record['compliance_type']]
    );

    /* ── Full compliance snapshot: latest record per type for this vehicle ── */
    $allCompliance = $db->fetchAll(
        "SELECT cr.*
         FROM compliance_records cr
         WHERE cr.vehicle_id = ?
           AND cr.record_id = (
               SELECT MAX(c2.record_id)
               FROM compliance_records c2
               WHERE c2.vehicle_id = cr.vehicle_id
                 AND c2.compliance_type = cr.compliance_type
           )
         ORDER BY cr.expiry_date ASC",
        [$record['vehicle_id']]
    );

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error retrieving record.';
    header('Location: index.php');
    exit;
}

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$pageTitle = 'View Compliance Instrument';
require_once '../../includes/header.php';

$base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

// Calculate status and days left
$diff = ceil((strtotime($record['expiry_date']) - time()) / (60 * 60 * 24));
$statusText = $diff < 0 ? 'BREACHED' : ($diff <= 30 ? 'EXPIRING SOON' : 'VALID');
$statusColor = $diff < 0 ? 'danger' : ($diff <= 30 ? 'warning' : 'success');

// Compliance Labels
$complianceTypes = [
    'lto_registration' => 'LTO Registration',
    'insurance_comprehensive' => 'Insurance (Comprehensive)',
    'insurance_tpl' => 'Insurance (TPL)',
    'emission_test' => 'Emission Test',
    'franchise_ltfrb' => 'Franchise (LTFRB)',
    'pnp_clearance' => 'PNP Clearance',
    'mayors_permit' => "Mayor's Permit",
];
$typeLabel = $complianceTypes[$record['compliance_type']] ?? strtoupper(str_replace('_', ' ', $record['compliance_type']));

?>

<div class="fade-in max-w-6xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 mb-6 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Compliance Registry
        </a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600">Document Profile</span>
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
                            <?php if (!empty($record['primary_photo_path'])): ?>
                                <img src="<?php echo BASE_URL . ltrim($record['primary_photo_path'], '/'); ?>"
                                    alt="<?php echo htmlspecialchars($record['vehicle_id'] ?? ''); ?>">
                            <?php else: ?>
                                <div class="car-placeholder">
                                    <i data-lucide="shield-check" style="width: 48px; height: 48px;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h2 style="margin-bottom: var(--space-2); font-size: 1.5rem; line-height: 1.2;">
                        <?= htmlspecialchars($typeLabel) ?>
                    </h2>
                    <p
                        style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                        <?= htmlspecialchars($record['plate_number']) ?>
                    </p>
                    <p
                        style="color: var(--text-secondary); font-size: 0.875rem; font-weight: bold; margin-bottom: var(--space-4);">
                        <?= htmlspecialchars($record['brand'] . ' ' . $record['model']) ?>
                    </p>

                    <?php
                    $statusColorHex = 'var(--secondary-500)';
                    if ($statusColor === 'success')
                        $statusColorHex = 'var(--success)';
                    if ($statusColor === 'warning')
                        $statusColorHex = 'var(--warning)';
                    if ($statusColor === 'danger')
                        $statusColorHex = 'var(--danger)';
                    ?>
                    <div
                        style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: <?= $statusColorHex ?>; color: white; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">
                        <span style="width: 6px; height: 6px; background: white; border-radius: 50%;"></span>
                        <?= $statusText ?>
                    </div>

                    <div
                        style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
                        <p
                            style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                            Document Info</p>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Ref No.</span>
                            <strong
                                style="font-family: monospace;"><?= htmlspecialchars($record['document_number'] ?? 'N/A') ?></strong>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Issued</span>
                            <strong><?= date('M d, Y', strtotime($record['issue_date'])) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Expiry</span>
                            <span style="font-weight: bold; color: <?= $statusColorHex ?>;">
                                <?= date('M d, Y', strtotime($record['expiry_date'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php if (!empty($record['document_file_path'])): ?>
                    <a href="<?= $base ?>/<?= htmlspecialchars($record['document_file_path']) ?>" target="_blank"
                        class="btn btn-primary" style="justify-content: center;">
                        <i data-lucide="eye" class="w-4 h-4"></i> View Document
                    </a>
                    <a href="<?= $base ?>/<?= htmlspecialchars($record['document_file_path']) ?>" download
                        class="btn btn-secondary" style="justify-content: center;">
                        <i data-lucide="download" class="w-4 h-4"></i> Download Document
                    </a>
                <?php endif; ?>
                <a href="generate-pdf.php?id=<?= (int) $recordId ?>" target="_blank" class="btn btn-secondary"
                    style="justify-content: center;">
                    <i data-lucide="file-text" class="w-4 h-4"></i> Generate PDF
                </a>
                <a href="../asset-tracking/vehicle-details.php?id=<?= urlencode((string) $record['vehicle_id']) ?>"
                    class="btn btn-ghost" style="justify-content: center;">
                    <i data-lucide="truck" class="w-4 h-4"></i> Asset Profile
                </a>
            </div>
        </div>

        <!-- Detail Sections (Right Column) -->
        <div class="flex flex-col gap-6">

            <!-- Regulatory Details -->
            <div class="card">
                <div class="card-body">
                    <h2
                        style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="file-text" style="color: var(--primary);"></i> Regulatory Details
                    </h2>
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Issuing
                                Authority</label>
                            <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                <?= htmlspecialchars($record['issuing_authority'] ?? 'N/A') ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Renewal
                                Cost</label>
                            <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                <?= $record['renewal_cost'] ? '₱' . number_format($record['renewal_cost'], 2) : 'N/A' ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Archived
                                By</label>
                            <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                <?= htmlspecialchars(($record['archiver_first'] ?? 'System') . ' ' . ($record['archiver_last'] ?? '')) ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Validity
                                Status</label>
                            <p style="font-weight: bold; margin: 0; font-size: 1rem; color: <?= $statusColorHex ?>;">
                                <?= $diff < 0 ? abs($diff) . ' days lapsed' : $diff . ' days remaining' ?>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($record['notes'])): ?>
                        <div
                            style="margin-top: var(--space-4); border-top: 1px solid var(--border-color); padding-top: var(--space-4);">
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Remarks
                                / Notes</label>
                            <p
                                style="margin: 0; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.5; white-space: pre-wrap;">
                                <?= htmlspecialchars($record['notes']) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lifecycle Tracking -->
            <div class="card">
                <div class="card-body">
                    <h2
                        style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="shield-check" style="color: var(--primary);"></i> Lifecycle Tracking
                    </h2>

                    <div
                        style="position: relative; padding-left: 20px; border-left: 2px solid var(--border-color); margin-left: 10px; display: flex; flex-direction: column; gap: var(--space-4);">
                        <div style="position: relative;">
                            <div
                                style="position: absolute; left: -27px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: var(--success); border: 2px solid white;">
                            </div>
                            <h3 style="font-size: 0.875rem; font-weight: bold; margin: 0 0 4px 0;">Archived</h3>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">
                                <?= date('M d, Y h:i A', strtotime($record['created_at'])) ?>
                            </div>
                            <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">Instrument
                                successfully registered to fleet asset.</p>
                        </div>
                        <div style="position: relative;">
                            <div
                                style="position: absolute; left: -27px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: var(--secondary-300); border: 2px solid white;">
                            </div>
                            <h3
                                style="font-size: 0.875rem; font-weight: bold; margin: 0 0 4px 0; color: var(--text-muted);">
                                Renewal Required</h3>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">
                                <?= date('M d, Y', strtotime($record['expiry_date'])) ?>
                            </div>
                            <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">Asset becomes legally
                                void if not renewed.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vehicle Regulatory Snapshot -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom:1px solid var(--border-color); padding:var(--space-4); display:flex; justify-content:space-between; align-items:center;">
                    <h2 class="card-title" style="margin:0; font-size:1rem; display:flex; align-items:center; gap:8px;">
                        <i data-lucide="shield-check" style="color:var(--primary); width:18px; height:18px;"></i>
                        Vehicle Regulatory Snapshot
                    </h2>
                    <a href="vehicle-compliance.php?vehicle_id=<?= urlencode($record['vehicle_id']) ?>"
                        class="btn btn-ghost btn-sm" style="font-size:0.75rem;">
                        <i data-lucide="external-link" style="width:13px;height:13px;"></i> All Records
                    </a>
                </div>
                <div style="padding:var(--space-4); display:flex; flex-direction:column; gap:var(--space-2);">
                    <?php if (empty($allCompliance)): ?>
                        <p style="color:var(--text-muted); font-size:0.875rem; text-align:center; padding:var(--space-4);">
                            No compliance records found for this vehicle.</p>
                    <?php else:
                        foreach ($allCompliance as $comp):
                            $cDiff = ceil((strtotime($comp['expiry_date']) - time()) / 86400);
                            $cExp = $cDiff < 0;
                            $cWarn = !$cExp && $cDiff <= 30;
                            $cColor = $cExp ? 'var(--danger)' : ($cWarn ? 'var(--warning)' : 'var(--success)');
                            $cBg = $cExp ? 'var(--danger-50,#fef2f2)' : ($cWarn ? 'var(--warning-50,#fffbeb)' : 'var(--bg-muted)');
                            $cBorder = $cExp ? 'var(--danger-200,#fecaca)' : ($cWarn ? 'var(--warning-200,#fde68a)' : 'var(--border-color)');
                            $cLabel = $cExp ? 'BREACHED' : ($cWarn ? 'EXPIRING' : 'VALID');
                            $isActive = ($comp['record_id'] == $recordId);
                            $cTypeLabel = $complianceTypes[$comp['compliance_type']] ?? strtoupper(str_replace('_', ' ', $comp['compliance_type']));
                            ?>
                            <div style="display:flex; justify-content:space-between; align-items:center;
                                  background:<?= $cBg ?>; border:1px solid <?= $cBorder ?>;
                                  <?= $isActive ? 'outline:2px solid var(--accent); outline-offset:1px;' : '' ?>
                                  border-radius:var(--radius-md); padding:var(--space-3) var(--space-4);">
                                <div>
                                    <div style="font-weight:700; font-size:0.8125rem; color:var(--text-main);">
                                        <?= htmlspecialchars($cTypeLabel) ?>
                                        <?php if ($isActive): ?>
                                            <span
                                                style="font-size:0.65rem; background:var(--accent); color:#fff; padding:1px 6px; border-radius:4px; margin-left:6px; vertical-align:middle;">Current</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                        Exp: <?= date('M d, Y', strtotime($comp['expiry_date'])) ?>
                                        <?php if ($comp['document_number']): ?>
                                            · #<?= htmlspecialchars($comp['document_number']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span
                                    style="font-size:0.7rem; font-weight:700; color:<?= $cColor ?>; padding:3px 8px; background:white; border-radius:4px; border:1px solid <?= $cBorder ?>; text-transform:uppercase;">
                                    <?= $cLabel ?>
                                </span>
                            </div>
                        <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Renewal / Transaction History -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom:1px solid var(--border-color); padding:var(--space-4); display:flex; justify-content:space-between; align-items:center;">
                    <h2 class="card-title" style="margin:0; font-size:1rem; display:flex; align-items:center; gap:8px;">
                        <i data-lucide="history" style="color:var(--primary); width:18px; height:18px;"></i>
                        Renewal Transaction History
                        <span
                            style="font-size:0.7rem; background:var(--bg-muted); color:var(--text-muted); padding:2px 8px; border-radius:99px; border:1px solid var(--border-color);">
                            <?= htmlspecialchars($typeLabel) ?>
                        </span>
                    </h2>
                </div>
                <div class="table-container" style="border:none; margin:0;">
                    <?php if (empty($renewalHistory)): ?>
                        <div
                            style="padding:var(--space-6); text-align:center; color:var(--text-muted); font-size:0.875rem;">
                            No transaction history found.</div>
                    <?php else: ?>
                        <table style="width:100%; border-collapse:collapse;">
                            <thead style="background:var(--bg-muted); border-bottom:1px solid var(--border-color);">
                                <tr>
                                    <th
                                        style="padding:10px 14px; text-align:left; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">
                                        Ref / Doc #</th>
                                    <th
                                        style="padding:10px 14px; text-align:left; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">
                                        Issued</th>
                                    <th
                                        style="padding:10px 14px; text-align:left; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">
                                        Expires</th>
                                    <th
                                        style="padding:10px 14px; text-align:left; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">
                                        Cost</th>
                                    <th
                                        style="padding:10px 14px; text-align:left; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">
                                        Status</th>
                                    <th
                                        style="padding:10px 14px; text-align:center; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">
                                        Docs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($renewalHistory as $h):
                                    $hDiff = ceil((strtotime($h['expiry_date']) - time()) / 86400);
                                    $hExp = $hDiff < 0;
                                    $hWarn = !$hExp && $hDiff <= 30;
                                    $hColor = $hExp ? 'var(--danger)' : ($hWarn ? 'var(--warning)' : 'var(--success)');
                                    $hLabel = $hExp ? 'BREACHED' : ($hWarn ? 'EXPIRING' : 'VALID');
                                    $hActive = ($h['record_id'] == $recordId);
                                    ?>
                                    <tr
                                        style="border-bottom:1px solid var(--border-color); <?= $hActive ? 'background:var(--accent-50,#eff6ff);' : '' ?>">
                                        <td style="padding:10px 14px;">
                                            <a href="instrument-view.php?id=<?= $h['record_id'] ?>"
                                                style="font-weight:700; font-size:0.8125rem; color:var(--accent); text-decoration:none; font-family:monospace;">
                                                <?= htmlspecialchars($h['document_number'] ?? 'N/A') ?>
                                            </a>
                                            <?php if ($hActive): ?>
                                                <div style="font-size:0.65rem; color:var(--accent); font-weight:700;">← Current View
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($h['created_by_name']): ?>
                                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">By:
                                                    <?= htmlspecialchars(trim($h['created_by_name'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td
                                            style="padding:10px 14px; font-size:0.8125rem; color:var(--text-secondary); font-weight:600;">
                                            <?= $h['issue_date'] ? date('M d, Y', strtotime($h['issue_date'])) : '—' ?>
                                        </td>
                                        <td
                                            style="padding:10px 14px; font-size:0.8125rem; font-weight:700; color:<?= $hColor ?>;">
                                            <?= date('M d, Y', strtotime($h['expiry_date'])) ?>
                                            <div style="font-size:0.7rem; color:var(--text-muted); font-weight:500;">
                                                <?= $hExp ? abs($hDiff) . ' days lapsed' : $hDiff . ' days left' ?>
                                            </div>
                                        </td>
                                        <td
                                            style="padding:10px 14px; font-size:0.8125rem; font-weight:700; color:var(--text-main);">
                                            <?= $h['renewal_cost'] ? '₱' . number_format($h['renewal_cost'], 2) : '—' ?>
                                        </td>
                                        <td style="padding:10px 14px;">
                                            <span
                                                style="font-size:0.7rem; font-weight:700; color:<?= $hColor ?>; padding:2px 8px; background:white; border-radius:4px; border:1px solid <?= str_replace(')', '-200)', str_replace('var(--', 'var(--', $hColor)) ?>; text-transform:uppercase;">
                                                <?= $hLabel ?>
                                            </span>
                                        </td>
                                        <td style="padding:10px 14px; text-align:center;">
                                            <div style="display:flex; justify-content:center; gap:4px;">
                                                <a href="instrument-view.php?id=<?= $h['record_id'] ?>"
                                                    class="btn btn-ghost btn-sm" title="View Record">
                                                    <i data-lucide="eye" style="width:13px;height:13px;"></i>
                                                </a>
                                                <?php if (!empty($h['document_file_path'])): ?>
                                                    <a href="<?= $base ?>/<?= htmlspecialchars($h['document_file_path']) ?>"
                                                        target="_blank" class="btn btn-ghost btn-sm" title="View Document">
                                                        <i data-lucide="file-text" style="width:13px;height:13px;"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Document Preview Section -->
            <?php if (!empty($record['document_file_path'])): ?>
                <div class="card p-0" style="overflow: hidden;">
                    <div class="card-header"
                        style="background: var(--secondary-50); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="card-title"
                            style="margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="eye" style="color: var(--primary); width: 18px; height: 18px;"></i> Document
                            Preview
                        </h2>
                        <span style="font-size: 0.75rem; font-family: monospace; color: var(--text-muted);">
                            <?= htmlspecialchars(basename($record['document_file_path'])) ?>
                        </span>
                    </div>
                    <div
                        style="background: var(--secondary-100); padding: var(--space-4); display: flex; justify-content: center; align-items: center; min-height: 400px;">
                        <?php
                        $ext = strtolower(pathinfo($record['document_file_path'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                            <img src="<?= $base ?>/<?= htmlspecialchars($record['document_file_path']) ?>"
                                alt="Document Preview"
                                style="max-width: 100%; max-height: 600px; object-fit: contain; border-radius: var(--radius-md); box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <?php elseif ($ext === 'pdf'): ?>
                            <iframe src="<?= $base ?>/<?= htmlspecialchars($record['document_file_path']) ?>"
                                style="width: 100%; height: 600px; border: none; border-radius: var(--radius-md); box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></iframe>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--text-muted);">
                                <i data-lucide="file"
                                    style="width: 48px; height: 48px; margin: 0 auto 16px auto; opacity: 0.5;"></i>
                                <p style="font-weight: bold; margin: 0 0 8px 0;">Preview not available</p>
                                <p style="font-size: 0.75rem; margin: 0;">Please use the Download button.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card"
                    style="background: var(--secondary-50); border: 2px dashed var(--border-color); text-align: center; padding: 48px 24px;">
                    <i data-lucide="file-x"
                        style="width: 48px; height: 48px; color: var(--text-muted); margin: 0 auto 16px auto; opacity: 0.5;"></i>
                    <h3 style="font-weight: bold; margin: 0 0 8px 0; color: var(--text-main);">No Document Available</h3>
                    <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">A physical copy or scanned document
                        was not uploaded when this instrument was archived.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>lucide.createIcons();</script>

<?php if ($successMsg): ?>
    <div id="compliance-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;">
            <?= htmlspecialchars($successMsg) ?>
        </span>
        <button onclick="document.getElementById('compliance-toast').remove()"
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
            var t = document.getElementById('compliance-toast');
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