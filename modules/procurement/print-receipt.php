<?php
/**
 * Print PR Receipt / Shopping List (Thermal Printer Friendly)
 * Path: modules/procurement/print-receipt.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('procurement.view');

$db = Database::getInstance();
$prId = (int) ($_GET['id'] ?? 0);
if (!$prId) {
    die("Invalid PR ID.");
}

$pr = $db->fetchOne(
    "SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) AS requester_name
     FROM procurement_requests pr
     LEFT JOIN users u ON pr.requestor_id = u.user_id
     WHERE pr.pr_id = ?",
    [$prId]
);

if (!$pr) {
    die("Purchase Request not found.");
}

$items = $db->fetchAll(
    "SELECT * FROM procurement_items WHERE pr_id = ? ORDER BY line_number",
    [$prId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping List - <?= htmlspecialchars($pr['pr_number']) ?></title>
    <style>
        /* Thermal Printer specific styling (usually 58mm or 80mm width) */
        @page { margin: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 10px;
            width: 300px; /* Approx 80mm width */
            max-width: 100%;
        }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .header h1 { font-size: 16px; margin: 0 0 5px 0; }
        .header p { margin: 2px 0; font-size: 11px; }
        
        .info-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .info-label { font-weight: bold; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .items-table th { border-bottom: 1px solid #000; border-top: 1px solid #000; text-align: left; padding: 4px 0; font-size: 10px; }
        .items-table td { padding: 4px 0; vertical-align: top; }
        .item-desc { width: 45%; }
        .item-qty { width: 25%; text-align: right; }
        .item-price { width: 30%; text-align: right; }
        
        .totals { margin-top: 10px; border-top: 1px dashed #000; padding-top: 5px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 3px; font-weight: bold; font-size: 14px;}
        
        .footer { margin-top: 30px; text-align: center; border-top: 1px dashed #000; padding-top: 10px; font-size: 10px;}
        .signature-line { margin-top: 40px; border-top: 1px solid #000; width: 80%; margin-left: auto; margin-right: auto; padding-top: 5px; text-align: center;}
        
        /* Print media overrides */
        @media print {
            body { width: 100%; padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h1>Gensan Car Rental</h1>
        <p>Purchasing Dept.</p>
        <p>Shopping List / Authorization</p>
    </div>

    <div class="info">
        <div class="info-row">
            <span class="info-label">PR Num:</span>
            <span><?= htmlspecialchars($pr['pr_number']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date:</span>
            <span><?= date('M d, Y') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Requester:</span>
            <span><?= htmlspecialchars($pr['requester_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Status:</span>
            <span><?= strtoupper(str_replace('_', ' ', $pr['status'])) ?></span>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th class="item-desc">Item</th>
                <th class="item-qty">Qty</th>
                <th class="item-price">Est. Cost</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $grandTotal = 0;
            foreach ($items as $item): 
                $grandTotal += $item['estimated_total_cost'];
            ?>
            <tr>
                <td class="item-desc">
                    <strong><?= htmlspecialchars($item['item_description']) ?></strong>
                </td>
                <td class="item-qty"><?= number_format($item['quantity'], 0) ?> <?= $item['unit'] ?></td>
                <td class="item-price"><?= number_format($item['estimated_total_cost'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>TOTAL EST:</span>
            <span>PHP <?= number_format($grandTotal, 2) ?></span>
        </div>
    </div>

    <div class="signature-line">
        <p>Purchaser Signature</p>
    </div>

    <div class="footer">
        <p>Attach physical receipt to this slip upon return.</p>
    </div>

</body>
</html>
