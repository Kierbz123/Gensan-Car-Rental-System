<?php
/**
 * Archive New Compliance Instrument
 * Path: modules/compliance/renew-upload.php
 *
 * Allows staff to create a new compliance_records entry for any vehicle,
 * including document details and optional file upload.
 */

require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('compliance.create');

$db = Database::getInstance();
$errors = [];
$success = false;

// Pre-select vehicle if coming from vehicle details page
$preselectedVehicleId = $_GET['vehicle_id'] ?? '';

// ─── Fetch vehicles for dropdown ────────────────────────────────────────────
$vehicles = $db->fetchAll(
    "SELECT vehicle_id, plate_number, brand, model
     FROM vehicles
     WHERE deleted_at IS NULL
     ORDER BY brand, model, plate_number"
);

// ─── Compliance types (mirrors DB ENUM) ─────────────────────────────────────
$complianceTypes = [
    'lto_registration' => 'LTO Registration',
    'insurance_comprehensive' => 'Insurance (Comprehensive)',
    'insurance_tpl' => 'Insurance (TPL)',
    'emission_test' => 'Emission Test',
    'franchise_ltfrb' => 'Franchise (LTFRB)',
    'pnp_clearance' => 'PNP Clearance',
    'mayors_permit' => "Mayor's Permit",
];

// ─── Form submission ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF guard
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch. Please reload and try again.';
    } else {

        // Required fields
        $vehicleId = trim($_POST['vehicle_id'] ?? '');
        $complianceType = trim($_POST['compliance_type'] ?? '');
        $documentNumber = trim($_POST['document_number'] ?? '');
        $issuingAuth = trim($_POST['issuing_authority'] ?? '');
        $issueDate = trim($_POST['issue_date'] ?? '');
        $expiryDate = trim($_POST['expiry_date'] ?? '');
        $renewalCost = trim($_POST['renewal_cost'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Validate
        $validVehicleIds = array_column($vehicles, 'vehicle_id');
        if (empty($vehicleId) || !in_array($vehicleId, $validVehicleIds))
            $errors[] = 'Valid vehicle is required.';
        if (empty($complianceType) || !array_key_exists($complianceType, $complianceTypes))
            $errors[] = 'Compliance type is required.';
        if (empty($documentNumber))
            $errors[] = 'Document number is required.';
        if (empty($issueDate))
            $errors[] = 'Issue date is required.';
        if (empty($expiryDate))
            $errors[] = 'Expiry date is required.';
        if (!empty($issueDate) && !empty($expiryDate) && $expiryDate <= $issueDate)
            $errors[] = 'Expiry date must be after issue date.';
        if (!empty($renewalCost) && (!is_numeric($renewalCost) || (float)$renewalCost < 0))
            $errors[] = 'Renewal cost must be a non-negative number.';

        // File upload (optional)
        $documentFilePath = null;
        if (!empty($_FILES['document_file']['tmp_name'])) {
            $file = $_FILES['document_file'];
            $allowedMime = [
                'application/pdf', 'image/jpeg', 'image/png',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv'
            ];
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'csv'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $maxSize = MAX_UPLOAD_SIZE;

            if ($file['size'] > $maxSize) {
                $errors[] = 'File too large. Maximum allowed size is ' . round($maxSize / 1024 / 1024) . ' MB.';
            } elseif (!in_array(mime_content_type($file['tmp_name']), $allowedMime) || !in_array($ext, $allowedExts)) {
                $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC(X), XLS(X), CSV.';
            } else {
                $uploadDir = DOCUMENTS_PATH;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                // Safe filename: sanitize vehicle/type to alphanumeric+dash only
                $safeVehicleId    = preg_replace('/[^a-zA-Z0-9\-]/', '_', $vehicleId);
                $safeCompType     = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $complianceType);
                $fileName = 'compliance_' . $safeVehicleId . '_' . $safeCompType . '_' . date('Ymd_His') . '.' . $ext;
                $dest = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Store relative path from BASE_PATH
                    $documentFilePath = 'assets/images/uploads/documents/' . $fileName;
                } else {
                    $errors[] = 'File upload failed. Please check server write permissions.';
                }
            }
        }

        // Insert record
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Retire any older overlapping compliance instruments of this exact type for this vehicle
                $db->execute(
                    "UPDATE compliance_records 
                     SET status = 'renewed' 
                     WHERE vehicle_id = ? AND compliance_type = ? AND status NOT IN ('renewed', 'cancelled')",
                    [$vehicleId, $complianceType]
                );

                $newId = $db->insert(
                    "INSERT INTO compliance_records
                        (vehicle_id, compliance_type, document_number, issuing_authority,
                         issue_date, expiry_date, renewal_cost, document_file_path,
                         notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $vehicleId,
                        $complianceType,
                        $documentNumber,
                        $issuingAuth ?: null,
                        $issueDate,
                        $expiryDate,
                        $renewalCost !== '' ? (float) $renewalCost : null,
                        $documentFilePath,
                        $notes ?: null,
                        $authUser->getId(),
                    ]
                );

                // Audit log
                if (class_exists('AuditLogger')) {
                    AuditLogger::log(
                        $authUser->getId(),
                        null,
                        null,
                        'create',
                        'compliance',
                        'compliance_records',
                        null,
                        "Archived compliance instrument: {$complianceType} for {$vehicleId}",
                        null,
                        json_encode(['vehicle_id' => $vehicleId, 'type' => $complianceType]),
                        getClientIP(),
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'POST',
                        '/compliance/renew-upload',
                        'info'
                    );
                }

                $db->commit();
                $_SESSION['success_message'] = 'Compliance instrument archived successfully.';
                header('Location: instrument-view.php?id=' . urlencode((string) $newId));
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                error_log('Compliance insert error: ' . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }
    }
}

