<?php
/**
 * Force regenerate all vehicle QR codes
 * Access via browser: http://localhost/IATPS/gensan-car-rental-system/regen_qr_web.php
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/session-manager.php';
require_once __DIR__ . '/includes/functions.php';

// Only admins can run this
if (!$authUser || !$authUser->hasPermission('vehicles.create')) {
    die('Access denied.');
}

$db = Database::getInstance();
$vehicleObj = new Vehicle();

$vehicles = $db->fetchAll("SELECT vehicle_id, plate_number, brand, model FROM vehicles WHERE deleted_at IS NULL ORDER BY vehicle_id ASC");

$results = [];
foreach ($vehicles as $v) {
    try {
        $path = $vehicleObj->generateQRCode($v['vehicle_id']);
        $results[] = ['id' => $v['vehicle_id'], 'status' => 'ok', 'path' => $path, 'size' => filesize($path)];
    } catch (Exception $e) {
        $results[] = ['id' => $v['vehicle_id'], 'status' => 'error', 'msg' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>QR Regeneration</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f9fafb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        th {
            background: #111;
            color: #fff;
            padding: 12px 16px;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.875rem;
        }

        .ok {
            color: #16a34a;
            font-weight: bold;
        }

        .error {
            color: #dc2626;
            font-weight: bold;
        }

        img {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <h1 style="margin-bottom: 0.25rem;">QR Code Regeneration Complete</h1>
    <p style="color: #666; margin-bottom: 1.5rem;">
        <?= count($vehicles) ?> vehicle(s) processed
    </p>
    <table>
        <thead>
            <tr>
                <th>Vehicle ID</th>
                <th>Status</th>
                <th>File Size</th>
                <th>Preview</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><strong>
                            <?= htmlspecialchars($r['id']) ?>
                        </strong></td>
                    <td class="<?= $r['status'] ?>">
                        <?= $r['status'] === 'ok' ? '✅ OK' : '❌ ' . htmlspecialchars($r['msg'] ?? '') ?>
                    </td>
                    <td>
                        <?= isset($r['size']) ? number_format($r['size']) . ' bytes' : '—' ?>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'ok'): ?>
                            <img src="<?= BASE_URL . 'assets/images/qr-codes/' . htmlspecialchars($r['id']) . '.png?t=' . time() ?>"
                                width="80" height="80" alt="QR">
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top: 1.5rem;"><a href="modules/asset-tracking/index.php" style="color: #2563eb;">← Back to Asset
            Tracking</a></p>
</body>

</html>