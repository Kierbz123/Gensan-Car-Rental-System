<?php
/**
 * Rental Agreement Create (Print/Sign)
 * Path: modules/customers/rental-agreement-create.php
 * Generates a printable agreement PDF or HTML
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('rentals.view');
$db = Database::getInstance();
$rentalId = (int) ($_GET['id'] ?? 0);
if (!$rentalId) {
    redirect('modules/rentals/', 'Rental ID missing', 'error');
}

$rental = $db->fetchOne(
    "SELECT ra.*, v.plate_number, v.brand, v.model, v.year_model, v.color,
            c.first_name, c.last_name, c.phone_primary, c.email, c.address, c.id_type, c.id_number,
            c.emergency_name, c.emergency_phone,
            CONCAT(u.first_name,' ',u.last_name) AS processed_by
     FROM rental_agreements ra
     JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
     JOIN customers c ON ra.customer_id = c.customer_id
     LEFT JOIN users u ON ra.processed_by = u.user_id
     WHERE ra.agreement_id = ?",
    [$rentalId]
);
if (!$rental) {
    redirect('modules/rentals/', 'Agreement not found', 'error');
}

$days = max(1, ceil((strtotime($rental['rental_end_date']) - strtotime($rental['rental_start_date'])) / 86400));
$pageTitle = 'Rental Agreement — ' . $rental['agreement_number'];
// No native header/footer for print view
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            background: #fff;
            font-size: 12px;
            line-height: 1.5;
        }

        .page {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 900;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .header p {
            color: #64748b;
            font-size: 11px;
        }

        .badge {
            display: inline-block;
            background: #ede9fe;
            color: #6366f1;
            font-weight: 900;
            font-size: 9px;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .section {
            margin-bottom: 24px;
        }

        .section h2 {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #6366f1;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .grid3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .field label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
            display: block;
            margin-bottom: 2px;
        }

        .field p {
            font-weight: 700;
            color: #1e293b;
        }

        .terms {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            font-size: 10px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .terms ol {
            padding-left: 16px;
        }

        .terms li {
            margin-bottom: 4px;
        }

        .sig-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
        }

        .sig-box {
            border-top: 2px solid #1e293b;
            padding-top: 8px;
            text-align: center;
        }

        .sig-box p {
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
        }

        .amount-box {
            background: #1e293b;
            color: #fff;
            border-radius: 12px;
            padding: 16px;
            text-align: right;
        }

        .amount-box .label {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 700;
        }

        .amount-box .value {
            font-size: 22px;
            font-weight: 900;
            color: #a5b4fc;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm;
            }

            .no-print {
                display: none !important;
            }

            body {
                background: #fff;
            }

            html, body, .page {
                height: 100%;
                max-height: 100vh;
                page-break-inside: avoid;
                page-break-after: avoid;
                page-break-before: avoid;
                overflow: hidden;
            }

            .page {
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="no-print" style="margin-bottom:20px; display:flex; gap:10px;">
            <button onclick="window.print()"
                style="background:#6366f1;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:900;font-size:11px;cursor:pointer;">🖨
                Print Agreement</button>
            <a href="../rentals/view.php?id=<?= $rentalId ?>"
                style="background:#f1f5f9;color:#64748b;border:none;padding:10px 20px;border-radius:8px;font-weight:700;font-size:11px;text-decoration:none;">←
                Back</a>
        </div>

        <div class="header">
            <h1>VEHICLE RENTAL AGREEMENT</h1>
            <p>Gensan Car Rental System · General Santos City, South Cotabato</p>
            <div style="margin-top:10px;"><span
                    class="badge"><?= htmlspecialchars($rental['agreement_number']) ?></span></div>
        </div>

        <div class="section">
            <h2>Lessee (Client) Information</h2>
            <div class="grid2">
                <div class="field"><label>Full Name</label>
                    <p><?= htmlspecialchars($rental['first_name'] . ' ' . $rental['last_name']) ?></p>
                </div>
                <div class="field"><label>Phone</label>
                    <p><?= htmlspecialchars($rental['phone_primary']) ?></p>
                </div>
                <div class="field"><label>Email</label>
                    <p><?= htmlspecialchars($rental['email'] ?? '—') ?></p>
                </div>
                <div class="field"><label>ID (<?= str_replace('_', ' ', ucfirst($rental['id_type'] ?? '')) ?>)</label>
                    <p><?= htmlspecialchars($rental['id_number'] ?? '—') ?></p>
                </div>
                <div class="field" style="grid-column: 1/-1"><label>Address</label>
                    <p><?= htmlspecialchars($rental['address'] ?? '—') ?></p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Vehicle Information</h2>
            <div class="grid3">
                <div class="field"><label>Make & Model</label>
                    <p><?= htmlspecialchars($rental['brand'] . ' ' . $rental['model']) ?></p>
                </div>
                <div class="field"><label>Year</label>
                    <p><?= htmlspecialchars($rental['year_model']) ?></p>
                </div>
                <div class="field"><label>Plate Number</label>
                    <p><?= htmlspecialchars($rental['plate_number']) ?></p>
                </div>
                <div class="field"><label>Color</label>
                    <p><?= htmlspecialchars($rental['color'] ?? '—') ?></p>
                </div>
                <div class="field"><label>Pickup Location</label>
                    <p><?= str_replace('_', ' ', ucfirst($rental['pickup_location'] ?? 'main office')) ?></p>
                </div>
                <div class="field"><label>Return Location</label>
                    <p><?= str_replace('_', ' ', ucfirst($rental['return_location'] ?? 'main office')) ?></p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Rental Terms & Financial Summary</h2>
            <div class="grid3">
                <div class="field"><label>Start Date</label>
                    <p><?= date('M d, Y', strtotime($rental['rental_start_date'])) ?></p>
                </div>
                <div class="field"><label>End Date</label>
                    <p><?= date('M d, Y', strtotime($rental['rental_end_date'])) ?></p>
                </div>
                <div class="field"><label>Duration</label>
                    <p><?= $days ?> day(s)</p>
                </div>
                <div class="field"><label>Daily Rate</label>
                    <p><?= CURRENCY_SYMBOL . number_format($rental['rental_rate'], 2) ?></p>
                </div>
                <div class="field"><label>Security Deposit</label>
                    <p><?= CURRENCY_SYMBOL . number_format($rental['security_deposit'] ?? 0, 2) ?></p>
                </div>
                <div class="field"><label>Total Amount</label>
                    <p style="color:#6366f1;font-size:14px;">
                        <?= CURRENCY_SYMBOL . number_format($rental['total_amount'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>

        <div class="terms">
            <h2
                style="margin-bottom:10px;color:#475569;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.1em;">
                Terms & Conditions</h2>
            <ol>
                <li>The lessee agrees to return the vehicle in the same condition as received, normal wear excepted.
                </li>
                <li>Any traffic violations incurred during the rental period are the sole responsibility of the lessee.
                </li>
                <li>The vehicle must not be sub-leased or used for illegal activities.</li>
                <li>Late returns are subject to an additional daily rate per day of delay.</li>
                <li>Damage to the vehicle will be charged to the lessee based on repair cost assessment.</li>
                <li>The security deposit will be refunded upon satisfactory return of the vehicle.</li>
                <li>No smoking or pets are allowed inside the vehicle unless prior agreement is made.</li>
                <li>The lessor reserves the right to repossess the vehicle if these terms are violated.</li>
            </ol>
        </div>

        <div class="sig-row">
            <div class="sig-box">
                <div style="height:60px;"></div>
                <p>Lessee Signature</p>
                <p style="font-weight:700;color:#1e293b;">
                    <?= htmlspecialchars($rental['first_name'] . ' ' . $rental['last_name']) ?></p>
            </div>
            <div class="sig-box">
                <div style="height:60px;"></div>
                <p>Authorized Representative</p>
                <p style="font-weight:700;color:#1e293b;">Gensan Car Rental System</p>
            </div>
        </div>
        <p style="text-align:center;margin-top:30px;font-size:9px;color:#94a3b8;">Agreement generated on
            <?= date('F d, Y H:i') ?> · <?= htmlspecialchars($rental['agreement_number']) ?></p>
    </div>
</body>

</html>