// ─── Page render ─────────────────────────────────────────────────────────────
$pageTitle = 'Archive Compliance Instrument';
require_once '../../includes/header.php';

// Build JS compliance type config
$complianceConfig = [
    'lto_registration'        => ['icon'=>'car','color'=>'#3b82f6','authority'=>'Land Transportation Office (LTO)','docLabel'=>'Certificate of Registration No.','placeholder'=>'e.g. OR-2024-0123456'],
    'insurance_comprehensive' => ['icon'=>'shield','color'=>'#8b5cf6','authority'=>'Insurance Company / Broker','docLabel'=>'Policy Number','placeholder'=>'e.g. POL-2024-XXXXX'],
    'insurance_tpl'           => ['icon'=>'shield-check','color'=>'#06b6d4','authority'=>'Insurance Company / Broker','docLabel'=>'TPL Policy Number','placeholder'=>'e.g. TPL-2024-XXXXX'],
    'emission_test'           => ['icon'=>'wind','color'=>'#10b981','authority'=>'DENR-Accredited Emission Testing Center','docLabel'=>'Certificate Number','placeholder'=>'e.g. EMT-2024-XXXXX'],
    'franchise_ltfrb'         => ['icon'=>'clipboard-list','color'=>'#f59e0b','authority'=>'Land Transportation Franchising and Regulatory Board (LTFRB)','docLabel'=>'Certificate of Public Convenience No.','placeholder'=>'e.g. CPC-LTFRB-XXXXX'],
    'pnp_clearance'           => ['icon'=>'badge','color'=>'#ef4444','authority'=>'Philippine National Police (PNP)','docLabel'=>'Clearance Certificate No.','placeholder'=>'e.g. PNP-CLR-2024-XXXXX'],
    'mayors_permit'           => ['icon'=>'building','color'=>'#f97316','authority'=>"City Mayor's Office – General Santos City",'docLabel'=>"Mayor's Permit No.",'placeholder'=>'e.g. MP-2024-XXXXX'],
];
?>

<style>
/* ── Upload drop-zone states ── */
#drop-zone { transition: border-color .2s, background .2s, transform .15s; }
#drop-zone:hover { border-color: var(--primary); background: var(--primary-light, #eff6ff); transform: scale(1.01); }
#drop-zone.drag-over { border-color: var(--primary); background: var(--primary-light, #eff6ff); transform: scale(1.02); }
#drop-zone.has-file { opacity: .5; pointer-events: none; }

/* ── Type badge pill ── */
.doc-type-badge { display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .9rem;border-radius:9999px;font-size:.78rem;font-weight:700;letter-spacing:.04em;transition:all .25s; }

