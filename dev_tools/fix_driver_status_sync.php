<?php
/**
 * One-time Driver Status Sync Repair Tool
 * dev_tools/fix_driver_status_sync.php
 *
 * Fixes drivers stuck as 'on_duty' with no corresponding active chauffeur rental,
 * and ensures drivers WITH active chauffeur rentals are correctly marked 'on_duty'.
 */
define('IN_APP', true);
require_once '../config/config.php';

$db = Database::getInstance();
$results = [];

// ─── 1. Reset orphaned on_duty drivers ───────────────────────────────────────
$stuckOnDuty = $db->fetchAll(
    "SELECT d.driver_id, d.employee_code,
            CONCAT(d.first_name, ' ', d.last_name) AS full_name,
            d.status
     FROM drivers d
     WHERE d.deleted_at IS NULL
       AND d.status = 'on_duty'
       AND d.driver_id NOT IN (
           SELECT ra.driver_id
           FROM rental_agreements ra
           WHERE ra.driver_id IS NOT NULL
             AND ra.rental_type = 'chauffeur'
             AND ra.status = 'active'
       )"
);

$resetCount = 0;
foreach ($stuckOnDuty as $d) {
    $db->execute(
        "UPDATE drivers SET status = 'available', updated_at = NOW() WHERE driver_id = ?",
        [$d['driver_id']]
    );
    $results['reset'][] = $d;
    $resetCount++;
}

// ─── 2. Mark drivers on_duty if they have an active chauffeur rental ──────────
$shouldBeOnDuty = $db->fetchAll(
    "SELECT DISTINCT d.driver_id, d.employee_code,
            CONCAT(d.first_name, ' ', d.last_name) AS full_name,
            d.status
     FROM drivers d
     JOIN rental_agreements ra
          ON ra.driver_id = d.driver_id
         AND ra.rental_type = 'chauffeur'
         AND ra.status = 'active'
     WHERE d.deleted_at IS NULL
       AND d.status != 'on_duty'"
);

$promotedCount = 0;
foreach ($shouldBeOnDuty as $d) {
    $db->execute(
        "UPDATE drivers SET status = 'on_duty', updated_at = NOW() WHERE driver_id = ?",
        [$d['driver_id']]
    );
    $results['promoted'][] = $d;
    $promotedCount++;
}

// ─── 3. Final state ───────────────────────────────────────────────────────────
$finalDrivers = $db->fetchAll(
    "SELECT d.driver_id, d.employee_code,
            CONCAT(d.first_name, ' ', d.last_name) AS full_name,
            d.status,
            COUNT(ra.agreement_id) AS active_chauffeur_rentals
     FROM drivers d
     LEFT JOIN rental_agreements ra
           ON ra.driver_id = d.driver_id
          AND ra.rental_type = 'chauffeur'
          AND ra.status = 'active'
     WHERE d.deleted_at IS NULL
     GROUP BY d.driver_id
     ORDER BY d.last_name"
);

