<?php
/**
 * QR Code Generator for Vehicles
 * Path: modules/asset-tracking/qr-generator.php
 */

require_once '../../includes/session-manager.php';

// Read action early — download must send headers BEFORE header.php outputs any HTML
$vehicleId = $_GET['id'] ?? '';
$action    = $_GET['action'] ?? 'display';

// ── Validation & Permissions (All redirects MUST happen before header.php) ────
$authUser->requirePermission('vehicles.view');

if (empty($vehicleId)) {
    redirect('modules/asset-tracking/', 'Vehicle ID is required', 'error');
}

$vehicle     = new Vehicle();
$vehicleData = $vehicle->getById($vehicleId);

if (!$vehicleData) {
    redirect('modules/asset-tracking/', 'Vehicle not found', 'error');
}

// ── Early: handle download (must happen before any HTML output) ───────────────
if ($action === 'download') {
    if (!empty($vehicleData['qr_code_path'])) {
        $expectedDir  = realpath(BASE_PATH . 'assets/qr-codes');
        $resolvedPath = realpath(BASE_PATH . ltrim($vehicleData['qr_code_path'], '/'));
        if ($resolvedPath && $expectedDir && strpos($resolvedPath, $expectedDir) === 0 && is_file($resolvedPath)) {
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="' . $vehicleData['vehicle_id'] . '_QR.png"');
            header('Content-Length: ' . filesize($resolvedPath));
            readfile($resolvedPath);
            exit;
        }
    }
    // Fall through to normal page with error message if file missing
    redirect("qr-generator.php?id=" . urlencode($vehicleId), 'QR code file not found for download.', 'error');
}

// ── HTML Output Begins Here ───────────────────────────────────────────────────

// Auto-generate QR if it doesn't exist yet — only users with update permission may trigger generation
$qrGenerationError = null;
$qrPath = BASE_PATH . ltrim($vehicleData['qr_code_path'] ?? '', '/');
if (empty($vehicleData['qr_code_path']) || !file_exists($qrPath)) {
    if ($authUser->hasPermission('vehicles.update')) {
        try {
            $vehicle->generateQRCode($vehicleId);
            $vehicleData = $vehicle->getById($vehicleId);
        } catch (Exception $e) {
            $qrGenerationError = 'QR auto-generation failed: ' . $e->getMessage();
        }
    }
}

require_once '../../includes/header.php';

// Build URLs
$qrFilePath = !empty($vehicleData['qr_code_path']) ? BASE_PATH . ltrim($vehicleData['qr_code_path'], '/') : '';
$qrCodeUrl  = (!empty($vehicleData['qr_code_path']) && is_file($qrFilePath))
    ? BASE_URL . $vehicleData['qr_code_path'] . '?v=' . filemtime($qrFilePath)
    : '';

require_once BASE_PATH . 'includes/qr-token.php';
$correctUrl = buildScanUrl($vehicleId);

// The URL embedded inside the QR code (absolute, for mobile scanners)
$qrData      = json_decode($vehicleData['qr_code_data'] ?? '{}', true);
$embeddedUrl = $qrData['url'] ?? $correctUrl;

// SEC-03: Ensure embedded URL uses a safe scheme (guard against DB tampering)
if (!preg_match('#^https?://#i', $embeddedUrl)) {
    $embeddedUrl = $correctUrl;
}

// Detect if stored QR still has a localhost URL (generated before the LAN-IP fix)
$qrHasLocalhost = (
    str_contains($embeddedUrl, '://localhost') ||
    str_contains($embeddedUrl, '://127.0.0.1')
);

// Detect if stored QR still points to the old admin page (generated before vehicle-scan.php)
$qrPointsToAdmin = str_contains($embeddedUrl, 'vehicle-details.php');