/* ── Dynamic fields slide-in ── */
.dynamic-field { animation: slideDown .25s ease; }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* ── File type icon colours ── */
.ft-pdf  { background:#fee2e2;color:#dc2626; }
.ft-img  { background:#ede9fe;color:#7c3aed; }
.ft-doc  { background:#dbeafe;color:#2563eb; }
.ft-xls  { background:#dcfce7;color:#16a34a; }
.ft-csv  { background:#fef9c3;color:#ca8a04; }
.ft-generic { background:var(--bg-muted);color:var(--text-muted); }

/* ── Print preview card ── */
.print-card-header { background: linear-gradient(135deg,#1e3a8a 0%,#2563eb 100%); color:#fff; border-radius: var(--radius-lg) var(--radius-lg) 0 0; padding:1.25rem 1.5rem; }

/* ── Input focus glow ── */
.form-control:focus { box-shadow:0 0 0 3px rgba(59,130,246,.15); }

/* ── Section tab bar ── */
.tab-bar { display:flex;gap:.25rem;background:var(--bg-muted);padding:.35rem;border-radius:var(--radius-md);margin-bottom:1.5rem; }
.tab-btn { flex:1;padding:.5rem .75rem;border:none;background:transparent;border-radius:var(--radius-sm);font-size:.85rem;font-weight:600;color:var(--text-muted);cursor:pointer;transition:all .18s; }
.tab-btn.active { background:#fff;color:var(--primary);box-shadow:0 1px 4px rgba(0,0,0,.12); }
</style>

<div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1><i data-lucide="shield" style="width:26px;height:26px;vertical-align:-5px;margin-right:10px;color:var(--primary)"></i>Archive New Instrument</h1>
            <p>Register a regulatory document for a fleet vehicle.</p>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Compliance Registry
            </a>
            <span style="display:inline-flex;align-items:center;gap:.5rem;background:var(--secondary-light);padding:.5rem 1rem;border-radius:var(--radius-full);font-size:.8rem;font-weight:700;color:var(--secondary);">
                <span style="width:8px;height:8px;background:var(--primary);border-radius:50%;display:inline-block;animation:pulse 2s infinite;"></span>
                Compliance Engine v3.2
            </span>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div style="margin-bottom:2rem;padding:1rem 1.25rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-weight:500;display:flex;align-items:flex-start;gap:.5rem;">
        <i data-lucide="shield-alert" style="width:18px;height:18px;flex-shrink:0;margin-top:2px;"></i>
        <div>
            <p style="font-weight:800;text-transform:uppercase;font-size:.85rem;margin-bottom:.5rem;">Validation Failure</p>
            <ul style="margin:0;padding-left:1.5rem;font-size:.9rem;">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="compliance-form" novalidate>
        <?php echo csrfField(); ?>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:2rem;align-items:start;">

            <!-- ── LEFT COLUMN ── -->
            <div style="display:flex;flex-direction:column;gap:1.75rem;">

                <!-- 1. Deployment Unit card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="car" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>Deployment Unit</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <!-- Vehicle -->
                            <div class="form-group">
                                <label for="vehicle_id">Vehicle <span style="color:var(--danger)">*</span></label>
                                <select id="vehicle_id" name="vehicle_id" class="form-control" required>
                                    <option value="">— Select vehicle —</option>
                                    <?php foreach ($vehicles as $v):
                                        $selectedVehicle = $_POST['vehicle_id'] ?? $preselectedVehicleId; ?>
                                        <option value="<?php echo htmlspecialchars($v['vehicle_id']); ?>" <?php echo $selectedVehicle === $v['vehicle_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($v['plate_number'] . ' · ' . $v['brand'] . ' ' . $v['model']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Document Type (renamed from "Instrument Class") -->
                            <div class="form-group">
                                <label for="compliance_type">Document Type <span style="color:var(--danger)">*</span></label>
                                <select id="compliance_type" name="compliance_type" class="form-control" required>
                                    <option value="">— Select document type —</option>
                                    <?php foreach ($complianceTypes as $key => $label):
                                        $selectedType = $_POST['compliance_type'] ?? $_GET['type'] ?? ''; ?>
                                        <option value="<?php echo $key; ?>" <?php echo $selectedType === $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Document Details card (with dynamic fields) -->
                <div class="card">
                    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                        <h2 class="card-title"><i data-lucide="file-text" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--success)"></i>Document Details</h2>
                        <!-- Tab bar: Manual Input / Upload -->
                        <div class="tab-bar" style="margin-bottom:0;width:auto;">
                            <button type="button" class="tab-btn active" id="tab-manual" onclick="switchTab('manual')">
                                <i data-lucide="pencil" style="width:13px;height:13px;vertical-align:-1px;margin-right:4px;"></i>Manual Input
                            </button>
                            <button type="button" class="tab-btn" id="tab-upload" onclick="switchTab('upload')">
                                <i data-lucide="upload-cloud" style="width:13px;height:13px;vertical-align:-1px;margin-right:4px;"></i>Upload File
                            </button>
                        </div>
                    </div>

                    <!-- Manual Input panel -->
                    <div id="panel-manual" class="card-body">
                        <!-- Document Number (label changes dynamically) -->
                        <div class="form-group dynamic-field" style="margin-bottom:1.5rem;">
                            <label for="document_number"><span id="doc-number-label">Document / Certificate Number</span> <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="document_number" name="document_number" class="form-control"
                                style="font-family:'JetBrains Mono',monospace;"
                                placeholder="e.g. DOC-2024-XXXXX"
                                value="<?php echo htmlspecialchars($_POST['document_number'] ?? ''); ?>" required>
                        </div>

                        <!-- Issuing Authority (pre-filled per type) -->
                        <div class="form-group dynamic-field" style="margin-bottom:1.5rem;">
                            <label for="issuing_authority">Issuing Authority</label>
                            <input type="text" id="issuing_authority" name="issuing_authority" class="form-control"
                                placeholder="e.g. Land Transportation Office – General Santos"
                                value="<?php echo htmlspecialchars($_POST['issuing_authority'] ?? ''); ?>">
                        </div>

                        <div class="form-row" style="margin-bottom:1.5rem;">
                            <!-- Issue Date -->
                            <div class="form-group dynamic-field">
                                <label for="issue_date">Issue Date <span style="color:var(--danger)">*</span></label>
                                <input type="date" id="issue_date" name="issue_date" class="form-control"
                                    value="<?php echo htmlspecialchars($_POST['issue_date'] ?? ''); ?>"
                                    max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <!-- Expiry Date -->
                            <div class="form-group dynamic-field">
                                <label for="expiry_date">Expiry Date <span style="color:var(--danger)">*</span></label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control"
                                    value="<?php echo htmlspecialchars($_POST['expiry_date'] ?? ''); ?>" required>
                            </div>
                            <!-- Renewal Cost -->
                            <div class="form-group dynamic-field">
                                <label for="renewal_cost">Renewal Cost (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                <input type="number" id="renewal_cost" name="renewal_cost" class="form-control"
                                    placeholder="0.00" min="0" step="0.01"
                                    value="<?php echo htmlspecialchars($_POST['renewal_cost'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Dynamic Extra Fields (injected by JS) -->
                        <div id="dynamic-extra-fields"></div>

                        <!-- Notes -->
                        <div class="form-group dynamic-field">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3" class="form-control" style="resize:none;"
                                placeholder="Optional additional context or remarks…"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Upload File panel (hidden by default) -->
                    <div id="panel-upload" class="card-body" style="display:none;">
                        <!-- Drop Zone -->
                        <label for="document_file" id="drop-zone"
                            style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem;padding:2.5rem 2rem;border:2px dashed var(--border-color);border-radius:var(--radius-lg);cursor:pointer;text-align:center;">
                            <div id="dz-icon-wrap" style="width:4rem;height:4rem;background:var(--bg-muted);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;transition:background .2s;">
                                <i data-lucide="upload-cloud" style="width:28px;height:28px;color:var(--primary);"></i>
                            </div>
                            <div>
                                <p style="font-weight:700;color:var(--text-main);margin-bottom:.25rem;">Click or drag files here</p>
                                <p style="font-size:.85rem;color:var(--text-muted);">PDF, JPG, PNG, DOC, XLS, CSV &middot; Max <?php echo round(MAX_UPLOAD_SIZE / 1024 / 1024); ?>MB</p>
                            </div>
                            <input type="file" id="document_file" name="document_file" style="display:none;"
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv">
                        </label>

                        <!-- File Preview -->
                        <div id="file-preview" style="display:none;margin-top:1rem;">
                            <div style="display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--success-light);border-radius:var(--radius-md);">
                                <div id="file-type-icon" style="width:2.5rem;height:2.5rem;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex-shrink:0;" class="ft-generic">
                                    <i data-lucide="file" style="width:18px;height:18px;"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <p id="file-name" style="font-weight:700;color:var(--success);font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:.15rem;"></p>
                                    <p id="file-size" style="font-size:.78rem;color:var(--text-muted);"></p>
                                </div>
                                <button type="button" id="clear-file" title="Remove file"
                                    style="background:none;border:none;cursor:pointer;color:var(--danger);padding:.4rem;border-radius:4px;transition:background .15s;"
                                    onmouseenter="this.style.background='var(--danger-light)'" onmouseleave="this.style.background='none'">
                                    <i data-lucide="x" style="width:16px;height:16px;"></i>
                                </button>
                            </div>
                        </div>

                        <p style="font-size:.8rem;color:var(--text-muted);margin-top:1rem;text-align:center;">
                            <i data-lucide="info" style="width:12px;height:12px;vertical-align:-1px;"></i>
                            Uploading a file is optional. Fill in document details in the Manual Input tab.
                        </p>
                    </div>
                </div>

                <!-- 3. Official Government Documents card -->
                <div class="card">
                    <div class="print-card-header">
                        <div style="display:flex;align-items:center;gap:.75rem;">
                            <i data-lucide="landmark" style="width:20px;height:20px;opacity:.85;"></i>
                            <div>
                                <h2 style="margin:0;font-size:1rem;font-weight:700;">Official Government Documents</h2>
                                <p style="margin:0;font-size:.8rem;opacity:.75;">Select a document type to view official government references</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="padding:1.25rem 1.5rem;">

                        <!-- Placeholder when no type selected -->
                        <div id="gov-ref-empty" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;padding:1.5rem 1rem;text-align:center;color:var(--text-muted);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4;"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>
                            <p style="font-size:.82rem;margin:0;">Select a <strong>Document Type</strong> above to see the relevant official government portals and references.</p>
                        </div>

                        <!-- Official Government Reference Links -->
                        <div id="gov-ref-panel" style="display:none;">
                            <p style="font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                Links open official .gov.ph portals in a new tab
                            </p>
                            <div id="gov-ref-links" style="display:flex;flex-direction:column;gap:.55rem;"></div>
                        </div>
                    </div>
                </div>

            </div><!-- /left column -->

            <!-- ── RIGHT COLUMN ── -->
            <div style="display:flex;flex-direction:column;gap:1.5rem;">

                <!-- Instrument Summary card (sticky) -->
                <div style="position:sticky;top:2rem;display:flex;flex-direction:column;gap:1.5rem;">

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title"><i data-lucide="shield-check" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>Instrument Summary</h2>
                        </div>
                        <div class="card-body">
                            <div style="display:flex;flex-direction:column;gap:0;font-size:.88rem;">
                                <?php foreach ([
                                    ['sum-vehicle','Vehicle',''],
                                    ['sum-type','Document Type',''],
                                    ['sum-docnum','Cert. / Doc #',''],
                                    ['sum-issue','Issue Date',''],
                                    ['sum-expiry','Expiry Date',''],
                                    ['sum-cost','Renewal Cost',''],
                                ] as $i => [$id,$lbl,$_]): ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;padding:.55rem 0;<?php echo $i < 5 ? 'border-bottom:1px solid var(--border-color);' : ''; ?>">
                                    <span style="color:var(--text-muted);font-weight:500;"><?php echo $lbl; ?></span>
                                    <span id="<?php echo $id; ?>" style="font-weight:700;text-align:right;max-width:60%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">—</span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div style="margin-top:1.5rem;">
                                <button type="submit" class="btn btn-primary" id="submit-btn"
                                    style="width:100%;padding:1rem;font-size:.9rem;">
                                    <i data-lucide="upload-cloud" style="width:18px;height:18px;"></i> Archive Instrument
                                </button>
                                <a href="index.php" style="display:block;text-align:center;margin-top:1rem;font-size:.85rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Cancel</a>
                            </div>
                        </div>
                    </div>

                    <!-- Help card -->
                    <div class="card" style="background:var(--primary-light,#eff6ff);border:1px solid rgba(59,130,246,.2);">
                        <div class="card-body" style="padding:1.1rem 1.25rem;">
                            <p style="font-weight:700;font-size:.85rem;color:var(--primary);margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem;">
                                <i data-lucide="lightbulb" style="width:14px;height:14px;"></i> Quick Tips
                            </p>
                            <ul style="margin:0;padding-left:1.1rem;font-size:.8rem;color:var(--text-muted);line-height:1.7;">
                                <li>Select a <strong>Document Type</strong> to auto-fill the issuing authority.</li>
                                <li>Upload a scanned copy or manually input the details.</li>
                                <li>Previous records of the same type will be marked <em>Renewed</em>.</li>
                                <li>Use <strong>Print</strong> to generate official-format documents.</li>
                            </ul>
                        </div>
                    </div>

                </div><!-- /sticky -->

            </div><!-- /right column -->

        </div><!-- /grid -->
    </form>

</div>

<script>
(function () {
    'use strict';

    // ── Compliance type config from PHP ──────────────────────────────────────
    const typeConfig = <?php echo json_encode($complianceConfig, JSON_HEX_TAG); ?>;

    // ── Element refs ─────────────────────────────────────────────────────────
    const fileInput   = document.getElementById('document_file');
    const dropZone    = document.getElementById('drop-zone');
    const filePreview = document.getElementById('file-preview');
    const fileNameEl  = document.getElementById('file-name');
    const fileSizeEl  = document.getElementById('file-size');
    const clearBtn    = document.getElementById('clear-file');
    const vehicleSel  = document.getElementById('vehicle_id');
    const typeSel     = document.getElementById('compliance_type');
    const issueDateEl = document.getElementById('issue_date');
    const expiryEl    = document.getElementById('expiry_date');
    const renewalEl   = document.getElementById('renewal_cost');
    const docNumInput = document.getElementById('document_number');
    const issuingAuth = document.getElementById('issuing_authority');

    // ── Tab switcher ─────────────────────────────────────────────────────────
    window.switchTab = function(tab) {
        document.getElementById('panel-manual').style.display = tab === 'manual' ? '' : 'none';
        document.getElementById('panel-upload').style.display = tab === 'upload' ? '' : 'none';
        document.getElementById('tab-manual').classList.toggle('active', tab === 'manual');
        document.getElementById('tab-upload').classList.toggle('active', tab === 'upload');
        lucide.createIcons();
    };

    // ── Official government reference links per compliance type ─────────────
    const govLinks = {
        'lto_registration': [
            { label: 'Land Transportation Office (LTO)', url: 'https://lto.gov.ph', desc: 'Official Motor Vehicle Registration' },
            { label: 'LTO Online Registration Portal', url: 'https://www.lto.gov.ph/index.php/online-services.html', desc: 'Online services & MV renewal' },
        ],
        'insurance_comprehensive': [
            { label: 'Insurance Commission – Philippines', url: 'https://www.insurance.gov.ph', desc: 'Comprehensive insurance regulation & consumer info' },
        ],
        'insurance_tpl': [
            { label: 'Insurance Commission – Philippines', url: 'https://www.insurance.gov.ph', desc: 'Third-Party Liability (TPL) insurance info' },
            { label: 'LTO – Compulsory TPL Requirements', url: 'https://lto.gov.ph', desc: 'TPL required for LTO MV registration' },
        ],
        'emission_test': [
            { label: 'LTO – Private Emission Testing Centers', url: 'https://lto.gov.ph', desc: 'LTO-accredited PETC locator & requirements' },
            { label: 'EMB – DENR (Air Quality Standards)', url: 'https://www.emb.gov.ph', desc: 'Environmental Management Bureau emission standards' },
        ],
        'franchise_ltfrb': [
            { label: 'Land Transportation Franchising & Regulatory Board (LTFRB)', url: 'https://ltfrb.gov.ph', desc: 'Certificate of Public Convenience (CPC) issuance' },
            { label: 'LTFRB – Online Services', url: 'https://ltfrb.gov.ph', desc: 'Franchise applications & renewal portal' },
        ],
        'pnp_clearance': [
            { label: 'Philippine National Police (PNP)', url: 'https://pnp.gov.ph', desc: 'Official PNP vehicle clearance & NUC' },
        ],
        'mayors_permit': [
            { label: 'General Santos City Government', url: 'https://gensantos.gov.ph', desc: "Mayor's Permit & Business Licensing (BPLD)" },
            { label: 'GenSan e-BOSS Portal', url: 'https://gensantos.gov.ph', desc: 'Electronic Business One-Stop Shop' },
        ],
    };

    // ── Document type change ─────────────────────────────────────────────────
    typeSel.addEventListener('change', onTypeChange);

    function onTypeChange() {
        const key = typeSel.value;
        const cfg = typeConfig[key] || null;

        if (cfg) {
            // Auto-fill authority if empty
            if (!issuingAuth.value.trim()) {
                issuingAuth.value = cfg.authority;
                issuingAuth.style.animation = 'none';
                requestAnimationFrame(() => { issuingAuth.style.animation = 'slideDown .3s ease'; });
            }

            // Update doc number label & placeholder
            document.getElementById('doc-number-label').textContent = cfg.docLabel;
            docNumInput.placeholder = cfg.placeholder;

            // Populate official government reference links
            const refPanel = document.getElementById('gov-ref-panel');
            const refLinks = document.getElementById('gov-ref-links');
            const links = govLinks[key] || [];
            if (links.length) {
                refLinks.innerHTML = links.map(l => `
                    <a href="${l.url}" target="_blank" rel="noopener noreferrer"
                        style="display:flex;align-items:center;gap:.6rem;padding:.55rem .75rem;border-radius:var(--radius-sm);background:var(--bg-muted);text-decoration:none;color:var(--text-main);font-size:.8rem;transition:background .15s;border:1px solid var(--border-color);"
                        onmouseenter="this.style.background='rgba(59,130,246,.08)';this.style.borderColor='rgba(59,130,246,.3)'"
                        onmouseleave="this.style.background='var(--bg-muted)';this.style.borderColor='var(--border-color)'"
                    >
                        <span style="flex-shrink:0;width:28px;height:28px;background:rgba(59,130,246,.12);border-radius:6px;display:flex;align-items:center;justify-content:center;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;color:var(--primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${l.label}</div>
                            <div style="font-size:.72rem;color:var(--text-muted);margin-top:.1rem;">${l.desc}</div>
                        </div>
                        <span style="font-size:.68rem;color:var(--text-muted);flex-shrink:0;font-family:monospace;">.gov.ph</span>
                    </a>
                `).join('');
                refPanel.style.display = '';
                document.getElementById('gov-ref-empty').style.display = 'none';
                lucide.createIcons();
            } else {
                refPanel.style.display = 'none';
                document.getElementById('gov-ref-empty').style.display = '';
            }

            lucide.createIcons();
        } else {
            document.getElementById('gov-ref-panel').style.display = 'none';
            document.getElementById('gov-ref-empty').style.display = '';
        }

        updateSummary();
        saveToSession();
    }

    // ── File utilities ───────────────────────────────────────────────────────
    function getFileTypeClass(ext) {
        if (['pdf'].includes(ext)) return 'ft-pdf';
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) return 'ft-img';
        if (['doc','docx'].includes(ext)) return 'ft-doc';
        if (['xls','xlsx'].includes(ext)) return 'ft-xls';
        if (['csv'].includes(ext)) return 'ft-csv';
        return 'ft-generic';
    }

    function getFileIcon(ext) {
        if (ext === 'pdf') return 'file-text';
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) return 'image';
        if (['doc','docx'].includes(ext)) return 'file-text';
        if (['xls','xlsx','csv'].includes(ext)) return 'table-2';
        return 'file';
    }

    function showFile(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        const iconEl = document.getElementById('file-type-icon');
        iconEl.className = getFileTypeClass(ext);
        iconEl.innerHTML = `<i data-lucide="${getFileIcon(ext)}" style="width:18px;height:18px;"></i>`;

        fileNameEl.textContent = file.name;
        const kb = file.size / 1024;
        fileSizeEl.textContent = kb < 1024 ? kb.toFixed(1) + ' KB' : (kb / 1024).toFixed(2) + ' MB';

        filePreview.style.display = 'block';
        dropZone.classList.add('has-file');
        lucide.createIcons();
    }

    fileInput.addEventListener('change', function () {
        if (this.files.length) showFile(this.files[0]);
    });

    clearBtn.addEventListener('click', function () {
        fileInput.value = '';
        filePreview.style.display = 'none';
        dropZone.classList.remove('has-file');
    });

    // Drag & drop
    ['dragenter','dragover'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.add('drag-over'); }));
    ['dragleave','drop'].forEach(ev => dropZone.addEventListener(ev, () => dropZone.classList.remove('drag-over')));
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (!file) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        showFile(file);
    });

    // ── Live summary card ────────────────────────────────────────────────────
    function fmt(val) {
        if (!val) return '—';
        const d = new Date(val + 'T00:00:00');
        return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function updateSummary() {
        document.getElementById('sum-vehicle').textContent  = vehicleSel.selectedIndex > 0 ? vehicleSel.options[vehicleSel.selectedIndex].text : '—';
        document.getElementById('sum-type').textContent     = typeSel.selectedIndex > 0    ? typeSel.options[typeSel.selectedIndex].text    : '—';
        document.getElementById('sum-docnum').textContent   = docNumInput.value.trim() || '—';
        document.getElementById('sum-issue').textContent    = fmt(issueDateEl.value);
        document.getElementById('sum-expiry').textContent   = fmt(expiryEl.value);
        const cost = parseFloat(renewalEl.value);
        document.getElementById('sum-cost').textContent     = !isNaN(cost) && cost > 0 ? '₱' + cost.toLocaleString('en-PH',{minimumFractionDigits:2}) : '—';
    }

    [vehicleSel, typeSel, issueDateEl, expiryEl, renewalEl, docNumInput].forEach(el =>
        el.addEventListener('input', () => { updateSummary(); saveToSession(); })
    );

    issueDateEl.addEventListener('change', function () {
        expiryEl.min = this.value;
        updateSummary();
    });

    // ── sessionStorage auto-save ─────────────────────────────────────────────
    const SESSION_KEY = 'compliance_form_draft';

    function saveToSession() {
        try {
            sessionStorage.setItem(SESSION_KEY, JSON.stringify({
                vehicle_id:        vehicleSel.value,
                compliance_type:   typeSel.value,
                document_number:   docNumInput.value,
                issuing_authority: issuingAuth.value,
                issue_date:        issueDateEl.value,
                expiry_date:       expiryEl.value,
                renewal_cost:      renewalEl.value,
                notes:             document.getElementById('notes').value,
            }));
        } catch(e) { /* quota exceeded – ignore */ }
    }

    function restoreFromSession() {
        <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        try {
            const raw = sessionStorage.getItem(SESSION_KEY);
            if (!raw) return;
            const d = JSON.parse(raw);
            if (d.vehicle_id)        vehicleSel.value  = d.vehicle_id;
            if (d.compliance_type)   { typeSel.value   = d.compliance_type; onTypeChange(); }
            if (d.document_number)   docNumInput.value  = d.document_number;
            if (d.issuing_authority) issuingAuth.value  = d.issuing_authority;
            if (d.issue_date)        issueDateEl.value  = d.issue_date;
            if (d.expiry_date)       expiryEl.value     = d.expiry_date;
            if (d.renewal_cost)      renewalEl.value    = d.renewal_cost;
            if (d.notes)             document.getElementById('notes').value = d.notes;
        } catch(e) {}
        <?php endif; ?>
    }

    // ── Print document ───────────────────────────────────────────────────────
    window.printDocument = function() {
        const w = window.open('instrument-view.php', '_blank');
        if (w) w.addEventListener('load', () => w.print());
    };

    // ── Form validation ──────────────────────────────────────────────────────
    document.getElementById('compliance-form').addEventListener('submit', function (e) {
        const required = ['vehicle_id', 'compliance_type', 'document_number', 'issue_date', 'expiry_date'];
        let ok = true;
        required.forEach(id => {
            const el = document.getElementById(id);
            if (!el || !el.value.trim()) {
                if (el) el.style.outline = '2px solid var(--danger)';
                ok = false;
            } else {
                if (el) el.style.outline = '';
            }
        });
        if (!ok) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            // Clear draft on successful submit
            try { sessionStorage.removeItem(SESSION_KEY); } catch(e) {}
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-circle" style="width:16px;height:16px;animation:spin 1s linear infinite;"></i> Archiving…';
            lucide.createIcons();
        }
    });

    // ── Init ─────────────────────────────────────────────────────────────────
    restoreFromSession();
    updateSummary();
    if (typeSel.value) onTypeChange();

    // Restore upload tab if file was already chosen (browser back/forward)
    if (fileInput && fileInput.files && fileInput.files.length) {
        showFile(fileInput.files[0]);
    }

    lucide.createIcons();

    // Spin keyframe
    const style = document.createElement('style');
    style.textContent = '@keyframes spin{to{transform:rotate(360deg)}} @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}';
    document.head.appendChild(style);
})();
</script>

<?php require_once '../../includes/footer.php'; ?>