$activeRentals = $db->fetchAll(
    "SELECT ra.agreement_number, ra.status, ra.rental_type,
            ra.driver_id,
            CONCAT(d.first_name,' ',d.last_name) AS driver_name
     FROM rental_agreements ra
     LEFT JOIN drivers d ON ra.driver_id = d.driver_id
     WHERE ra.status IN ('reserved','confirmed','active')
     ORDER BY ra.created_at DESC
     LIMIT 30"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Driver Status Sync Repair</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 860px; margin: 2rem auto; padding: 0 1rem; color: #1e293b; }
        h1 { color: #0f172a; }
        h2 { margin-top: 2rem; font-size: 1rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: .5rem; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
        .badge { display:inline-block; padding: 2px 10px; border-radius: 999px; font-size: .8rem; font-weight: 600; }
        .badge-success { background:#dcfce7; color:#166534; }
        .badge-primary { background:#dbeafe; color:#1d4ed8; }
        .badge-secondary { background:#f1f5f9; color:#475569; }
        .badge-danger { background:#fee2e2; color:#991b1b; }
        table { width:100%; border-collapse: collapse; font-size: .875rem; }
        th { text-align:left; padding: 8px 12px; background:#f1f5f9; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
        td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        .ok { color: #16a34a; font-weight: 600; }
        .warn { color: #d97706; font-weight: 600; }
        .actions { margin-top: 2rem; display:flex; gap: 1rem; flex-wrap:wrap; }
        .btn { display:inline-block; padding: .5rem 1.25rem; border-radius:6px; text-decoration:none; font-weight:600; font-size:.875rem; }
        .btn-primary { background:#3b82f6; color:#fff; }
        .btn-secondary { background:#e2e8f0; color:#334155; }
        .alert { padding: .75rem 1rem; border-radius:6px; margin-bottom:1rem; font-weight:500; }
        .alert-success { background:#dcfce7; border:1px solid #bbf7d0; color:#15803d; }
        .alert-warning { background:#fef9c3; border:1px solid #fde047; color:#854d0e; }
        .alert-info { background:#dbeafe; border:1px solid #bfdbfe; color:#1e40af; }
    </style>
</head>
<body>
<h1>🔧 Driver Status Sync Repair</h1>

<?php if ($resetCount === 0 && $promotedCount === 0): ?>
    <div class="alert alert-success">✅ No issues found — all driver statuses are already in sync with rental records.</div>
<?php else: ?>
    <?php if ($resetCount > 0): ?>
        <div class="alert alert-warning">⚠️ Fixed <strong><?= $resetCount ?></strong> orphaned "On Duty" driver(s) — reset to <strong>Available</strong>.</div>
    <?php endif; ?>
    <?php if ($promotedCount > 0): ?>
        <div class="alert alert-info">ℹ️ Corrected <strong><?= $promotedCount ?></strong> driver(s) with active chauffeur rentals → set to <strong>On Duty</strong>.</div>
    <?php endif; ?>
<?php endif; ?>

<h2>Step 1 — Orphaned "On Duty" Drivers Reset</h2>
<?php if (empty($results['reset'])): ?>
    <p class="ok">✓ None — no orphaned on_duty drivers found.</p>
<?php else: ?>
    <div class="card">
        <table>
            <thead><tr><th>Code</th><th>Name</th><th>Was</th><th>Now</th></tr></thead>
            <tbody>
                <?php foreach ($results['reset'] as $d): ?>
                <tr>
                    <td><code><?= htmlspecialchars($d['employee_code']) ?></code></td>
                    <td><?= htmlspecialchars($d['full_name']) ?></td>
                    <td><span class="badge badge-primary">On Duty</span></td>
                    <td><span class="badge badge-success">Available</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>Step 2 — Drivers Promoted to "On Duty" (Had Active Chauffeur Rental)</h2>
<?php if (empty($results['promoted'])): ?>
    <p class="ok">✓ None — all chauffeur-assigned drivers were already correctly marked.</p>
<?php else: ?>
    <div class="card">
        <table>
            <thead><tr><th>Code</th><th>Name</th><th>Was</th><th>Now</th></tr></thead>
            <tbody>
                <?php foreach ($results['promoted'] as $d): ?>
                <tr>
                    <td><code><?= htmlspecialchars($d['employee_code']) ?></code></td>
                    <td><?= htmlspecialchars($d['full_name']) ?></td>
                    <td><span class="badge badge-secondary"><?= htmlspecialchars($d['status']) ?></span></td>
                    <td><span class="badge badge-primary">On Duty</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>Final Driver Status State</h2>
<div class="card">
    <table>
        <thead><tr><th>Code</th><th>Name</th><th>Status</th><th>Active Chauffeur Rentals</th><th>Sync OK?</th></tr></thead>
        <tbody>
            <?php foreach ($finalDrivers as $d):
                $synced = ($d['status'] === 'on_duty') === ($d['active_chauffeur_rentals'] > 0);
                $statusMap = [
                    'available'  => 'badge-success',
                    'on_duty'    => 'badge-primary',
                    'off_duty'   => 'badge-secondary',
                    'suspended'  => 'badge-danger',
                ];
                $bc = $statusMap[$d['status']] ?? 'badge-secondary';
            ?>
            <tr>
                <td><code><?= htmlspecialchars($d['employee_code']) ?></code></td>
                <td><?= htmlspecialchars($d['full_name']) ?></td>
                <td><span class="badge <?= $bc ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$d['status']))) ?></span></td>
                <td style="text-align:center;"><?= (int)$d['active_chauffeur_rentals'] ?></td>
                <td><?= $synced ? '<span class="ok">✓</span>' : '<span class="warn">✗ Mismatch</span>' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h2>Active / Pending Rentals</h2>
<?php if (empty($activeRentals)): ?>
    <p style="color:#64748b;">No active, reserved, or confirmed rentals found.</p>
<?php else: ?>
    <div class="card">
        <table>
            <thead><tr><th>Agreement #</th><th>Status</th><th>Type</th><th>Assigned Driver</th></tr></thead>
            <tbody>
                <?php foreach ($activeRentals as $ra):
                    $statusBadges = [
                        'reserved'  => 'badge-secondary',
                        'confirmed' => 'badge-secondary',
                        'active'    => 'badge-success',
                    ];
                    $bc = $statusBadges[$ra['status']] ?? 'badge-secondary';
                ?>
                <tr>
                    <td style="font-weight:700;color:#3b82f6;font-family:monospace;"><?= htmlspecialchars($ra['agreement_number']) ?></td>
                    <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($ra['status']) ?></span></td>
                    <td><?= htmlspecialchars($ra['rental_type'] === 'chauffeur' ? '🧑 Chauffeur' : '🚗 Self-Drive') ?></td>
                    <td><?= $ra['driver_id'] ? htmlspecialchars($ra['driver_name']) : '<span style="color:#94a3b8">—</span>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="actions">
    <a href="../modules/drivers/index.php" class="btn btn-primary">→ Drivers Module</a>
    <a href="../modules/rentals/index.php" class="btn btn-primary">→ Rentals Module</a>
    <a href="fix_driver_status_sync.php" class="btn btn-secondary">↺ Run Again</a>
</div>
</body>
</html>
