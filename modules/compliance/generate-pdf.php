<?php
/**
 * Compliance Instrument — Printable PDF View
 * Path: modules/compliance/generate-pdf.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('compliance.view');

$db = Database::getInstance();
$recordId = (int)($_GET['id'] ?? 0);

if (!$recordId) {
    die('Record ID missing.');
}

try {
    $record = $db->fetchOne(
        "SELECT c.*, v.plate_number, v.brand, v.model, v.year_model,
                v.chassis_number, v.engine_number,
                u.first_name AS archiver_first, u.last_name AS archiver_last
         FROM compliance_records c
         JOIN vehicles v ON c.vehicle_id = v.vehicle_id
         LEFT JOIN users u ON c.created_by = u.user_id
         WHERE c.record_id = ?",
        [$recordId]
    );

    if (!$record) {
        die('Compliance record not found.');
    }
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
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
$typeLabel = $complianceTypes[$record['compliance_type']]
    ?? strtoupper(str_replace('_', ' ', $record['compliance_type']));

$hasExpiry  = !empty($record['expiry_date']) && $record['expiry_date'] !== '0000-00-00';
$diff       = $hasExpiry ? (int) ceil((strtotime($record['expiry_date']) - time()) / 86400) : null;
$isExpired  = $hasExpiry && $diff < 0;
$isWarning  = $hasExpiry && !$isExpired && $diff <= 30;
$isPending  = !$hasExpiry;
$statusText = $isPending ? 'PENDING'   : ($isExpired ? 'BREACHED' : ($isWarning ? 'EXPIRING SOON' : 'VALID'));
$statusHex  = $isPending ? '#64748b'   : ($isExpired ? '#dc2626'  : ($isWarning ? '#d97706'       : '#16a34a'));
$statusBg   = $isPending ? '#f8fafc'   : ($isExpired ? '#fef2f2'  : ($isWarning ? '#fffbeb'       : '#f0fdf4'));

$autoPrint = !empty($_GET['autoprint']);
$generatedAt = date('F j, Y  h:i A');
$base = rtrim(BASE_URL, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($typeLabel) ?> — <?= htmlspecialchars($record['plate_number']) ?></title>
    <style>
        /* ── Screen wrapper ── */
        @media screen {
            body {
                font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
                background: #f1f5f9;
                padding: 32px 16px;
                color: #1e293b;
                margin: 0;
            }
            .page {
                background: #fff;
                max-width: 760px;
                margin: 0 auto;
                padding: 48px 52px;
                box-shadow: 0 4px 24px rgba(0,0,0,.12);
                border-radius: 8px;
            }
            .toolbar {
                max-width: 760px;
                margin: 0 auto 20px auto;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
        }

        /* ── Print ── */
        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; padding: 0; margin: 0; }
            .page {
                max-width: 100%;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            @page { size: A4 portrait; margin: 18mm 18mm 18mm 18mm; }
        }

        /* ── Shared ── */
        * { box-sizing: border-box; }

        body {
            font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
            font-size: 10.5pt;
            line-height: 1.5;
            color: #1e293b;
        }

        /* Header */
        .org-header { text-align: center; margin-bottom: 28px; }
        .org-header h1 {
            margin: 0 0 2px 0;
            font-size: 17pt;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .org-header p { margin: 2px 0; font-size: 8.5pt; color: #64748b; }
        .org-header hr { margin: 14px 0; border: none; border-top: 2px solid #1e293b; }
        .doc-title {
            font-size: 13pt;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }
        .doc-subtitle { font-size: 9pt; color: #64748b; letter-spacing: 0.03em; }

        /* Status banner */
        .status-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: <?= $statusBg ?>;
            border: 1.5px solid <?= $statusHex ?>;
            border-radius: 6px;
            padding: 10px 18px;
            margin: 22px 0;
        }
        .status-banner .label { font-size: 8pt; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
        .status-banner .value { font-size: 11pt; font-weight: 900; color: <?= $statusHex ?>; text-transform: uppercase; }
        .status-badge {
            padding: 4px 14px;
            border-radius: 99px;
            font-size: 8.5pt;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: <?= $statusHex ?>;
            color: #fff;
        }

        /* Section heading */
        .section {
            font-size: 8pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin: 22px 0 12px 0;
        }

        /* Data grid */
        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 32px; }
        .data-field { }
        .data-field .lbl { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin-bottom: 2px; }
        .data-field .val { font-size: 10.5pt; font-weight: 700; color: #1e293b; }
        .data-field .val.mono { font-family: 'Courier New', monospace; font-size: 10pt; }
        .full { grid-column: 1 / -1; }

        /* Remark box */
        .remark-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 10px 14px;
            font-size: 9.5pt;
            color: #475569;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        /* Signature */
        .sig-block { display: flex; justify-content: space-between; margin-top: 48px; }
        .sig-col { width: 44%; text-align: center; }
        .sig-line { border-bottom: 1px solid #1e293b; height: 44px; margin-bottom: 5px; }
        .sig-name { font-size: 9pt; font-weight: 800; text-transform: uppercase; }
        .sig-role { font-size: 7.5pt; color: #64748b; margin-top: 2px; }

        /* Footer */
        .doc-footer {
            margin-top: 38px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt;
            color: #94a3b8;
        }

        /* Toolbar buttons (screen only) */
        .btn-toolbar {
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 0.8375rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back  { background: #e2e8f0; color: #0f172a; }
        .btn-print { background: #2563eb; color: #fff; box-shadow: 0 1px 3px rgba(37,99,235,.3); }
    </style>
</head>
<body>

<!-- Toolbar (hidden on print) -->
<div class="toolbar">
    <a href="#" onclick="if(window.history.length > 2) window.history.back(); else if(window.opener) window.close(); else window.location.href='<?= BASE_URL ?>modules/compliance/index.php'; return false;" class="btn-toolbar btn-back">← Back</a>
    <button onclick="window.print()" class="btn-toolbar btn-print">🖨️ Print / Save as PDF</button>
</div>

<div class="page">

    <!-- Organisation header -->
    <div class="org-header">
        <h1>Gensan Car Rental Services</h1>
        <p>Plaza Heneral Santos, Pendatun Avenue, General Santos City, Philippines</p>
        <p>Phone: +63-965-129-6777 &nbsp;|&nbsp; Email: info@gensancarrental.com</p>
        <hr>
        <div class="doc-title">Regulatory Compliance Certificate</div>
        <div class="doc-subtitle"><?= htmlspecialchars($typeLabel) ?></div>
    </div>

    <!-- Status banner -->
    <div class="status-banner">
        <div>
            <div class="label">Compliance Status</div>
            <div class="value"><?= $statusText ?></div>
            <div style="font-size:8pt; color:#64748b; margin-top:3px;">
                <?= $isPending
                    ? 'No expiry date set'
                    : ($isExpired ? abs($diff) . ' day(s) past expiry' : $diff . ' day(s) until expiry') ?>
            </div>
        </div>
        <span class="status-badge"><?= $statusText ?></span>
    </div>

    <!-- Vehicle info -->
    <div class="section">I. VEHICLE INFORMATION</div>
    <div class="data-grid">
        <div class="data-field">
            <div class="lbl">Plate Number</div>
            <div class="val mono"><?= htmlspecialchars($record['plate_number']) ?></div>
        </div>
        <div class="data-field">
            <div class="lbl">Unit / Model</div>
            <div class="val"><?= htmlspecialchars($record['brand'] . ' ' . $record['model'] . ' (' . $record['year_model'] . ')') ?></div>
        </div>
        <?php if (!empty($record['chassis_number'])): ?>
        <div class="data-field">
            <div class="lbl">Chassis No.</div>
            <div class="val mono"><?= htmlspecialchars($record['chassis_number']) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($record['engine_number'])): ?>
        <div class="data-field">
            <div class="lbl">Engine No.</div>
            <div class="val mono"><?= htmlspecialchars($record['engine_number']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Document details -->
    <div class="section">II. INSTRUMENT DETAILS</div>
    <div class="data-grid">
        <div class="data-field">
            <div class="lbl">Compliance Type</div>
            <div class="val"><?= htmlspecialchars($typeLabel) ?></div>
        </div>
        <div class="data-field">
            <div class="lbl">Document / Reference No.</div>
            <div class="val mono"><?= htmlspecialchars($record['document_number'] ?? 'N/A') ?></div>
        </div>
        <div class="data-field">
            <div class="lbl">Issuing Authority</div>
            <div class="val"><?= htmlspecialchars($record['issuing_authority'] ?? 'N/A') ?></div>
        </div>
        <div class="data-field">
            <div class="lbl">Renewal Cost</div>
            <div class="val"><?= $record['renewal_cost'] ? '₱ ' . number_format($record['renewal_cost'], 2) : 'N/A' ?></div>
        </div>
        <div class="data-field">
            <div class="lbl">Issue Date</div>
            <div class="val"><?= $record['issue_date'] ? date('F j, Y', strtotime($record['issue_date'])) : 'N/A' ?></div>
        </div>
        <div class="data-field">
            <div class="lbl">Expiry Date</div>
            <div class="val" style="color:<?= $statusHex ?>;">
                <?= $hasExpiry ? date('F j, Y', strtotime($record['expiry_date'])) : '<em>Not yet set</em>' ?>
            </div>
        </div>
        <div class="data-field">
            <div class="lbl">Record Status</div>
            <div class="val"><?= htmlspecialchars(strtoupper($record['status'] ?? 'active')) ?></div>
        </div>
        <div class="data-field">
            <div class="lbl">Archived By</div>
            <div class="val">
                <?= htmlspecialchars(trim(($record['archiver_first'] ?? 'System') . ' ' . ($record['archiver_last'] ?? ''))) ?>
            </div>
        </div>
        <div class="data-field full">
            <div class="lbl">Date Archived</div>
            <div class="val"><?= date('F j, Y  h:i A', strtotime($record['created_at'])) ?></div>
        </div>
    </div>

    <!-- Remarks -->
    <?php if (!empty($record['notes'])): ?>
    <div class="section">III. REMARKS / NOTES</div>
    <div class="remark-box"><?= htmlspecialchars($record['notes']) ?></div>
    <?php endif; ?>

    <!-- Signature block -->
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

    <!-- Footer -->
    <div class="doc-footer">
        <span>Record ID: CR-<?= str_pad($recordId, 5, '0', STR_PAD_LEFT) ?> &nbsp;|&nbsp; Vehicle: <?= htmlspecialchars($record['plate_number']) ?></span>
        <span>Generated: <?= $generatedAt ?></span>
    </div>

</div>

<?php if ($autoPrint): ?>
<script>window.onload = function(){ window.print(); };</script>
<?php endif; ?>

</body>
</html>
