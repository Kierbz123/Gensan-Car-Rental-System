<?php
/**
 * Batch QR Code Generator
 * Path: modules/asset-tracking/ajax/batch-qr-generate.php
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/session-manager.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Enforce permissions
$authUser->requirePermission('vehicles.create');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/asset-tracking/', 'Invalid request method.', 'error');
}

// Validate CSRF Token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirect('modules/asset-tracking/', 'Invalid security token.', 'error');
}

$categoryId = (int) ($_POST['category_id'] ?? 0);

if (!$categoryId) {
    redirect('modules/asset-tracking/', 'Invalid vehicle category selected.', 'error');
}

try {
    $db = Database::getInstance();
    $vehicleObj = new Vehicle();

    // Verify category exists
    $categoryData = $db->fetchOne("SELECT category_name FROM vehicle_categories WHERE category_id = ?", [$categoryId]);
    if (!$categoryData) {
        redirect('modules/asset-tracking/', 'Category does not exist.', 'error');
    }

    // Fetch all active vehicles belonging to this category
    $vehicles = $db->fetchAll(
        "SELECT * FROM vehicles WHERE category_id = ? AND deleted_at IS NULL ORDER BY plate_number ASC",
        [$categoryId]
    );

    if (empty($vehicles)) {
        redirect('modules/asset-tracking/index.php?category=' . urlencode($categoryId), 'No active vehicles found in this category.', 'info');
    }

    $generatedVehicles = [];

    foreach ($vehicles as $v) {
        // Generate QR Code if physically absent or not logged
        $qrPath = BASE_PATH . ltrim($v['qr_code_path'] ?? '', '/');

        if (empty($v['qr_code_path']) || !is_file($qrPath)) {
            try {
                $vehicleObj->generateQRCode($v['vehicle_id']);
                // fetch updated data specifically for this vehicle to get the new path
                $v = $vehicleObj->getById($v['vehicle_id']);
            } catch (Exception $e) {
                error_log("Failed to generate QR for vehicle {$v['vehicle_id']}: " . $e->getMessage());
                continue;
            }
        }
        $generatedVehicles[] = $v;
    }

    // Terminate PHP logic and display a dedicated batch-print DOM
    ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Batch QR Codes - <?php echo htmlspecialchars($categoryData['category_name']); ?></title>
            <style>
                body { font-family: system-ui, sans-serif; text-align: center; padding: 20px; background: #f5f5f5; color: #111; }
                .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; max-width: 1200px; margin: 0 auto; }
                .qr-card { background: #fff; border: 2px solid #000; padding: 20px; page-break-inside: avoid; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .qr-image { width: 220px; height: 220px; margin: 0 auto; display: block; mix-blend-mode: multiply; }
                .vehicle-info { margin-top: 15px; font-size: 14px; line-height: 1.4; }
                .vehicle-id { font-size: 20px; font-weight: 900; margin: 5px 0; text-transform: uppercase; }
            
                @media print { 
                    body { padding: 0; background: #fff; } 
                    .no-print { display: none; } 
                    .grid-container { display: block; }
                    .qr-card { 
                        border: 2px dashed #999; 
                        margin-bottom: 25px; 
                        border-radius: 0; 
                        box-shadow: none; 
                        float: left; 
                        width: 42%; 
                        margin: 2% 4%; 
                        height: auto; 
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-bottom: 40px; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: inline-block;">
                <h2 style="margin-top: 0; margin-bottom: 8px; font-weight: 900; text-transform: uppercase;">Batch Asset Tag Generation</h2>
                <p style="margin-top: 0; margin-bottom: 20px; color: #555;">
                    Category: <strong><?php echo htmlspecialchars($categoryData['category_name']); ?></strong> (<?php echo count($generatedVehicles); ?> Assets)
                </p>
                <button onclick="window.print()" style="padding: 12px 24px; font-size: 14px; font-weight: bold; cursor: pointer; background: #000; color: #fff; border: none; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.05em;">Print All Tags</button>
                <button onclick="window.history.back()" style="padding: 12px 24px; font-size: 14px; cursor: pointer; background: #e5e7eb; color: #111; border: none; font-weight: bold; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.05em; margin-left: 10px;">Return</button>
            </div>

            <div class="grid-container">
                <?php foreach ($generatedVehicles as $vData): ?>
                        <?php $qrUrl = !empty($vData['qr_code_path']) ? BASE_URL . ltrim($vData['qr_code_path'], '/') : ''; ?>
                        <div class="qr-card">
                            <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="QR Code" class="qr-image">
                            <div class="vehicle-info">
                                <div class="vehicle-id"><?php echo htmlspecialchars($vData['vehicle_id']); ?></div>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars(trim(($vData['brand'] ?? '') . ' ' . ($vData['model'] ?? ''))); ?></div>
                                <div style="font-weight: 800; font-size: 16px; margin-top: 5px; color: #333;">Plate: <?php echo htmlspecialchars($vData['plate_number'] ?? ''); ?></div>
                            </div>
                        </div>
                <?php endforeach; ?>
            </div>
        
            <script>
                // Optionally auto-open the print dialog once image rendering is complete
                if (window.opener || window.history.length > 1) {
                    setTimeout(function() { window.print(); }, 800);
                }
            </script>
        </body>
        </html>
        <?php

} catch (Exception $e) {
    error_log("Batch QR Generation DB Error: " . $e->getMessage());
    redirect('modules/asset-tracking/', 'A database error occurred during batch generation.', 'error');
}
