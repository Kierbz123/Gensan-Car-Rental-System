<?php
// modules/drivers/driver-view.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('drivers.view');

$driverId = (int)($_GET['id'] ?? 0);
if (!$driverId) {
    redirect('modules/drivers/', 'Driver ID missing', 'error');
}

$driverObj = new Driver();
$d = $driverObj->getById($driverId);
if (!$d) {
    redirect('modules/drivers/', 'Driver not found', 'error');
}

$history = $driverObj->getAssignmentHistory($driverId, 15);

// Check for active assignment if driver is on_duty
$activeAssignment = null;
if ($d['status'] === 'on_duty') {
    $db = Database::getInstance();
    $activeAssignment = $db->fetchOne("
        SELECT ra.*, c.first_name, c.last_name, v.brand, v.model, v.plate_number
        FROM rental_agreements ra
        JOIN customers c ON ra.customer_id = c.customer_id
        LEFT JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
        WHERE ra.driver_id = ? AND ra.status IN ('active', 'confirmed', 'reserved') 
        ORDER BY ra.created_at DESC LIMIT 1
    ", [$driverId]);
}

$pageTitle = htmlspecialchars($d['full_name']) . ' — Personnel Profile';
require_once '../../includes/header.php';

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Licensing Logic
$daysLeft = (int)round((strtotime($d['license_expiry']) - strtotime('today')) / 86400);
$isExpired = $daysLeft < 0;
$isWarning = $daysLeft >= 0 && $daysLeft <= 30;

$licBgStatus = $isExpired ? 'var(--danger-50)' : ($isWarning ? 'var(--warning-50)' : 'var(--secondary-50)');
$licBorderStatus = $isExpired ? 'var(--danger-200)' : ($isWarning ? 'var(--warning-200)' : 'var(--secondary-200)');
$licTextStatus = $isExpired ? 'var(--danger-700)' : ($isWarning ? 'var(--warning-700)' : 'var(--success-700)');

$STATUS = [
    'available' => 'success',
    'on_duty' => 'primary',
    'off_duty' => 'secondary',
    'suspended' => 'danger'
];

$statusColor = 'var(--secondary-500)';
if ($d['status'] === 'available') $statusColor = 'var(--success)';
if ($d['status'] === 'on_duty') $statusColor = 'var(--primary)';
if ($d['status'] === 'suspended') $statusColor = 'var(--danger)';

?>

<div class="fade-in max-w-7xl mx-auto">
    <!-- Breadcrumb / Header area -->
    <div class="flex items-center gap-3 mb-6 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Driver Directory
        </a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600">Personnel Profile</span>
    </div>

    <!-- Main Layout Grid -->
    <div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
        <!-- Avatar & Summary Card (Left Column) -->
        <div class="flex flex-col gap-6">
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <?php if (!empty($d['profile_photo_path'])): ?>
                        <div style="width:120px;height:120px;border-radius:50%;margin:0 auto var(--space-4);overflow:hidden;border:4px solid var(--primary-50); box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                            <img src="<?= BASE_URL . ltrim($d['profile_photo_path'], '/') ?>" style="width:100%;height:100%;object-fit:cover;" alt="Profile Photo">
                        </div>
                    <?php else: ?>
                        <div style="width:120px;height:120px;border-radius:50%;margin:0 auto var(--space-4);background:var(--primary-50);display:flex;align-items:center;justify-content:center;border:4px solid white;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                            <i data-lucide="user" style="width:48px;height:48px;color:var(--primary-400);"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h2 style="margin-bottom: var(--space-2); font-size: 1.5rem; line-height: 1.2;">
                        <?= htmlspecialchars($d['full_name']) ?>
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-4);">
                        <?= htmlspecialchars($d['employee_code']) ?>
                    </p>

                    <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: <?= $statusColor ?>; color: white; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">
                        <span style="width: 6px; height: 6px; background: white; border-radius: 50%;"></span>
                        <?= str_replace('_', ' ', $d['status']) ?>
                    </div>

                    <div style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
                        <p style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                            Contact Matrix</p>
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary); display:flex; align-items:center; gap:6px;"><i data-lucide="phone" style="width:14px;height:14px;"></i> Base Comm</span>
                            <strong style="font-family: monospace;"><?= htmlspecialchars($d['phone']) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary); display:flex; align-items:center; gap:6px;"><i data-lucide="mail" style="width:14px;height:14px;"></i> Comlink</span>
                            <strong style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px;" title="<?= htmlspecialchars($d['email'] ?? '—') ?>"><?= htmlspecialchars($d['email'] ?? '—') ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--text-secondary); display:flex; align-items:center; gap:6px;"><i data-lucide="calendar" style="width:14px;height:14px;"></i> Commissioned</span>
                            <strong><?= date('M d, Y', strtotime($d['created_at'])) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons Stack -->
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php if ($authUser->hasPermission('drivers.update')): ?>
                    <a href="driver-edit.php?id=<?= $driverId ?>" class="btn btn-primary"
                        style="justify-content: center;">
                        <i data-lucide="edit-3" class="w-4 h-4"></i> Modify Personnel Profile
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary"
                    style="justify-content: center;">
                    <i data-lucide="users" class="w-4 h-4"></i> Return to Directory
                </a>
            </div>
        </div>

        <!-- Detail Sections (Right Column) -->
        <div class="flex flex-col gap-6">

            <!-- Live Status Alert (Active Assignment) -->
            <?php if ($d['status'] === 'on_duty' && $activeAssignment): ?>
                <div class="card"
                    style="background: #1e3a8a; color: white; border: none; overflow: hidden; position: relative;">
                    <!-- Decorative icon background -->
                    <i data-lucide="route"
                        style="position: absolute; right: -20px; bottom: -20px; width: 160px; height: 160px; color: rgba(255,255,255,0.05); transform: rotate(-10deg);"></i>
                    
                    <div class="card-body" style="position: relative; z-index: 1;">
                        <h2 style="margin-bottom: var(--space-4); margin-top: 0; color: white; display: flex; align-items: center; gap: 8px; font-size: 1rem;">
                            <span style="background: rgba(255,255,255,0.2); padding: 6px; border-radius: 8px; display: inline-flex;"><i
                                    data-lucide="compass" style="width: 16px; height: 16px;"></i></span> Active Assignment Protocol
                        </h2>
                        <div class="grid"
                            style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-4);">
                            <div>
                                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: rgba(191,219,254,0.85); margin-bottom: 4px;">Contractor</label>
                                <p style="font-weight: bold; margin: 0; font-size: 1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars($activeAssignment['first_name'] . ' ' . $activeAssignment['last_name']) ?>
                                </p>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: rgba(191,219,254,0.85); margin-bottom: 4px;">Assigned Vector</label>
                                <p style="font-weight: bold; margin: 0; font-size: 1rem; font-family:monospace;">
                                    <?= htmlspecialchars($activeAssignment['plate_number'] ?? 'N/A') ?>
                                </p>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: rgba(191,219,254,0.85); margin-bottom: 4px;">Dispatch ETD</label>
                                <p style="font-weight: bold; margin: 0; font-size: 1rem;">
                                    <?= date('M d, H:i', strtotime($activeAssignment['rental_end_date'])) ?>
                                </p>
                            </div>
                        </div>
                        <div style="margin-top: var(--space-5); text-align: right;">
                            <a href="../rentals/view.php?id=<?= $activeAssignment['agreement_id'] ?>"
                                style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background: white; color: #1e3a8a; text-decoration: none; font-weight: bold; font-size: 0.75rem; text-transform: uppercase; border-radius: var(--radius-md);">
                                Inspect Logistics Agreement
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Licensing Credentials -->
            <div class="card">
                <div class="card-body">
                    <h2 style="margin-bottom: var(--space-4); margin-top: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="id-card" style="width:18px;height:18px;color:var(--primary);"></i> Licensing Credentials
                    </h2>
                    
                    <div style="background: <?= $licBgStatus ?>; border: 1px solid <?= $licBorderStatus ?>; border-radius: var(--radius-md); padding: var(--space-4); margin-bottom: var(--space-4); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: bold; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-main); margin-bottom: 4px;">
                                LTO License Expiry
                            </div>
                            <div style="font-weight: 800; font-size: 1.1em; color: var(--text-secondary);">
                                <?= date('M d, Y', strtotime($d['license_expiry'])) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                (<?= $daysLeft > 0 ? $daysLeft . ' days remaining' : ($daysLeft === 0 ? 'Expires today' : abs($daysLeft) . ' days ago') ?>)
                            </div>
                        </div>
                        <div style="font-weight: bold; font-size: 0.75em; text-transform: uppercase; color: <?= $licTextStatus ?>; padding: 6px 12px; background: white; border-radius: 4px; border: 1px solid <?= $licBorderStatus ?>;">
                            <?= $isExpired ? 'EXPIRED' : ($isWarning ? 'EXPIRING SOON' : 'VALID') ?>
                        </div>
                    </div>

                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4); background: var(--secondary-50); padding: var(--space-4); border-radius: var(--radius-md);">
                        <div>
                            <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">License Number</label>
                            <p style="font-weight: bold; margin: 0; font-family: monospace; font-size: 0.95rem; letter-spacing:0.05em;">
                                <?= htmlspecialchars($d['license_number']) ?>
                            </p>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">License Classification</label>
                            <p style="font-weight: bold; margin: 0; font-size: 0.95rem;">
                                <?= ucwords(str_replace('_', ' ', $d['license_type'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deployment/Assignment History -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom: 1px solid var(--border-color); padding: var(--space-4); margin: -var(--space-4) -var(--space-4) var(--space-4) -var(--space-4); display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title"
                        style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;"><i
                            data-lucide="history" style="width:18px;height:18px;color:var(--primary);"></i> Chauffeur Deployment History</h2>
                </div>
                <div class="table-container" style="border:none; margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: var(--secondary-50); border-bottom: 1px solid var(--border-color);">
                            <tr>
                                <th style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Agreement</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Contractor & Vector</th>
                                <th style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Timeline</th>
                                <th style="padding: 12px 16px; text-align: center; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Chauffeur Fee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="4" style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 0.875rem; font-weight: bold;">
                                        No active deployment records attached to this personnel.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): 
                                    $STAT_COLORS = ['active' => 'primary', 'completed' => 'success', 'returned' => 'info', 'cancelled' => 'danger', 'reserved' => 'secondary', 'confirmed' => 'info'];
                                ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px 16px; font-weight: bold; font-size: 0.8rem; color: var(--primary-600);">
                                            <a href="<?= BASE_URL ?>modules/rentals/view.php?id=<?= $h['agreement_id'] ?>" style="color: inherit; text-decoration: none;">
                                                #<?= htmlspecialchars($h['agreement_number']) ?>
                                            </a>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; font-weight: 800;">
                                                <span style="color:var(--<?= $STAT_COLORS[$h['status']] ?? 'secondary' ?>);"><?= htmlspecialchars($h['status']) ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 0.8rem; font-weight: bold; color: var(--text-main);">
                                            <?= htmlspecialchars($h['customer_name']) ?>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; font-weight: 600; font-family:monospace;">
                                                <?= htmlspecialchars($h['plate_number']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 0.8rem; color: var(--text-main);">
                                            <div style="color: var(--text-main); font-weight: 800; font-size: 0.75rem; margin-bottom: 2px;">
                                                DISPATCH: <?= date('M d, Y', strtotime($h['rental_start_date'])) ?>
                                            </div>
                                            <div style="color: var(--text-secondary); font-weight: 800; font-size: 0.75rem;">
                                                RETURN: <?= date('M d, Y', strtotime($h['rental_end_date'])) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 0.8rem; font-weight: 900; color: var(--text-main); text-align:center;">
                                            <?= formatCurrency($h['chauffeur_fee']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- /card -->

            <!-- Administrative Notes -->
            <?php if (!empty($d['notes'])): ?>
                <div class="card" style="margin-bottom: var(--space-6);">
                    <div class="card-body">
                        <h2 style="margin-bottom: var(--space-4); margin-top: 0; font-size: 1rem; display: flex; align-items: center; gap: 8px; color: var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">
                            <i data-lucide="info" style="width:16px;height:16px;"></i> Administrative Notes
                        </h2>
                        <div style="font-size: 0.875rem; line-height: 1.5; color: var(--text-main); background:var(--secondary-50); padding:var(--space-4); border-radius:var(--radius-md); border-left:3px solid var(--primary-300);">
                            <?= nl2br(htmlspecialchars($d['notes'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php if ($successMsg): ?>
    <div id="toast-drv" style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span><?= htmlspecialchars($successMsg) ?></span>
    </div>
    <style>
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(60px) scale(0.96); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }
    </style>
    <script>setTimeout(() => { document.getElementById('toast-drv')?.remove(); }, 3500);</script>
<?php endif; ?>

<script>
    lucide.createIcons();
</script>

<?php require_once '../../includes/footer.php'; ?>