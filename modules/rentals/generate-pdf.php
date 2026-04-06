<?php
/**
 * Rental Agreement PDF Generator (Printable View)
 * Path: modules/rentals/generate-pdf.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('rentals.view');

$db = Database::getInstance();
$rentalId = (int) ($_GET['id'] ?? 0);

if (!$rentalId) {
    die("Agreement ID missing.");
}

try {
    $rental = $db->fetchOne(
        "SELECT ra.*, v.plate_number, v.brand, v.model, v.year_model,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.customer_code,
                c.phone_primary, c.email, c.address, c.city, c.province
         FROM rental_agreements ra
         JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
         JOIN customers c ON ra.customer_id = c.customer_id
         WHERE ra.agreement_id = ?",
        [$rentalId]
    );

    if (!$rental) {
        die("Agreement not found.");
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Since we are in "no CSS" mode, we'll provide a very clean, printable HTML structure.
// This acts as the "PDF" view.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Agreement_
        <?= $rental['agreement_number'] ?>
    </title>
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm;
            }

            .no-print {
                display: none !important;
            }

            body {
                font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                font-size: 10.5pt;
                line-height: 1.4;
                margin: 0;
                padding: 0;
                color: #000;
            }

            /* Force everything to fit on one page */
            html, body, .page-container {
                height: 100%;
                max-height: 100vh;
                page-break-inside: avoid;
                page-break-after: avoid;
                page-break-before: avoid;
                overflow: hidden;
            }

            .section-title {
                background-color: transparent !important;
                border-bottom: 2px solid #000;
                padding: 2px 0;
                margin-top: 15px;
            }
        }

        @media screen {
            body {
                font-family: system-ui, -apple-system, sans-serif;
                padding: 40px;
                background: #f1f5f9;
                color: #1e293b;
            }
            .page-container {
                background: white;
                max-width: 800px;
                margin: 0 auto;
                padding: 40px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            }
            .section-title {
                background: #f8fafc;
                border-left: 4px solid #0f172a;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header h1 {
            margin: 0 0 5px 0;
            font-size: 18pt;
            letter-spacing: 1px;
        }

        .header p {
            margin: 2px 0;
            font-size: 9pt;
            color: #475569;
        }

        .header hr {
            margin: 15px 0;
            border: none;
            border-top: 1px solid #cbd5e1;
        }

        .header h2 {
            margin: 10px 0 5px 0;
            font-size: 14pt;
        }

        .section-title {
            padding: 6px 10px;
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
        }

        .data-row {
            display: flex;
            align-items: flex-end;
            padding: 4px 10px;
            font-size: 10.5pt;
        }

        .data-row .label {
            width: 160px;
            font-weight: 600;
            color: #334155;
        }

        .data-row .value {
            flex: 1;
            border-bottom: 1px dotted #94a3b8;
        }

        .signature-block {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-col {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 4px;
            height: 40px;
        }

        .signature-label {
            font-size: 9pt;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="no-print" style="max-width: 800px; margin: 0 auto 20px auto; display: flex; justify-content: flex-end; gap: 10px;">
        <a href="view.php?id=<?= $rentalId ?>" style="padding: 8px 16px; background: #e2e8f0; color: #0f172a; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.875rem;">Back to View</a>
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.875rem; box-shadow: 0 1px 3px rgba(37,99,235,0.3);">Print Agreement</button>
    </div>

    <div class="page-container">
        <div class="header">
            <h1>GENSAN CAR RENTAL SERVICES</h1>
            <p>Plaza Heneral Santos, Pendatun Avenue, General Santos City</p>
            <p>Phone: +63-965-129-6777 &nbsp;|&nbsp; Email: info@gensancarrental.com</p>
            <hr>
            <h2>RENTAL AGREEMENT</h2>
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 5px; padding: 0 10px; font-size: 10.5pt;">
            <div>
                <strong>AGREEMENT NO:</strong> <span style="font-family: monospace; font-size: 11pt;"><?= htmlspecialchars($rental['agreement_number']) ?></span>
            </div>
            <div>
                <strong>DATE:</strong> <?= date('F j, Y', strtotime($rental['created_at'])) ?>
            </div>
        </div>

        <div class="section-title">I. LESSEE (CUSTOMER) DETAILS</div>
        <div class="data-row">
            <div class="label">Full Name:</div>
            <div class="value"><?= htmlspecialchars($rental['customer_name']) ?></div>
        </div>
        <div class="data-row">
            <div class="label">Address:</div>
            <div class="value"><?= htmlspecialchars(trim($rental['address'] . ' ' . $rental['city'] . ' ' . $rental['province'])) ?></div>
        </div>
        <div class="data-row">
            <div class="label">Contact No:</div>
            <div class="value"><?= htmlspecialchars($rental['phone_primary']) ?></div>
        </div>
        <div class="data-row">
            <div class="label">Email:</div>
            <div class="value"><?= htmlspecialchars($rental['email']) ?></div>
        </div>

        <div class="section-title">II. VEHICLE INFORMATION</div>
        <div class="data-row">
            <div class="label">Unit/Model:</div>
            <div class="value"><?= htmlspecialchars($rental['brand'] . ' ' . $rental['model'] . ' (' . $rental['year_model'] . ')') ?></div>
        </div>
        <div class="data-row">
            <div class="label">Plate Number:</div>
            <div class="value" style="font-family: monospace; font-weight: bold; font-size: 11pt;"><?= htmlspecialchars($rental['plate_number']) ?></div>
        </div>

        <div class="section-title">III. RENTAL TERMS & BILLING</div>
        <div class="data-row">
            <div class="label">Pickup Date/Time:</div>
            <div class="value"><?= date('F j, Y g:i A', strtotime($rental['rental_start_date'])) ?></div>
        </div>
        <div class="data-row">
            <div class="label">Return Date/Time:</div>
            <div class="value"><?= date('F j, Y g:i A', strtotime($rental['rental_end_date'])) ?></div>
        </div>
        <div class="data-row">
            <div class="label">Daily Rate:</div>
            <div class="value">₱<?= number_format($rental['daily_rate'], 2) ?></div>
        </div>
        <div class="data-row">
            <div class="label">Security Deposit:</div>
            <div class="value">₱<?= number_format($rental['security_deposit'], 2) ?></div>
        </div>

        <?php
        $days = ceil((strtotime($rental['rental_end_date']) - strtotime($rental['rental_start_date'])) / 86400);
        if ($days < 1) $days = 1;
        ?>
        <div class="data-row" style="margin-top: 15px; font-weight: bold; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 4px;">
            <div class="label" style="width: auto; margin-right: 15px;">TOTAL RENTAL FEE (<?= $days ?> Days):</div>
            <div class="value" style="border: none; font-size: 13pt; text-align: right; margin-right: 10px; color: #000;">
                ₱<?= number_format($rental['total_amount'], 2) ?>
            </div>
        </div>

        <div style="margin-top: 25px; font-size: 9pt; text-align: justify; color: #475569; padding: 0 10px;">
            <p style="margin: 0;"><em>By signing below, the Lessee acknowledges receipt of the vehicle in good condition and agrees to all terms and conditions stipulated in the master rental agreement policy, assuming full liability for any damages, traffic violations, and late return penalties incurred during the rental period.</em></p>
        </div>

        <div class="signature-block">
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="signature-label">LESSEE SIGNATURE</div>
                <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">Printed Name & Signature</div>
            </div>
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="signature-label">AUTHORIZED REPRESENTATIVE</div>
                <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">Gensan Car Rental Services</div>
            </div>
        </div>
    </div>

    <!-- Auto print logic if desired, can re-enable: <script>window.onload = function() { window.print(); }</script> -->
</body>

</html>