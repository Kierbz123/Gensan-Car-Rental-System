<?php
/**
 * Vehicle Compliance Profile
 * Path: modules/compliance/vehicle-compliance.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('compliance.view');
$db = Database::getInstance();
$vehicleId = trim($_GET['vehicle_id'] ?? '');
if (empty($vehicleId)) {
    redirect('modules/compliance/', 'Vehicle ID missing', 'error');
}

$vehicle = $db->fetchOne("SELECT * FROM vehicles WHERE vehicle_id=? AND deleted_at IS NULL", [$vehicleId]);
if (!$vehicle) {
    redirect('modules/compliance/', 'Vehicle not found', 'error');
}

$records = $db->fetchAll(
    "SELECT cr.*, CONCAT(u.first_name,' ',u.last_name) AS created_by_name
     FROM compliance_records cr
     LEFT JOIN users u ON cr.created_by = u.user_id
     WHERE cr.vehicle_id = ? ORDER BY cr.compliance_type ASC, cr.expiry_date DESC",
    [$vehicleId]
);

$today = date('Y-m-d');
$pageTitle = 'Compliance — ' . $vehicle['plate_number'];
require_once '../../includes/header.php';

// Group records: latest per type, show all in secondary
$latestByType = [];
foreach ($records as $r) {
    if (!isset($latestByType[$r['compliance_type']])) {
        $latestByType[$r['compliance_type']] = $r;
    }
}

$complianceLabels = [
    'lto_registration' => ['label' => 'LTO Registration', 'icon' => 'file-badge'],
    'insurance_comprehensive' => ['label' => 'Comprehensive Insurance', 'icon' => 'shield'],
    'insurance_tpl' => ['label' => 'TPL Insurance', 'icon' => 'shield-check'],
    'emission_test' => ['label' => 'Emission Test', 'icon' => 'wind'],
    'franchise_ltfrb' => ['label' => 'LTFRB Franchise', 'icon' => 'award'],
    'pnp_clearance' => ['label' => 'PNP Clearance', 'icon' => 'badge-check'],
    'mayors_permit' => ['label' => "Mayor's Permit", 'icon' => 'landmark'],
];

// Stats
$totalRecords = count($latestByType);
$breached = 0;
$expiring = 0;
$valid = 0;
foreach ($latestByType as $r) {
    $d = ceil((strtotime($r['expiry_date']) - time()) / 86400);
    if ($d < 0)
        $breached++;
    elseif ($d <= 30)
        $expiring++;
    else
        $valid++;
}
?>

<div class="fade-in">
    <!-- Breadcrumb -->
    <div
        style="display:flex; align-items:center; gap:8px; margin-bottom:1.5rem; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted);">
        <a href="index.php"
            style="color:var(--text-muted); text-decoration:none; display:flex; align-items:center; gap:4px; transition:color 0.15s;"
            onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-muted)'">
            <i data-lucide="arrow-left" style="width:13px;height:13px;"></i> Compliance
        </a>
        <span>/</span>
        <span style="color:var(--text-main);"><?= htmlspecialchars($vehicle['plate_number']) ?></span>
    </div>

    <!-- Hero header -->
    <div class="card"
        style="background:linear-gradient(135deg,#1e293b 0%,#334155 100%); border:none; margin-bottom:1.5rem; overflow:hidden; position:relative;">
        <i data-lucide="shield"
            style="position:absolute;right:-24px;bottom:-24px;width:140px;height:140px;color:rgba(255,255,255,0.04);"></i>
        <div class="card-body" style="position:relative; z-index:1;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
                <div>
                    <div
                        style="font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:rgba(148,163,184,0.9); margin-bottom:6px;">
                        <?= htmlspecialchars($vehicle['plate_number']) ?> · Compliance Profile
                    </div>
                    <h1 style="margin:0 0 6px 0; font-size:1.5rem; font-weight:900; color:#fff; line-height:1.2;">
                        <?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' ' . $vehicle['year_model']) ?>
                    </h1>
                    <div style="font-size:0.8125rem; color:rgba(148,163,184,0.85); font-weight:500;">
                        <?= count($records) ?> instrument(s) archived · <?= $totalRecords ?> type(s) tracked
                    </div>
                </div>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
                    <!-- Stat pills -->
                    <?php if ($breached > 0): ?>
                        <div
                            style="background:rgba(239,68,68,0.2); border:1px solid rgba(239,68,68,0.4); border-radius:99px; padding:5px 14px; font-size:0.75rem; font-weight:800; color:#fca5a5; display:flex; align-items:center; gap:6px;">
                            <i data-lucide="shield-x" style="width:13px;height:13px;"></i> <?= $breached ?> BREACHED
                        </div>
                    <?php endif; ?>
                    <?php if ($expiring > 0): ?>
                        <div
                            style="background:rgba(245,158,11,0.2); border:1px solid rgba(245,158,11,0.4); border-radius:99px; padding:5px 14px; font-size:0.75rem; font-weight:800; color:#fcd34d; display:flex; align-items:center; gap:6px;">
                            <i data-lucide="timer" style="width:13px;height:13px;"></i> <?= $expiring ?> EXPIRING
                        </div>
                    <?php endif; ?>
                    <?php if ($valid > 0): ?>
                        <div
                            style="background:rgba(34,197,94,0.2); border:1px solid rgba(34,197,94,0.4); border-radius:99px; padding:5px 14px; font-size:0.75rem; font-weight:800; color:#86efac; display:flex; align-items:center; gap:6px;">
                            <i data-lucide="shield-check" style="width:13px;height:13px;"></i> <?= $valid ?> VALID
                        </div>
                    <?php endif; ?>
                    <?php if ($authUser->hasPermission('compliance.create')): ?>
                        <a href="renew-upload.php?vehicle_id=<?= urlencode($vehicleId) ?>" class="btn btn-primary btn-sm"
                            style="font-size:0.8125rem; font-weight:700; display:flex; align-items:center; gap:6px;">
                            <i data-lucide="file-plus" style="width:14px;height:14px;"></i> Archive Instrument
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($latestByType)): ?>
        <div class="card" style="text-align:center; padding:4rem 2rem;">
            <i data-lucide="shield-off"
                style="width:48px;height:48px;color:var(--text-muted);opacity:0.4;margin:0 auto 1rem;display:block;"></i>
            <h2 style="font-weight:800; margin:0 0 8px 0; color:var(--text-main);">No Compliance Records</h2>
            <p style="color:var(--text-muted); font-size:0.875rem; margin:0 0 1.5rem 0;">No instruments have been archived
                for this vehicle.</p>
            <?php if ($authUser->hasPermission('compliance.create')): ?>
                <a href="renew-upload.php?vehicle_id=<?= urlencode($vehicleId) ?>" class="btn btn-primary">
                    <i data-lucide="file-plus" style="width:15px;height:15px;"></i> Archive First Instrument
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Current status: latest per type -->
        <h2
            style="font-size:0.8rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); margin:0 0 0.75rem 0;">
            Current Status by Type</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:1rem; margin-bottom:2rem;">
            <?php foreach ($latestByType as $type => $r):
                $daysLeft = ceil((strtotime($r['expiry_date']) - time()) / 86400);
                $isExpired = $daysLeft < 0;
                $isWarning = !$isExpired && $daysLeft <= 30;

                $borderColor = $isExpired ? 'var(--danger)' : ($isWarning ? 'var(--warning)' : 'var(--success)');
                $bgColor = $isExpired ? 'var(--danger-50,#fef2f2)' : ($isWarning ? 'var(--warning-50,#fffbeb)' : 'var(--bg-surface)');
                $badgeColor = $isExpired ? 'danger' : ($isWarning ? 'warning' : 'success');
                $statusLabel = $isExpired ? 'BREACHED' : ($isWarning ? 'EXPIRING' : 'VALID');

                $meta = $complianceLabels[$type] ?? ['label' => strtoupper(str_replace('_', ' ', $type)), 'icon' => 'file'];
                ?>
                <a href="instrument-view.php?id=<?= $r['record_id'] ?>"
                    style="display:block; background:<?= $bgColor ?>; border:1px solid <?= $borderColor ?>; border-left:4px solid <?= $borderColor ?>; border-radius:var(--radius-lg); padding:1.125rem 1.25rem; text-decoration:none; transition:box-shadow 0.15s, filter 0.15s;"
                    onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)';this.style.filter='brightness(0.97)'"
                    onmouseout="this.style.boxShadow='';this.style.filter=''">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div
                                style="width:36px;height:36px;border-radius:50%;background:white;display:flex;align-items:center;justify-content:center;border:1px solid <?= $borderColor ?>;">
                                <i data-lucide="<?= htmlspecialchars($meta['icon']) ?>"
                                    style="width:17px;height:17px;color:<?= $borderColor ?>;"></i>
                            </div>
                            <div>
                                <div style="font-size:0.8125rem; font-weight:700; color:var(--text-main);">
                                    <?= htmlspecialchars($meta['label']) ?></div>
                                <?php if ($r['document_number']): ?>
                                    <div style="font-size:0.7rem; color:var(--text-muted); font-family:monospace; margin-top:1px;">
                                        #<?= htmlspecialchars($r['document_number']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge badge-<?= $badgeColor ?>"
                            style="font-size:0.65rem; font-weight:800; letter-spacing:0.05em;"><?= $statusLabel ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                        <div>
                            <div
                                style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); margin-bottom:2px;">
                                Expires</div>
                            <div style="font-size:0.9375rem; font-weight:800; color:<?= $borderColor ?>;">
                                <?= date('M d, Y', strtotime($r['expiry_date'])) ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:1.5rem; font-weight:900; color:<?= $borderColor ?>; line-height:1;">
                                <?= abs($daysLeft) ?></div>
                            <div style="font-size:0.65rem; font-weight:700; text-transform:uppercase; color:var(--text-muted);">
                                <?= $isExpired ? 'days past' : 'days left' ?></div>
                        </div>
                    </div>
                    <?php if ($r['renewal_cost']): ?>
                        <div
                            style="margin-top:8px; padding-top:8px; border-top:1px solid <?= $isExpired ? 'rgba(239,68,68,0.2)' : ($isWarning ? 'rgba(245,158,11,0.2)' : 'var(--border-color)') ?>; font-size:0.75rem; color:var(--text-muted);">
                            Last renewal cost: <strong
                                style="color:var(--text-main);">₱<?= number_format($r['renewal_cost'], 2) ?></strong>
                        </div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Full transaction history -->
        <h2
            style="font-size:0.8rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); margin:0 0 0.75rem 0;">
            Full Transaction History</h2>
        <div class="card">
            <div class="table-container" style="border:none;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="background:var(--bg-muted); border-bottom:1px solid var(--border-color);">
                        <tr>
                            <th
                                style="padding:10px 14px; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; text-align:left;">
                                Instrument Type</th>
                            <th
                                style="padding:10px 14px; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; text-align:left;">
                                Doc #</th>
                            <th
                                style="padding:10px 14px; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; text-align:left;">
                                Issued</th>
                            <th
                                style="padding:10px 14px; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; text-align:left;">
                                Expires</th>
                            <th
                                style="padding:10px 14px; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; text-align:left;">
                                Cost</th>
                            <th
                                style="padding:10px 14px; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; text-align:left;">
                                Status</th>
                            <th
                                style="padding:10px 14px; font-size:0.7rem; text-transform:uppercase; color:var(--text-muted); font-weight:700; text-align:center;">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r):
                            $daysLeft = ceil((strtotime($r['expiry_date']) - time()) / 86400);
                            $isExpired = $daysLeft < 0;
                            $isWarning = !$isExpired && $daysLeft <= 30;
                            $badgeClass = $isExpired ? 'badge-danger' : ($isWarning ? 'badge-warning' : 'badge-success');
                            $statusLabel = $isExpired ? 'BREACHED' : ($isWarning ? 'EXPIRING' : 'VALID');
                            $meta = $complianceLabels[$r['compliance_type']] ?? ['label' => ucwords(str_replace('_', ' ', $r['compliance_type'])), 'icon' => 'file'];
                            $isLatest = isset($latestByType[$r['compliance_type']]) && $latestByType[$r['compliance_type']]['record_id'] === $r['record_id'];
                            ?>
                            <tr style="border-bottom:1px solid var(--border-color);"
                                onmouseover="this.style.background='var(--bg-muted)'" onmouseout="this.style.background=''">
                                <td style="padding:10px 14px;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <i data-lucide="<?= htmlspecialchars($meta['icon']) ?>"
                                            style="width:14px;height:14px;color:var(--text-muted);flex-shrink:0;"></i>
                                        <div>
                                            <div style="font-size:0.8125rem; font-weight:700; color:var(--text-main);">
                                                <?= htmlspecialchars($meta['label']) ?></div>
                                            <?php if ($isLatest): ?>
                                                <span
                                                    style="font-size:0.6rem; background:var(--accent); color:#fff; padding:1px 5px; border-radius:3px; font-weight:700;">CURRENT</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    style="padding:10px 14px; font-family:monospace; font-size:0.8rem; color:var(--text-secondary); font-weight:600;">
                                    <?= htmlspecialchars($r['document_number'] ?? '—') ?>
                                </td>
                                <td style="padding:10px 14px; font-size:0.8rem; color:var(--text-secondary); font-weight:600;">
                                    <?= $r['issue_date'] ? date('M d, Y', strtotime($r['issue_date'])) : '—' ?>
                                </td>
                                <td
                                    style="padding:10px 14px; font-size:0.8rem; font-weight:700; color:<?= $isExpired ? 'var(--danger)' : ($isWarning ? 'var(--warning)' : 'var(--text-main)') ?>">
                                    <?= date('M d, Y', strtotime($r['expiry_date'])) ?>
                                    <div style="font-size:0.7rem; color:var(--text-muted); font-weight:500;">
                                        <?= $isExpired ? abs($daysLeft) . ' days lapsed' : $daysLeft . ' days left' ?>
                                    </div>
                                </td>
                                <td style="padding:10px 14px; font-size:0.8rem; font-weight:700; color:var(--text-main);">
                                    <?= $r['renewal_cost'] ? '₱' . number_format($r['renewal_cost'], 2) : '—' ?>
                                </td>
                                <td style="padding:10px 14px;">
                                    <span class="badge <?= $badgeClass ?>"
                                        style="font-size:0.65rem; font-weight:800;"><?= $statusLabel ?></span>
                                </td>
                                <td style="padding:10px 14px; text-align:center;">
                                    <div style="display:flex; justify-content:center; gap:4px;">
                                        <a href="instrument-view.php?id=<?= $r['record_id'] ?>" class="btn btn-ghost btn-sm"
                                            title="View Record">
                                            <i data-lucide="eye" style="width:13px;height:13px;"></i>
                                        </a>
                                        <a href="generate-pdf.php?id=<?= $r['record_id'] ?>" target="_blank"
                                            class="btn btn-ghost btn-sm" title="Generate PDF">
                                            <i data-lucide="file-text" style="width:13px;height:13px;"></i>
                                        </a>
                                        <?php if ($r['document_file_path']): ?>
                                            <a href="<?= BASE_URL . ltrim($r['document_file_path'], '/') ?>" target="_blank"
                                                class="btn btn-ghost btn-sm" title="View Uploaded Document">
                                                <i data-lucide="download" style="width:13px;height:13px;"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($authUser->hasPermission('compliance.create') && $isLatest): ?>
                                            <a href="renew-upload.php?vehicle_id=<?= urlencode($vehicleId) ?>&type=<?= urlencode($r['compliance_type']) ?>"
                                                class="btn btn-<?= $isExpired ? 'danger' : 'warning' ?> btn-sm" title="Renew"
                                                style="font-size:0.7rem;">
                                                Renew
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>