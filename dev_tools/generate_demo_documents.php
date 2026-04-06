<?php
/**
 * Generate Demo Compliance Documents
 * Creates PDF-style HTML documents for each compliance record and updates the DB.
 * 
 * Run via browser: http://localhost/IATPS/gensan-car-rental-system/dev_tools/generate_demo_documents.php
 */

require_once __DIR__ . '/../config/config.php';

$db = Database::getInstance();

// Get all compliance records that need demo documents
$records = $db->fetchAll(
    "SELECT c.*, v.plate_number, v.brand, v.model, v.year_model,
            v.chassis_number, v.engine_number, v.color, v.fuel_type, v.transmission,
            v.seating_capacity
     FROM compliance_records c
     JOIN vehicles v ON c.vehicle_id = v.vehicle_id
     ORDER BY c.record_id ASC"
);

if (empty($records)) {
    die('<h2>No compliance records found.</h2>');
}

$complianceTypes = [
    'lto_registration'        => 'LTO Registration',
    'insurance_comprehensive' => 'Comprehensive Insurance',
    'insurance_tpl'           => 'Third-Party Liability (TPL) Insurance',
    'emission_test'           => 'Emission Test Certificate',
    'franchise_ltfrb'         => 'LTFRB Franchise Permit',
    'pnp_clearance'           => 'PNP Clearance',
    'mayors_permit'           => "Mayor's Business Permit",
];

$issuingAuthorities = [
    'lto_registration'        => 'Land Transportation Office - General Santos District',
    'insurance_comprehensive' => 'Pioneer Insurance Corporation',
    'insurance_tpl'           => 'Pioneer Insurance Corporation',
    'emission_test'           => 'DENR - Accredited Private Emission Testing Center, GenSan',
    'franchise_ltfrb'         => 'Land Transportation Franchising and Regulatory Board',
    'pnp_clearance'           => 'Philippine National Police - GenSan City',
    'mayors_permit'           => "City Mayor's Office - General Santos City",
];

// Policy number prefixes for insurance
$policyPrefixes = [
    'insurance_comprehensive' => 'PIC-COMP',
    'insurance_tpl'           => 'PIC-TPL',
];

$uploadDir = BASE_PATH . 'assets/images/uploads/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$generated = 0;
$skipped   = 0;
$errors    = [];

echo '<!DOCTYPE html><html><head><title>Demo Document Generator</title>';
echo '<style>body{font-family:-apple-system,sans-serif;padding:30px;background:#f1f5f9;color:#1e293b;}';
echo '.card{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);margin-bottom:16px;}';
echo '.success{color:#16a34a;}.error{color:#dc2626;}.skip{color:#d97706;}.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;}';
echo '.b-s{background:#dcfce7;color:#16a34a;}.b-e{background:#fef2f2;color:#dc2626;}.b-k{background:#fef3c7;color:#92400e;}';
echo 'table{width:100%;border-collapse:collapse;}th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #e2e8f0;font-size:13px;}th{background:#f8fafc;font-weight:700;text-transform:uppercase;font-size:11px;color:#64748b;}</style></head><body>';
echo '<h1>🏗️ Demo Document Generator</h1>';
echo '<p style="color:#64748b;margin-bottom:24px;">Generating compliance certificate documents for all fleet vehicles...</p>';

echo '<table><thead><tr><th>#</th><th>Vehicle</th><th>Type</th><th>Ref #</th><th>Status</th><th>File</th></tr></thead><tbody>';