// ── Handle print view ────────────────────────────────────────────────────────
if ($action === 'print') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>QR Code – <?= htmlspecialchars($vehicleData['vehicle_id'] ?? '') ?></title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Segoe UI', system-ui, sans-serif;
                background: #fff;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 24px;
                color: #111;
            }
            .sticker {
                border: 2.5px solid #111;
                border-radius: 12px;
                padding: 24px 28px 20px;
                display: inline-flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                max-width: 340px;
                width: 100%;
            }
            .sticker-brand {
                font-size: 10px;
                letter-spacing: 0.18em;
                font-weight: 800;
                text-transform: uppercase;
                color: #555;
            }
            .sticker img { width: 240px; height: 240px; display: block; }
            .sticker-id {
                font-size: 22px;
                font-weight: 900;
                letter-spacing: 0.06em;
                text-align: center;
            }
            .sticker-sub {
                font-size: 13px;
                color: #444;
                text-align: center;
                line-height: 1.4;
            }
            .sticker-url {
                font-size: 9px;
                color: #777;
                word-break: break-all;
                text-align: center;
                border-top: 1px solid #ddd;
                padding-top: 10px;
                width: 100%;
            }
            .scan-hint {
                margin-top: 8px;
                font-size: 11px;
                color: #555;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .no-print { display: flex; gap: 12px; margin-top: 24px; justify-content: center; }
            .btn-p {
                padding: 10px 22px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                border: none;
            }
            .btn-primary-p { background: #2563eb; color: #fff; }
            .btn-secondary-p { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
            @media print {
                .no-print { display: none !important; }
                body { padding: 0; }
            }
        </style>
    </head>
    <body>
        <div class="sticker">
            <div class="sticker-brand">Gensan Car Rental Services · Asset Tag</div>
            <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="QR Code for <?= htmlspecialchars($vehicleData['vehicle_id']) ?>">
            <div class="sticker-id"><?= htmlspecialchars($vehicleData['vehicle_id'] ?? '') ?></div>
            <div class="sticker-sub">
                <?= htmlspecialchars(($vehicleData['brand'] ?? '') . ' ' . ($vehicleData['model'] ?? '')) ?><br>
                Plate: <?= htmlspecialchars($vehicleData['plate_number'] ?? '') ?>
                &nbsp;·&nbsp;
                <?= htmlspecialchars($vehicleData['category_name'] ?? '') ?>
            </div>
            <div class="scan-hint">
                📷 Scan to open Vehicle Profile
            </div>
            <div class="sticker-url"><?= htmlspecialchars($embeddedUrl) ?></div>
        </div>

        <div class="no-print">
            <button class="btn-p btn-primary-p" onclick="window.print()">🖨️ Print Sticker</button>
            <button class="btn-p btn-secondary-p" onclick="window.close()">Close</button>
        </div>
        <script>if (window.opener) setTimeout(function () { window.print(); }, 600);</script>
    </body>
    </html>
    <?php
    exit;
}



// ── Display view ──────────────────────────────────────────────────────────────
?>

<div class="page-header">
    <div class="page-title">
        <h1>QR Code Generator</h1>
        <p>Vehicle #<?= htmlspecialchars($vehicleData['vehicle_id'] ?? '') ?> —
            <?= htmlspecialchars(($vehicleData['brand'] ?? '') . ' ' . ($vehicleData['model'] ?? '')) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="vehicle-details.php?id=<?= urlencode($vehicleId) ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Vehicle
        </a>
    </div>
</div>

<?php if (!empty($qrGenerationError)): ?>
<div style="max-width:960px; margin:0 auto var(--space-4); background:#fef2f2; border:1.5px solid #fca5a5; border-radius:var(--radius-md); padding:var(--space-4); display:flex; align-items:flex-start; gap:12px;">
    <i data-lucide="x-circle" style="width:20px;height:20px;color:#dc2626;flex-shrink:0;margin-top:1px;"></i>
    <div>
        <div style="font-weight:700; color:#991b1b; margin-bottom:4px;">QR Code Generation Failed</div>
        <div style="font-size:0.85rem; color:#7f1d1d;"><?= htmlspecialchars($qrGenerationError) ?></div>
    </div>
</div>
<?php endif; ?>

<?php if ($qrPointsToAdmin && !$qrHasLocalhost): ?>
<div style="max-width:960px; margin:0 auto var(--space-4); background:#fff7ed; border:1.5px solid #fed7aa; border-radius:var(--radius-md); padding:var(--space-4); display:flex; align-items:flex-start; gap:12px;">
    <i data-lucide="refresh-cw" style="width:20px;height:20px;color:#ea580c;flex-shrink:0;margin-top:1px;"></i>
    <div style="flex:1;">
        <div style="font-weight:700; color:#9a3412; margin-bottom:4px;">QR Code Uses Old Admin URL</div>
        <div style="font-size:0.85rem; color:#c2410c; margin-bottom:8px;">This QR was generated before <code>vehicle-scan.php</code> was deployed. It links to the admin page (login required) instead of the public scan page.</div>
        <button type="button" onclick="regenerateQR(<?= htmlspecialchars(json_encode($vehicleId)) ?>)"
            style="background:#ea580c;color:#fff;border:none;border-radius:6px;padding:8px 18px;font-weight:700;font-size:0.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Regenerate Now
        </button>
    </div>
</div>
<?php endif; ?>

<?php if ($qrHasLocalhost): ?>

<div style="max-width:960px; margin:0 auto var(--space-4); background:#fef2f2; border:1.5px solid #fca5a5; border-radius:var(--radius-md); padding:var(--space-4); display:flex; align-items:flex-start; gap:12px;">
    <i data-lucide="alert-triangle" style="width:20px;height:20px;color:#dc2626;flex-shrink:0;margin-top:1px;"></i>
    <div style="flex:1;">
        <div style="font-weight:700; color:#991b1b; margin-bottom:4px;">⚠️ This QR code won't work on a phone — it still points to <code>localhost</code></div>
        <div style="font-size:0.85rem; color:#7f1d1d; margin-bottom:8px;">
            Phones can't reach <code>localhost</code> — it resolves to the phone itself, not this PC.<br>
            Click <strong>Regenerate</strong> to create a new QR with your network IP:
            <code style="display:inline-block; background:#fee2e2; padding:2px 6px; border-radius:4px; margin-top:4px; word-break:break-all;"><?= htmlspecialchars($correctUrl) ?></code>
        </div>
        <button type="button" onclick="regenerateQR(<?= htmlspecialchars(json_encode($vehicleId)) ?>)"
            style="background:#dc2626;color:#fff;border:none;border-radius:6px;padding:8px 18px;font-weight:700;font-size:0.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Regenerate Now
        </button>
    </div>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); max-width: 960px; margin: 0 auto;">

    <!-- Left: QR Preview + Actions -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i data-lucide="qr-code" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;"></i>
                Vehicle QR Code: <?= htmlspecialchars($vehicleData['vehicle_id'] ?? '') ?>
            </h2>
        </div>
        <div class="card-body text-center">

            <!-- QR Image -->
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-md); display:inline-block; padding:12px; margin-bottom:var(--space-4);">
                <?php if ($qrCodeUrl): ?>
                    <img id="qrImage" src="<?= $qrCodeUrl ?>" alt="QR Code"
                         style="width:220px; height:220px; display:block;">
                <?php else: ?>
                    <div style="width:220px;height:220px;display:flex;align-items:center;justify-content:center;background:var(--bg-muted);color:var(--text-muted);font-size:0.8rem;font-weight:bold;border-radius:8px;">
                        QR NOT GENERATED
                    </div>
                <?php endif; ?>
            </div>

            <!-- Vehicle Summary -->
            <div style="text-align:left; margin-bottom:var(--space-4);">
                <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <th style="padding:8px 4px; color:var(--text-muted); font-weight:600; white-space:nowrap;">Vehicle ID</th>
                        <td style="padding:8px 4px; font-weight:700;"><?= htmlspecialchars($vehicleData['vehicle_id'] ?? '') ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <th style="padding:8px 4px; color:var(--text-muted); font-weight:600;">Plate</th>
                        <td style="padding:8px 4px; font-weight:700;"><?= htmlspecialchars($vehicleData['plate_number'] ?? '') ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <th style="padding:8px 4px; color:var(--text-muted); font-weight:600;">Category</th>
                        <td style="padding:8px 4px;"><?= htmlspecialchars($vehicleData['category_name'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th style="padding:8px 4px; color:var(--text-muted); font-weight:600;">Year</th>
                        <td style="padding:8px 4px;"><?= htmlspecialchars($vehicleData['year_model'] ?? '') ?></td>
                    </tr>
                </table>
            </div>

            <!-- Embedded URL display -->
            <div style="background:var(--bg-muted); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:var(--space-3); margin-bottom:var(--space-4); text-align:left;">
                <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="link" style="width:12px;height:12px;"></i> Embedded URL (inside QR)
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span id="embeddedUrl" style="font-size:0.72rem; font-family:monospace; color:var(--primary-600); word-break:break-all; flex:1; font-weight:600;"><?= htmlspecialchars($embeddedUrl) ?></span>
                    <button onclick="copyUrl()" title="Copy URL" style="background:none;border:1px solid var(--border-color);border-radius:6px;padding:4px 8px;cursor:pointer;flex-shrink:0;display:flex;align-items:center;gap:4px;font-size:0.72rem;color:var(--text-secondary);">
                        <i data-lucide="copy" style="width:12px;height:12px;"></i>
                        <span id="copyLabel">Copy</span>
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3" style="flex-wrap:wrap; justify-content:center;">
                <a href="?id=<?= urlencode($vehicleId) ?>&action=print" target="_blank" class="btn btn-primary">
                    <i data-lucide="printer" style="width:16px;height:16px;"></i> Print Sticker
                </a>
                <a href="?id=<?= urlencode($vehicleId) ?>&action=download" class="btn btn-secondary">
                    <i data-lucide="download" style="width:16px;height:16px;"></i> Download PNG
                </a>
                <button type="button" onclick="regenerateQR(<?= htmlspecialchars(json_encode($vehicleId)) ?>)" class="btn btn-ghost">
                    <i data-lucide="refresh-cw" style="width:16px;height:16px;"></i> Regenerate
                </button>
            </div>
        </div>
    </div>

    <!-- Right: How-to Guide -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">

        <!-- Scanning Guide Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                    <i data-lucide="smartphone" style="width:18px;height:18px;color:var(--primary-600);"></i>
                    How to Scan This QR
                </h2>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:var(--space-3);">

                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="background:var(--primary-600); color:#fff; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:900; flex-shrink:0;">1</div>
                        <div>
                            <div style="font-weight:700; font-size:0.875rem; margin-bottom:2px;">Open your Camera App</div>
                            <div style="font-size:0.8rem; color:var(--text-secondary);">Works with the built-in camera on Android & iPhone — no special app needed. Google Lens also works.</div>
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="background:var(--primary-600); color:#fff; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:900; flex-shrink:0;">2</div>
                        <div>
                            <div style="font-weight:700; font-size:0.875rem; margin-bottom:2px;">Point at the QR sticker</div>
                            <div style="font-size:0.8rem; color:var(--text-secondary);">Keep the phone steady — the camera auto-detects and reads the code in 1–2 seconds.</div>
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="background:var(--primary-600); color:#fff; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:900; flex-shrink:0;">3</div>
                        <div>
                            <div style="font-weight:700; font-size:0.875rem; margin-bottom:2px;">Tap the link notification</div>
                            <div style="font-size:0.8rem; color:var(--text-secondary);">A banner appears at the top of the screen showing the URL. Tap it to open the vehicle profile in your browser.</div>
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="background:var(--success); color:#fff; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:900; flex-shrink:0;">✓</div>
                        <div>
                            <div style="font-weight:700; font-size:0.875rem; margin-bottom:2px;">Vehicle profile opens instantly</div>
                            <div style="font-size:0.8rem; color:var(--text-secondary);">The page shows real-time status, maintenance history, compliance records, and rental info for this vehicle.</div>
                        </div>
                    </div>

                </div>

                <!-- Network Requirement Note -->
                <div style="margin-top:var(--space-4); background:var(--warning-50,#fffbeb); border:1px solid var(--warning-200,#fde68a); border-radius:var(--radius-sm); padding:var(--space-3);">
                    <div style="display:flex; gap:8px; align-items:flex-start;">
                        <i data-lucide="wifi" style="width:16px;height:16px;color:var(--warning-600,#d97706);flex-shrink:0;margin-top:1px;"></i>
                        <div style="font-size:0.8rem; color:var(--warning-800,#92400e);">
                            <strong>Network Required:</strong> The phone must be connected to the same Wi-Fi network as this server, or the server must be accessible from the internet. The embedded URL is:<br>
                            <code style="font-size:0.72rem; word-break:break-all;"><?= htmlspecialchars($embeddedUrl) ?></code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Placement Guide -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                    <i data-lucide="map-pin" style="width:18px;height:18px;color:var(--warning-600,#d97706);"></i>
                    Sticker Placement Guide
                </h2>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:var(--space-2); font-size:0.875rem;">
                    <div style="display:flex; align-items:center; gap:10px; padding:var(--space-2); background:var(--bg-muted); border-radius:var(--radius-sm);">
                        <i data-lucide="check-circle" style="width:16px;height:16px;color:var(--success);flex-shrink:0;"></i>
                        <span><strong>Driver's door jamb</strong> — most accessible, protected from weather</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; padding:var(--space-2); background:var(--bg-muted); border-radius:var(--radius-sm);">
                        <i data-lucide="check-circle" style="width:16px;height:16px;color:var(--success);flex-shrink:0;"></i>
                        <span><strong>Windshield corner (inside)</strong> — visible without opening doors</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; padding:var(--space-2); background:var(--bg-muted); border-radius:var(--radius-sm);">
                        <i data-lucide="check-circle" style="width:16px;height:16px;color:var(--success);flex-shrink:0;"></i>
                        <span><strong>Dashboard</strong> — ideal for mechanics doing in-cabin checks</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; padding:var(--space-2); background:var(--bg-muted); border-radius:var(--radius-sm);">
                        <i data-lucide="x-circle" style="width:16px;height:16px;color:var(--danger);flex-shrink:0;"></i>
                        <span style="color:var(--text-muted);">Avoid direct sunlight / extreme heat — QR may fade</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Who Scans It -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                    <i data-lucide="users" style="width:18px;height:18px;color:var(--primary-600);"></i>
                    Who Uses This QR
                </h2>
            </div>
            <div class="card-body">
                <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
                    <?php
                    $roles = [
                        ['icon' => 'car', 'title' => 'Lot Attendants', 'desc' => 'Check vehicle status before moving it'],
                        ['icon' => 'sparkles', 'title' => 'Cleaners', 'desc' => 'Confirm vehicle ID when detailing'],
                        ['icon' => 'wrench', 'title' => 'Mechanics', 'desc' => 'Pull up maintenance history instantly'],
                    ];
                    foreach ($roles as $r): ?>
                        <div style="flex:1; min-width:130px; background:var(--bg-muted); border-radius:var(--radius-md); padding:var(--space-3); text-align:center;">
                            <i data-lucide="<?= $r['icon'] ?>" style="width:22px;height:22px;color:var(--primary-600);margin-bottom:6px;"></i>
                            <div style="font-weight:700; font-size:0.82rem; margin-bottom:2px;"><?= $r['title'] ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted);"><?= $r['desc'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php if ($authUser->hasPermission('vehicles.create')): ?>
    <div class="card" style="max-width: 960px; margin: var(--space-8) auto 0;">
        <div class="card-header">
            <h2 class="card-title">Batch QR Generation</h2>
        </div>
        <div class="card-body">
            <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: var(--space-4);">Generate QR codes for all vehicles in a category:</p>
            <form action="ajax/batch-qr-generate.php" method="POST" class="form-row" style="align-items: flex-end;">
                <?= csrfField() ?>
                <div class="form-group" style="flex: 1; min-width: 180px;">
                    <label>Category</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php
                        $db = Database::getInstance();
                        $categories = $db->fetchAll("SELECT * FROM vehicle_categories WHERE is_active = TRUE");
                        foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Generate All</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    lucide.createIcons();

    var csrfToken = <?= json_encode(getCsrfToken()) ?>;

    function copyUrl() {
        var url = document.getElementById('embeddedUrl').textContent.trim();
        navigator.clipboard.writeText(url).then(function () {
            var lbl = document.getElementById('copyLabel');
            lbl.textContent = 'Copied!';
            setTimeout(function () { lbl.textContent = 'Copy'; }, 2000);
        }).catch(function () {
            // Fallback for older browsers
            var ta = document.createElement('textarea');
            ta.value = url;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            document.getElementById('copyLabel').textContent = 'Copied!';
            setTimeout(function () { document.getElementById('copyLabel').textContent = 'Copy'; }, 2000);
        });
    }

    function regenerateQR(vehicleId) {
        if (confirm('Regenerate the QR code for this vehicle?\n\nAny printed stickers with the old QR will still work as long as the vehicle ID does not change.')) {
            var btn = event.target.closest('button');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i data-lucide="loader" style="width:16px;height:16px;"></i> Regenerating…'; lucide.createIcons(); }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'ajax/regenerate-qr.php');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                var r = JSON.parse(xhr.responseText || '{}');
                if (r.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (r.message || 'Failed to regenerate'));
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="refresh-cw" style="width:16px;height:16px;"></i> Regenerate'; lucide.createIcons(); }
                }
            };
            xhr.onerror = function () {
                alert('A network error occurred. Please try again.');
                if (btn) { btn.disabled = false; }
            };
            xhr.send('vehicle_id=' + encodeURIComponent(vehicleId) + '&csrf_token=' + encodeURIComponent(csrfToken));
        }
    }
</script>

<?php require_once '../../includes/footer.php'; ?>