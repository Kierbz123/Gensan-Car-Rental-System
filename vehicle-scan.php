<?php
/**
 * GCR Vehicle Scan Page
 * Public QR landing page — no admin login required.
 * Protected by a stateless HMAC-SHA256 token embedded in the QR URL.
 *
 * Path   : /vehicle-scan.php  (root, for the shortest possible URL)
 * URL    : /vehicle-scan.php?id=GCR-HB-0006&t=<hmac_token>
 * Public : token validation only (shows core vehicle fields)
 * Staff  : if a valid GCR session cookie is present, shows mileage + service data
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/qr-token.php';

global $MAINTENANCE_SERVICE_TYPES;

// ── Inputs ────────────────────────────────────────────────────────────────────
$vehicleId = trim($_GET['id'] ?? '');
$token     = trim($_GET['t']  ?? '');

// ── Status configuration ──────────────────────────────────────────────────────
$statusConfig = [
    'available'      => ['bg' => '#dcfce7', 'color' => '#15803d', 'border' => '#86efac', 'dot' => '#22c55e', 'pulse' => true,  'label' => 'Available'],
    'rented'         => ['bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fca5a5', 'dot' => '#ef4444', 'pulse' => false, 'label' => 'Currently Rented'],
    'maintenance'    => ['bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#fcd34d', 'dot' => '#f59e0b', 'pulse' => false, 'label' => 'Under Maintenance'],
    'reserved'       => ['bg' => '#dbeafe', 'color' => '#1e40af', 'border' => '#93c5fd', 'dot' => '#3b82f6', 'pulse' => false, 'label' => 'Reserved'],
    'cleaning'       => ['bg' => '#ede9fe', 'color' => '#5b21b6', 'border' => '#c4b5fd', 'dot' => '#7c3aed', 'pulse' => false, 'label' => 'Being Cleaned'],
    'out_of_service' => ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1', 'dot' => '#94a3b8', 'pulse' => false, 'label' => 'Out of Service'],
    'retired'        => ['bg' => '#1e293b', 'color' => '#94a3b8', 'border' => '#334155', 'dot' => '#475569', 'pulse' => false, 'label' => 'Retired from Fleet'],
];

// ── Inline HTML helpers ───────────────────────────────────────────────────────
function renderScanShell(string $title, string $bodyHtml): never
{
    $assetsUrl = ASSETS_URL;
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>{$title} | GCR Scan</title>
  <link rel="stylesheet" href="{$assetsUrl}css/app.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#0f172a;min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1rem}
    .shell-card{background:#1e293b;border:1px solid #334155;border-radius:20px;padding:2.5rem 2rem;max-width:420px;width:100%;text-align:center}
    .shell-icon{width:64px;height:64px;margin:0 auto 1.25rem;opacity:.7}
    .shell-title{font-size:1.3rem;font-weight:700;color:#f1f5f9;margin-bottom:.5rem}
    .shell-msg{font-size:.9rem;color:#94a3b8;line-height:1.6}
    .gcr-brand{margin-bottom:2rem;color:#64748b;font-size:.8rem;letter-spacing:.05em;text-transform:uppercase}
    .gcr-brand strong{color:#3b82f6}
  </style>
</head>
<body>
  <div class="gcr-brand">⬡ <strong>GCR</strong> Fleet System</div>
  <div class="shell-card">{$bodyHtml}</div>
  <script src="{$assetsUrl}js/lucide.min.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
HTML;
    exit;
}

// ── No ID → Landing page ──────────────────────────────────────────────────────
if (empty($vehicleId)) {
    $assetsUrl = ASSETS_URL;
    $loginUrl  = BASE_URL . 'login.php';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>GCR Vehicle Scanner</title>
  <link rel="stylesheet" href="{$assetsUrl}css/app.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#0f172a;min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1rem;text-align:center}
    .brand{color:#3b82f6;font-size:1rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:2.5rem}
    .scanner-anim{width:160px;height:160px;position:relative;margin:0 auto 2rem}
    .scanner-anim .frame{position:absolute;inset:0;border:3px solid rgba(59,130,246,.3);border-radius:20px}
    .scanner-anim .corner{position:absolute;width:24px;height:24px}
    .corner.tl{top:-2px;left:-2px;border-top:4px solid #3b82f6;border-left:4px solid #3b82f6;border-radius:6px 0 0 0}
    .corner.tr{top:-2px;right:-2px;border-top:4px solid #3b82f6;border-right:4px solid #3b82f6;border-radius:0 6px 0 0}
    .corner.bl{bottom:-2px;left:-2px;border-bottom:4px solid #3b82f6;border-left:4px solid #3b82f6;border-radius:0 0 0 6px}
    .corner.br{bottom:-2px;right:-2px;border-bottom:4px solid #3b82f6;border-right:4px solid #3b82f6;border-radius:0 0 6px 0}
    .scanner-anim .scan-line{position:absolute;left:8px;right:8px;height:2px;background:linear-gradient(90deg,transparent,#3b82f6,transparent);animation:scanMove 2s ease-in-out infinite;top:50%}
    @keyframes scanMove{0%,100%{top:15%}50%{top:85%}}
    .scanner-anim .qr-icon{position:absolute;inset:30px;color:#334155;display:flex;align-items:center;justify-content:center}
    h1{font-size:1.5rem;font-weight:700;color:#f1f5f9;margin-bottom:.5rem}
    p{color:#64748b;font-size:.9rem;line-height:1.6;margin-bottom:2rem}
    .login-link{color:#3b82f6;text-decoration:none;font-size:.85rem;border:1px solid #1d4ed8;padding:.5rem 1.25rem;border-radius:20px;display:inline-block;transition:all .2s}
    .login-link:hover{background:#1e3a8a}
  </style>
</head>
<body>
  <div class="brand">⬡ GCR Fleet System</div>
  <div class="scanner-anim">
    <div class="frame"></div>
    <div class="corner tl"></div><div class="corner tr"></div>
    <div class="corner bl"></div><div class="corner br"></div>
    <div class="scan-line"></div>
    <div class="qr-icon"><i data-lucide="qr-code" style="width:60px;height:60px;"></i></div>
  </div>
  <h1>Vehicle QR Scanner</h1>
  <p>Point your camera at a vehicle QR sticker<br>to instantly view its real-time status and info.</p>
  <a href="{$loginUrl}" class="login-link">Staff Login →</a>
  <script src="{$assetsUrl}js/lucide.min.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
HTML;
    exit;
}

// ── Token validation ───────────────────────────────────────────────────────────
if (!validateScanToken($vehicleId, $token)) {
    http_response_code(403);
    renderScanShell('Invalid QR Code', '
        <i data-lucide="shield-x" class="shell-icon" style="color:#ef4444"></i>
        <div class="shell-title">Invalid QR Code</div>
        <div class="shell-msg">This QR code could not be verified. It may have been tampered with or printed from an outdated system.<br><br>Please re-print from the admin panel.</div>
    ');
}

// ── Load vehicle data ─────────────────────────────────────────────────────────
$db = Database::getInstance();
$vehicle = $db->fetchOne(
    "SELECT v.vehicle_id, v.plate_number, v.brand, v.model, v.variant,
            v.year_model, v.color, v.fuel_type, v.transmission, v.seating_capacity,
            v.current_status, v.current_location, v.mileage, v.primary_photo_path,
            vc.category_name
     FROM vehicles v
     JOIN vehicle_categories vc ON v.category_id = vc.category_id
     WHERE v.vehicle_id = ? AND v.deleted_at IS NULL",
    [$vehicleId]
);

if (!$vehicle) {
    http_response_code(404);
    renderScanShell('Vehicle Not Found', '
        <i data-lucide="car-off" class="shell-icon" style="color:#94a3b8"></i>
        <div class="shell-title">Vehicle Not Found</div>
        <div class="shell-msg">Vehicle ID <strong style="color:#f1f5f9">' . htmlspecialchars($vehicleId) . '</strong> is not registered or has been decommissioned from the fleet.</div>
    ');
}

// ── Optional staff session detection ──────────────────────────────────────────
// Manually check the GCR cookie — we intentionally do NOT call session-manager.php
// because that would redirect unauthenticated public users to login.php.
$isStaffView   = false;
$staffRole     = null;
$staffName     = null;
$showAdminLink = false;

if (!empty($_COOKIE[SESSION_NAME])) {
    $staffSession = $db->fetchOne(
        "SELECT u.role, u.first_name, u.last_name
         FROM user_sessions s
         JOIN users u ON s.user_id = u.user_id
         WHERE s.session_id = ?
           AND s.expires_at > NOW()
           AND s.is_valid = TRUE
           AND u.status = 'active'",
        [$_COOKIE[SESSION_NAME]]
    );
    if ($staffSession) {
        $isStaffView   = true;
        $staffRole     = $staffSession['role'];
        $staffName     = $staffSession['first_name'];
        $showAdminLink = !in_array($staffRole, ['qr_scanner', 'mechanic'], true);
    }
}

// ── Extended staff data ───────────────────────────────────────────────────────
$lastMaintenance   = null;
$nextMaintenance   = null;
$complianceSummary = null;

if ($isStaffView) {
    $lastMaintenance = $db->fetchOne(
        "SELECT MAX(service_date) AS last_date FROM maintenance_logs WHERE vehicle_id = ?",
        [$vehicleId]
    );
    $nextMaintenance = $db->fetchOne(
        "SELECT service_type, next_due_date
         FROM maintenance_schedules
         WHERE vehicle_id = ? AND next_due_date >= CURDATE()
         ORDER BY next_due_date ASC LIMIT 1",
        [$vehicleId]
    );
    $complianceSummary = $db->fetchOne(
        "SELECT
            COALESCE(SUM(CASE WHEN expiry_date < CURDATE() THEN 1 ELSE 0 END),0)              AS expired_count,
            COALESCE(SUM(CASE WHEN expiry_date >= CURDATE()
                          AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END),0) AS expiring_count
         FROM compliance_records
         WHERE vehicle_id = ?
           AND status NOT IN ('pending','cancelled')
           AND expiry_date IS NOT NULL",
        [$vehicleId]
    );
}

// ── Page variables ────────────────────────────────────────────────────────────
$sc        = $statusConfig[$vehicle['current_status']] ?? $statusConfig['out_of_service'];
$photoUrl  = !empty($vehicle['primary_photo_path'])
    ? BASE_URL . ltrim($vehicle['primary_photo_path'], '/')
    : null;
$location  = !empty($vehicle['current_location'])
    ? ucwords(str_replace('_', ' ', $vehicle['current_location']))
    : 'Not Recorded';
$fullName  = trim($vehicle['brand'] . ' ' . $vehicle['model'] . (!empty($vehicle['variant']) ? ' ' . $vehicle['variant'] : ''));
$pageTitle = $fullName . ' — ' . $vehicle['plate_number'];
$scanTime  = date('F j, Y · g:i A');
$loginUrl  = BASE_URL . 'login.php?return=' . urlencode('vehicle-scan.php?id=' . $vehicleId . '&t=' . $token);
$adminUrl  = BASE_URL . 'modules/asset-tracking/vehicle-details.php?id=' . urlencode($vehicleId);
$assetsUrl = ASSETS_URL;

$serviceTypeLabels = [
    'oil_change' => 'Oil Change', 'tire_rotation' => 'Tire Rotation',
    'brake_inspection' => 'Brake Inspection', 'engine_tuneup' => 'Engine Tune-up',
    'transmission_service' => 'Transmission Service', 'aircon_cleaning' => 'Aircon Cleaning',
    'battery_check' => 'Battery Check', 'coolant_flush' => 'Coolant Flush',
    'timing_belt' => 'Timing Belt Replacement', 'general_checkup' => 'General Check-up',
    'emergency_repair' => 'Emergency Repair', 'body_repair' => 'Body Repair',
    'detailing' => 'Detailing', 'others' => 'Other Service',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <title><?= htmlspecialchars($pageTitle) ?> | GCR</title>
    <link rel="stylesheet" href="<?= $assetsUrl ?>css/app.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #0f172a;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ── Top bar ─────────────────────────────────────────────────────── */
        .scan-topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 1.25rem;
            background: rgba(15,23,42,.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,.07);
        }
        .topbar-brand {
            display: flex; align-items: center; gap: .5rem;
            font-size: .85rem; font-weight: 700; color: #3b82f6; letter-spacing: .05em;
        }
        .topbar-brand i { width: 18px; height: 18px; }
        .topbar-pill {
            font-size: .7rem; font-weight: 600; letter-spacing: .06em;
            text-transform: uppercase; color: #64748b;
            border: 1px solid #1e293b; padding: .25rem .65rem; border-radius: 20px;
        }

        /* ── Hero ────────────────────────────────────────────────────────── */
        .hero {
            position: relative;
            height: 300px;
            margin-top: 49px; /* topbar height */
            background: linear-gradient(135deg, #0f172a, #1e3a5f);
            overflow: hidden;
        }
        .hero-photo {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            opacity: .75;
        }
        .hero-placeholder {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,.07);
        }
        .hero-placeholder svg { width: 140px; height: 140px; }
        .hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(15,23,42,1) 0%, rgba(15,23,42,.6) 40%, rgba(15,23,42,.15) 100%);
        }
        .hero-content {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 1.25rem 1.25rem .25rem;
        }
        .hero-plate {
            display: inline-block;
            font-size: .72rem; font-weight: 700; letter-spacing: .12em;
            color: #94a3b8; text-transform: uppercase;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            padding: .2rem .65rem; border-radius: 4px;
            margin-bottom: .5rem;
        }
        .hero-name {
            font-size: 1.75rem; font-weight: 800;
            color: #f1f5f9; line-height: 1.15;
            margin-bottom: .25rem;
        }
        .hero-meta {
            font-size: .8rem; color: #64748b;
        }

        /* ── Main card ───────────────────────────────────────────────────── */
        .scan-main {
            position: relative;
            background: #f8fafc;
            border-radius: 24px 24px 0 0;
            margin-top: -20px;
            padding: 1.5rem 1.25rem 2rem;
            min-height: calc(100vh - 329px);
        }

        /* ── Status badge ────────────────────────────────────────────────── */
        .status-badge {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .7rem 1.5rem;
            border-radius: 12px;
            border: 1.5px solid var(--sborder);
            background: var(--sbg);
            color: var(--scolor);
            font-size: .95rem; font-weight: 700; letter-spacing: .04em;
            margin-bottom: 1.5rem;
        }
        .status-dot {
            width: 9px; height: 9px; border-radius: 50%;
            background: var(--sdot);
            flex-shrink: 0;
        }
        .status-dot.pulse {
            box-shadow: 0 0 0 0 var(--sdot);
            animation: statusPulse 2s infinite;
        }
        @keyframes statusPulse {
            0%   { box-shadow: 0 0 0 0 var(--sdot); }
            70%  { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }

        /* ── Info fields ─────────────────────────────────────────────────── */
        .section-label {
            font-size: .65rem; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; color: #94a3b8;
            margin-bottom: .75rem;
        }
        .fields-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .field-row {
            display: flex; align-items: center;
            padding: .85rem 1rem;
            gap: .75rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .field-row:last-child { border-bottom: none; }
        .field-icon-wrap {
            width: 34px; height: 34px; border-radius: 10px;
            background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            color: #475569;
        }
        .field-icon-wrap i { width: 16px; height: 16px; }
        .field-text { flex: 1; }
        .field-label { font-size: .72rem; color: #94a3b8; margin-bottom: 1px; }
        .field-value { font-size: .9rem; font-weight: 600; color: #0f172a; }

        /* ── Staff section ───────────────────────────────────────────────── */
        .staff-card {
            background: linear-gradient(135deg, #eff6ff, #f0f9ff);
            border: 1.5px solid #bfdbfe;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(59,130,246,.08);
        }
        .staff-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 1rem .65rem;
            border-bottom: 1px solid #bfdbfe;
            background: rgba(59,130,246,.06);
        }
        .staff-header-left {
            display: flex; align-items: center; gap: .5rem;
            font-size: .78rem; font-weight: 700; color: #1e40af;
        }
        .staff-header-left i { width: 15px; height: 15px; }
        .staff-badge {
            font-size: .65rem; font-weight: 700; letter-spacing: .06em;
            color: #2563eb; background: #dbeafe;
            border-radius: 20px; padding: .15rem .55rem; text-transform: uppercase;
        }
        .staff-field {
            display: flex; align-items: center; gap: .75rem;
            padding: .8rem 1rem;
            border-bottom: 1px solid rgba(191,219,254,.5);
        }
        .staff-field:last-child { border-bottom: none; }
        .staff-field-icon { width: 30px; height: 30px; border-radius: 8px; background: #dbeafe; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #2563eb; }
        .staff-field-icon i { width: 14px; height: 14px; }
        .staff-field-label { font-size: .72rem; color: #64748b; margin-bottom: 1px; }
        .staff-field-value { font-size: .88rem; font-weight: 600; color: #1e293b; }

        /* Compliance indicators */
        .compliance-pills { display: flex; gap: .4rem; flex-wrap: wrap; margin-top: .25rem; }
        .compliance-pill { font-size: .72rem; font-weight: 700; padding: .2rem .55rem; border-radius: 20px; }
        .pill-expired { background: #fee2e2; color: #991b1b; }
        .pill-expiring { background: #fef3c7; color: #92400e; }
        .pill-ok { background: #dcfce7; color: #15803d; }

        /* ── Login nudge ─────────────────────────────────────────────────── */
        .login-nudge {
            display: flex; align-items: center; gap: .75rem;
            padding: .85rem 1rem;
            background: #fff7ed; border: 1px solid #fed7aa;
            border-radius: 12px; margin-bottom: 1rem;
            text-decoration: none; color: inherit;
            transition: background .2s;
        }
        .login-nudge:hover { background: #ffedd5; }
        .login-nudge i { width: 18px; height: 18px; color: #ea580c; flex-shrink: 0; }
        .login-nudge-text { font-size: .82rem; color: #9a3412; line-height: 1.4; }
        .login-nudge-text strong { color: #c2410c; }

        /* ── Footer ──────────────────────────────────────────────────────── */
        .scan-footer {
            margin-top: 1.25rem;
            display: flex; flex-direction: column; align-items: center; gap: .5rem;
            padding: 0 .5rem 1rem;
        }
        .scan-time {
            font-size: .72rem; color: #94a3b8;
            display: flex; align-items: center; gap: .3rem;
        }
        .scan-time i { width: 12px; height: 12px; }
        .admin-link {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .78rem; font-weight: 600; color: #3b82f6;
            text-decoration: none; padding: .35rem .9rem;
            border: 1px solid #bfdbfe; border-radius: 20px;
            background: #eff6ff; transition: all .2s;
        }
        .admin-link:hover { background: #dbeafe; }
        .admin-link i { width: 13px; height: 13px; }

        /* ── Responsive max-width ──────────────────────────────────────────── */
        @media (min-width: 480px) {
            .hero { height: 340px; }
            .hero-name { font-size: 2rem; }
            .scan-main { border-radius: 28px 28px 0 0; }
        }
        @media (min-width: 640px) {
            .hero, .scan-topbar, .scan-main { max-width: 540px; margin-left: auto; margin-right: auto; }
            .scan-topbar { border-radius: 0 0 12px 12px; }
            body { background: #070d1a; }
        }
    </style>
</head>
<body>

<!-- ── Top navigation bar ──────────────────────────────────────────────────── -->
<div class="scan-topbar">
    <div class="topbar-brand">
        <i data-lucide="hexagon"></i>
        GCR Fleet
    </div>
    <div class="topbar-pill">Vehicle Info</div>
</div>

<!-- ── Hero section ───────────────────────────────────────────────────────── -->
<div class="hero">
    <?php if ($photoUrl): ?>
        <img class="hero-photo" src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($fullName) ?>">
    <?php else: ?>
        <div class="hero-placeholder">
            <i data-lucide="car" style="width:140px;height:140px;"></i>
        </div>
    <?php endif; ?>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-plate"><?= htmlspecialchars($vehicle['plate_number']) ?></div>
        <h1 class="hero-name"><?= htmlspecialchars($fullName) ?></h1>
        <div class="hero-meta">
            <?= htmlspecialchars($vehicle['category_name']) ?> &nbsp;·&nbsp;
            <?= htmlspecialchars($vehicle['year_model']) ?> &nbsp;·&nbsp;
            <?= htmlspecialchars(ucfirst($vehicle['color'])) ?>
        </div>
    </div>
</div>

<!-- ── Main card ──────────────────────────────────────────────────────────── -->
<div class="scan-main">

    <!-- Status badge -->
    <div class="status-badge" style="
        --sbg: <?= $sc['bg'] ?>;
        --scolor: <?= $sc['color'] ?>;
        --sborder: <?= $sc['border'] ?>;
        --sdot: <?= $sc['dot'] ?>;">
        <span class="status-dot <?= $sc['pulse'] ? 'pulse' : '' ?>"></span>
        <?= htmlspecialchars($sc['label']) ?>
    </div>

    <!-- Core vehicle info -->
    <div class="section-label">Vehicle Details</div>
    <div class="fields-card">
        <div class="field-row">
            <div class="field-icon-wrap"><i data-lucide="map-pin"></i></div>
            <div class="field-text">
                <div class="field-label">Current Location</div>
                <div class="field-value"><?= htmlspecialchars($location) ?></div>
            </div>
        </div>
        <div class="field-row">
            <div class="field-icon-wrap"><i data-lucide="fuel"></i></div>
            <div class="field-text">
                <div class="field-label">Fuel Type</div>
                <div class="field-value"><?= htmlspecialchars(ucfirst($vehicle['fuel_type'])) ?></div>
            </div>
        </div>
        <div class="field-row">
            <div class="field-icon-wrap"><i data-lucide="settings-2"></i></div>
            <div class="field-text">
                <div class="field-label">Transmission</div>
                <div class="field-value"><?= htmlspecialchars(ucfirst($vehicle['transmission'])) ?></div>
            </div>
        </div>
        <div class="field-row">
            <div class="field-icon-wrap"><i data-lucide="users"></i></div>
            <div class="field-text">
                <div class="field-label">Seating Capacity</div>
                <div class="field-value"><?= (int)$vehicle['seating_capacity'] ?> Passengers</div>
            </div>
        </div>
        <div class="field-row">
            <div class="field-icon-wrap"><i data-lucide="hash"></i></div>
            <div class="field-text">
                <div class="field-label">Vehicle ID</div>
                <div class="field-value"><?= htmlspecialchars($vehicle['vehicle_id']) ?></div>
            </div>
        </div>
    </div>

    <?php if ($isStaffView): ?>
    <!-- ── Staff section ──────────────────────────────────────────────────── -->
    <div class="section-label" style="margin-top:.25rem;">Service Information</div>
    <div class="staff-card">
        <div class="staff-header">
            <div class="staff-header-left">
                <i data-lucide="wrench"></i>
                Staff View
                <?php if ($staffName): ?>
                    &mdash; <?= htmlspecialchars($staffName) ?>
                <?php endif; ?>
            </div>
            <span class="staff-badge"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $staffRole))) ?></span>
        </div>

        <!-- Mileage -->
        <div class="staff-field">
            <div class="staff-field-icon"><i data-lucide="gauge"></i></div>
            <div>
                <div class="staff-field-label">Odometer Reading</div>
                <div class="staff-field-value"><?= number_format((int)$vehicle['mileage']) ?> km</div>
            </div>
        </div>

        <!-- Last maintenance -->
        <div class="staff-field">
            <div class="staff-field-icon"><i data-lucide="calendar-check"></i></div>
            <div>
                <div class="staff-field-label">Last Service Date</div>
                <div class="staff-field-value">
                    <?php if (!empty($lastMaintenance['last_date'])): ?>
                        <?= date('F j, Y', strtotime($lastMaintenance['last_date'])) ?>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-weight:400;">No service logged yet</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Next maintenance -->
        <div class="staff-field">
            <div class="staff-field-icon"><i data-lucide="calendar-clock"></i></div>
            <div>
                <div class="staff-field-label">Next Service Due</div>
                <div class="staff-field-value">
                    <?php if (!empty($nextMaintenance['next_due_date'])): ?>
                        <?php
                        $daysUntil = (int)ceil((strtotime($nextMaintenance['next_due_date']) - time()) / 86400);
                        $svcLabel  = $serviceTypeLabels[$nextMaintenance['service_type']] ?? ucfirst($nextMaintenance['service_type']);
                        $dateLabel = date('F j, Y', strtotime($nextMaintenance['next_due_date']));
                        $urgencyColor = $daysUntil <= 7 ? '#ef4444' : ($daysUntil <= 30 ? '#f59e0b' : '#15803d');
                        ?>
                        <?= htmlspecialchars($svcLabel) ?> &mdash;
                        <span style="color:<?= $urgencyColor ?>;font-weight:700;">
                            <?= $dateLabel ?>
                            <?php if ($daysUntil <= 30): ?>
                                (<?= $daysUntil ?>d)
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-weight:400;">No upcoming schedule</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Compliance -->
        <div class="staff-field">
            <div class="staff-field-icon"><i data-lucide="shield-check"></i></div>
            <div>
                <div class="staff-field-label">Compliance Status</div>
                <div class="staff-field-value">
                    <?php
                    $expired  = (int)($complianceSummary['expired_count']  ?? 0);
                    $expiring = (int)($complianceSummary['expiring_count'] ?? 0);
                    ?>
                    <div class="compliance-pills">
                        <?php if ($expired > 0): ?>
                            <span class="compliance-pill pill-expired">⚠ <?= $expired ?> Expired</span>
                        <?php endif; ?>
                        <?php if ($expiring > 0): ?>
                            <span class="compliance-pill pill-expiring">⏳ <?= $expiring ?> Expiring</span>
                        <?php endif; ?>
                        <?php if ($expired === 0 && $expiring === 0): ?>
                            <span class="compliance-pill pill-ok">✓ All Valid</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Login nudge for non-authenticated users ────────────────────────── -->
    <a href="<?= htmlspecialchars($loginUrl) ?>" class="login-nudge">
        <i data-lucide="lock"></i>
        <div class="login-nudge-text">
            <strong>Staff?</strong> Log in to view mileage, service history, and compliance status.
        </div>
    </a>
    <?php endif; ?>

    <!-- ── Footer ─────────────────────────────────────────────────────────── -->
    <div class="scan-footer">
        <div class="scan-time">
            <i data-lucide="clock"></i>
            Scanned <?= $scanTime ?>
        </div>
        <?php if ($showAdminLink): ?>
        <a href="<?= htmlspecialchars($adminUrl) ?>" class="admin-link">
            <i data-lucide="external-link"></i>
            Open Full Admin Profile
        </a>
        <?php endif; ?>
    </div>
</div>

<script src="<?= $assetsUrl ?>js/lucide.min.js"></script>
<script>
    lucide.createIcons();
    // Refresh the page if the user backgrounds the device for more than 2 minutes
    // so the status badge always reflects the live state.
    var _lastVisible = Date.now();
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) { _lastVisible = Date.now(); }
        else if (Date.now() - _lastVisible > 120000) { location.reload(); }
    });
</script>
</body>
</html>