foreach ($records as $rec) {
    $typeLabel     = $complianceTypes[$rec['compliance_type']] ?? strtoupper(str_replace('_', ' ', $rec['compliance_type']));
    $authority     = $rec['issuing_authority'] ?: ($issuingAuthorities[$rec['compliance_type']] ?? 'Government Agency');
    $docNumber     = $rec['document_number'] ?? 'N/A';
    $plateNumber   = $rec['plate_number'];
    $vehicleName   = $rec['brand'] . ' ' . $rec['model'];
    $yearModel     = $rec['year_model'] ?? '';
    $chassisNo     = $rec['chassis_number'] ?? 'N/A';
    $engineNo      = $rec['engine_number'] ?? 'N/A';
    $color         = $rec['color'] ?? 'N/A';
    $fuelType      = ucfirst($rec['fuel_type'] ?? 'N/A');
    $transmission  = ucfirst($rec['transmission'] ?? 'N/A');
    $seating       = $rec['seating_capacity'] ?? 'N/A';
    $issueDate     = $rec['issue_date'] ? date('F j, Y', strtotime($rec['issue_date'])) : 'N/A';
    $expiryDate    = date('F j, Y', strtotime($rec['expiry_date']));
    $renewalCost   = $rec['renewal_cost'] ? '₱ ' . number_format($rec['renewal_cost'], 2) : 'N/A';
    $notes         = $rec['notes'] ?? '';
    $recordId      = $rec['record_id'];
    $vehicleId     = $rec['vehicle_id'];
    $compType      = $rec['compliance_type'];

    $diff       = ceil((strtotime($rec['expiry_date']) - time()) / 86400);
    $isExpired  = $diff < 0;
    $isWarning  = !$isExpired && $diff <= 30;
    $statusText = $isExpired ? 'EXPIRED / BREACHED' : ($isWarning ? 'EXPIRING SOON' : 'VALID');
    $statusHex  = $isExpired ? '#dc2626' : ($isWarning ? '#d97706' : '#16a34a');
    $statusBg   = $isExpired ? '#fef2f2' : ($isWarning ? '#fffbeb' : '#f0fdf4');

    // Generate a unique filename
    $filename = "compliance_{$vehicleId}_{$compType}_" . date('Ymd_His') . "_{$recordId}.html";
    $filepath = $uploadDir . $filename;
    $dbPath   = 'assets/images/uploads/documents/' . $filename;

    // Generate document HTML
    $docHtml = generateDocumentHtml(
        $typeLabel, $authority, $docNumber, $plateNumber, $vehicleName,
        $yearModel, $chassisNo, $engineNo, $color, $fuelType, $transmission,
        $seating, $issueDate, $expiryDate, $renewalCost, $notes,
        $statusText, $statusHex, $statusBg, $recordId, $vehicleId, $compType,
        $diff, $isExpired
    );

    // Write file
    if (file_put_contents($filepath, $docHtml) !== false) {
        // Update database
        try {
            $db->execute(
                "UPDATE compliance_records SET document_file_path = ?, updated_at = NOW() WHERE record_id = ?",
                [$dbPath, $recordId]
            );
            $generated++;
            $badge = '<span class="badge b-s">✓ Generated</span>';
        } catch (Exception $e) {
            $errors[] = "DB update failed for record {$recordId}: " . $e->getMessage();
            $badge = '<span class="badge b-e">✗ DB Error</span>';
        }
    } else {
        $errors[] = "File write failed: {$filepath}";
        $badge = '<span class="badge b-e">✗ File Error</span>';
    }

    echo "<tr>";
    echo "<td>{$recordId}</td>";
    echo "<td><strong>{$vehicleName}</strong><br><span style='font-size:11px;color:#64748b;'>{$plateNumber}</span></td>";
    echo "<td>{$typeLabel}</td>";
    echo "<td style='font-family:monospace;font-size:12px;'>{$docNumber}</td>";
    echo "<td>{$badge}</td>";
    echo "<td style='font-size:11px;font-family:monospace;color:#64748b;'>{$filename}</td>";
    echo "</tr>";

    // Small delay to ensure unique filenames
    usleep(50000);
}

echo '</tbody></table>';

echo '<div class="card" style="margin-top:20px;">';
echo "<h3>Summary</h3>";
echo "<p class='success'><strong>{$generated}</strong> documents generated successfully.</p>";
if (!empty($errors)) {
    echo "<p class='error'><strong>" . count($errors) . "</strong> errors:</p>";
    echo "<ul>";
    foreach ($errors as $err) {
        echo "<li class='error'>{$err}</li>";
    }
    echo "</ul>";
}
echo '<p style="margin-top:12px;"><a href="' . BASE_URL . 'modules/compliance/index.php" style="color:#2563eb;font-weight:700;">← Return to Compliance Registry</a></p>';
echo '</div>';
echo '</body></html>';

// ────────────────────── Document HTML Generator ──────────────────────

function generateDocumentHtml(
    $typeLabel, $authority, $docNumber, $plateNumber, $vehicleName,
    $yearModel, $chassisNo, $engineNo, $color, $fuelType, $transmission,
    $seating, $issueDate, $expiryDate, $renewalCost, $notes,
    $statusText, $statusHex, $statusBg, $recordId, $vehicleId, $compType,
    $diff, $isExpired
) {
    $generatedAt = date('F j, Y h:i A');
    $validityText = $isExpired ? abs($diff) . ' day(s) past expiry' : $diff . ' day(s) until expiry';
    $year = date('Y');

    // Generate a control number
    $controlNo = 'GCR-CC-' . str_pad($recordId, 5, '0', STR_PAD_LEFT) . '-' . date('Y');

    // Document-specific content
    $docSpecificSection = '';
    switch ($compType) {
        case 'lto_registration':
            $docSpecificSection = <<<HTML
            <div class="section">III. REGISTRATION PARTICULARS</div>
            <div class="data-grid">
                <div class="data-field">
                    <div class="lbl">Registration Type</div>
                    <div class="val">Private — For Hire</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Classification</div>
                    <div class="val">Motor Vehicle</div>
                </div>
                <div class="data-field">
                    <div class="lbl">MV File Number</div>
                    <div class="val mono">MVF-{$vehicleId}-{$year}</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Encumbrances</div>
                    <div class="val">None</div>
                </div>
            </div>
HTML;
            break;

        case 'insurance_comprehensive':
            $policyNo = 'PIC-COMP-' . strtoupper(substr(md5($vehicleId . $recordId), 0, 8));
            $docSpecificSection = <<<HTML
            <div class="section">III. COVERAGE DETAILS</div>
            <div class="data-grid">
                <div class="data-field">
                    <div class="lbl">Policy Number</div>
                    <div class="val mono">{$policyNo}</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Coverage Type</div>
                    <div class="val">Comprehensive (Own Damage + Theft)</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Sum Insured</div>
                    <div class="val">₱ 1,500,000.00</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Deductible</div>
                    <div class="val">₱ 10,000.00</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Acts of Nature</div>
                    <div class="val">Included</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Authorized Repair</div>
                    <div class="val">Casa / Accredited Shops</div>
                </div>
            </div>
HTML;
            break;

        case 'insurance_tpl':
            $policyNo = 'PIC-TPL-' . strtoupper(substr(md5($vehicleId . $recordId), 0, 8));
            $docSpecificSection = <<<HTML
            <div class="section">III. LIABILITY COVERAGE</div>
            <div class="data-grid">
                <div class="data-field">
                    <div class="lbl">Policy Number</div>
                    <div class="val mono">{$policyNo}</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Coverage Type</div>
                    <div class="val">Third-Party Liability (Compulsory)</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Bodily Injury Limit</div>
                    <div class="val">₱ 100,000.00 per person</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Property Damage Limit</div>
                    <div class="val">₱ 100,000.00 per accident</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Death Indemnity</div>
                    <div class="val">₱ 100,000.00</div>
                </div>
                <div class="data-field">
                    <div class="lbl">No-Fault Benefit</div>
                    <div class="val">₱ 15,000.00 per passenger</div>
                </div>
            </div>
HTML;
            break;

        case 'emission_test':
            $docSpecificSection = <<<HTML
            <div class="section">III. TEST RESULTS</div>
            <div class="data-grid">
                <div class="data-field">
                    <div class="lbl">Test Type</div>
                    <div class="val">{$fuelType} Engine Emission</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Result</div>
                    <div class="val" style="color:#16a34a;font-weight:900;">PASSED ✓</div>
                </div>
                <div class="data-field">
                    <div class="lbl">CO Level</div>
                    <div class="val mono">0.42% (Limit: 4.5%)</div>
                </div>
                <div class="data-field">
                    <div class="lbl">HC Level</div>
                    <div class="val mono">85 ppm (Limit: 600 ppm)</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Opacity</div>
                    <div class="val mono">1.2 HSU (Limit: 2.5 HSU)</div>
                </div>
                <div class="data-field">
                    <div class="lbl">Testing Center</div>
                    <div class="val">DENR Accredited Testing Center</div>
                </div>
            </div>
HTML;
            break;

        default:
            $docSpecificSection = <<<HTML
            <div class="section">III. ADDITIONAL DETAILS</div>
            <div class="data-grid">
                <div class="data-field full">
                    <div class="lbl">Scope</div>
                    <div class="val">Full regulatory compliance for commercial vehicle operations in Region XII — SOCCSKSARGEN</div>
                </div>
            </div>
HTML;
            break;
    }

    $remarksSection = '';
    if (!empty($notes)) {
        $notesEscaped = htmlspecialchars($notes);
        $remarksSection = <<<HTML
        <div class="section">IV. REMARKS / NOTES</div>
        <div class="remark-box">{$notesEscaped}</div>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$typeLabel} — {$plateNumber} | Gensan Car Rental</title>
    <style>
        @media screen {
            body { font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif; background: #f1f5f9; padding: 32px 16px; color: #1e293b; margin: 0; }
            .page { background: #fff; max-width: 760px; margin: 0 auto; padding: 48px 52px; box-shadow: 0 4px 24px rgba(0,0,0,.12); border-radius: 8px; }
            .toolbar { max-width: 760px; margin: 0 auto 20px auto; display: flex; justify-content: flex-end; gap: 10px; }
        }
        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; padding: 0; margin: 0; }
            .page { max-width: 100%; padding: 0; box-shadow: none; border-radius: 0; }
            @page { size: A4 portrait; margin: 18mm; }
        }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1e293b; }

        .org-header { text-align: center; margin-bottom: 28px; }
        .org-header h1 { margin: 0 0 2px 0; font-size: 17pt; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
        .org-header p { margin: 2px 0; font-size: 8.5pt; color: #64748b; }
        .org-header hr { margin: 14px 0; border: none; border-top: 2px solid #1e293b; }
        .doc-title { font-size: 13pt; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; margin: 0 0 4px 0; }
        .doc-subtitle { font-size: 9pt; color: #64748b; letter-spacing: 0.03em; }

        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 72pt; font-weight: 900; opacity: 0.03; color: #1e293b; text-transform: uppercase; pointer-events: none; white-space: nowrap; z-index: 0; }

        .status-banner { display: flex; align-items: center; justify-content: space-between; background: {$statusBg}; border: 1.5px solid {$statusHex}; border-radius: 6px; padding: 10px 18px; margin: 22px 0; }
        .status-banner .label { font-size: 8pt; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
        .status-banner .value { font-size: 11pt; font-weight: 900; color: {$statusHex}; text-transform: uppercase; }
        .status-badge { padding: 4px 14px; border-radius: 99px; font-size: 8.5pt; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; background: {$statusHex}; color: #fff; }

        .section { font-size: 8pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin: 22px 0 12px 0; }
        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 32px; }
        .data-field .lbl { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin-bottom: 2px; }
        .data-field .val { font-size: 10.5pt; font-weight: 700; color: #1e293b; }
        .data-field .val.mono { font-family: 'Courier New', monospace; font-size: 10pt; }
        .full { grid-column: 1 / -1; }
        .remark-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 10px 14px; font-size: 9.5pt; color: #475569; white-space: pre-wrap; line-height: 1.5; }

        .sig-block { display: flex; justify-content: space-between; margin-top: 48px; }
        .sig-col { width: 44%; text-align: center; }
        .sig-line { border-bottom: 1px solid #1e293b; height: 44px; margin-bottom: 5px; }
        .sig-name { font-size: 9pt; font-weight: 800; text-transform: uppercase; }
        .sig-role { font-size: 7.5pt; color: #64748b; margin-top: 2px; }

        .doc-footer { margin-top: 38px; padding-top: 12px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 7.5pt; color: #94a3b8; }

        .btn-toolbar { padding: 8px 18px; border-radius: 6px; font-size: 0.8375rem; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-back { background: #e2e8f0; color: #0f172a; }
        .btn-print { background: #2563eb; color: #fff; box-shadow: 0 1px 3px rgba(37,99,235,.3); }

        .seal { display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border: 3px solid #1e293b; border-radius: 50%; font-size: 7pt; font-weight: 900; text-transform: uppercase; text-align: center; color: #1e293b; opacity: 0.15; position: absolute; right: 52px; bottom: 120px; }
    </style>
</head>
<body>

<div class="toolbar">
    <button onclick="if(window.history.length > 1) { window.history.back(); } else if(window.opener){ window.close(); } else { window.location.href='/IATPS/gensan-car-rental-system/modules/compliance/index.php'; }" class="btn-toolbar btn-back">← Back</button>
    <button onclick="window.print()" class="btn-toolbar btn-print">🖨️ Print / Save as PDF</button>
</div>

<div class="page" style="position:relative;">
    <div class="watermark">GENSAN CAR RENTAL</div>

    <div class="org-header">
        <h1>Gensan Car Rental Services</h1>
        <p>Plaza Heneral Santos, Pendatun Avenue, General Santos City, Philippines 9500</p>
        <p>Phone: +63-965-129-6777 &nbsp;|&nbsp; Email: info@gensancarrental.com</p>
        <hr>
        <div class="doc-title">Regulatory Compliance Certificate</div>
        <div class="doc-subtitle">{$typeLabel}</div>
    </div>

    <div class="status-banner">
        <div>
            <div class="label">Compliance Status</div>
            <div class="value">{$statusText}</div>
            <div style="font-size:8pt; color:#64748b; margin-top:3px;">{$validityText}</div>
        </div>
        <span class="status-badge">{$statusText}</span>
    </div>

    <div class="section">I. VEHICLE INFORMATION</div>
    <div class="data-grid">
        <div class="data-field">
            <div class="lbl">Plate Number</div>
            <div class="val mono">{$plateNumber}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Unit / Model</div>
            <div class="val">{$vehicleName} ({$yearModel})</div>
        </div>
        <div class="data-field">
            <div class="lbl">Chassis No.</div>
            <div class="val mono">{$chassisNo}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Engine No.</div>
            <div class="val mono">{$engineNo}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Color</div>
            <div class="val">{$color}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Fuel / Transmission</div>
            <div class="val">{$fuelType} / {$transmission}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Seating Capacity</div>
            <div class="val">{$seating} passenger(s)</div>
        </div>
        <div class="data-field">
            <div class="lbl">Fleet ID</div>
            <div class="val mono">{$vehicleId}</div>
        </div>
    </div>

    <div class="section">II. INSTRUMENT DETAILS</div>
    <div class="data-grid">
        <div class="data-field">
            <div class="lbl">Compliance Type</div>
            <div class="val">{$typeLabel}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Document / Reference No.</div>
            <div class="val mono">{$docNumber}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Issuing Authority</div>
            <div class="val">{$authority}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Renewal Cost</div>
            <div class="val">{$renewalCost}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Issue Date</div>
            <div class="val">{$issueDate}</div>
        </div>
        <div class="data-field">
            <div class="lbl">Expiry Date</div>
            <div class="val" style="color:{$statusHex};">{$expiryDate}</div>
        </div>
    </div>

    {$docSpecificSection}

    {$remarksSection}

    <div class="sig-block">
        <div class="sig-col">
            <div class="sig-line"></div>
            <div class="sig-name">Fleet / Compliance Officer</div>
            <div class="sig-role">Verified &amp; Certified By</div>
        </div>
        <div class="sig-col">
            <div class="sig-line"></div>
            <div class="sig-name">Authorized Representative</div>
            <div class="sig-role">Gensan Car Rental Services</div>
        </div>
    </div>

    <div class="seal">GCR<br>OFFICIAL<br>SEAL</div>

    <div class="doc-footer">
        <span>Control No: {$controlNo} &nbsp;|&nbsp; Vehicle: {$plateNumber} &nbsp;|&nbsp; Fleet ID: {$vehicleId}</span>
        <span>Generated: {$generatedAt}</span>
    </div>
</div>

</body>
</html>
HTML;
}
