<?php
/**
 * Service View (Maintenance Schedule Detail)
 * Path: modules/maintenance/service-view.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('maintenance.view');

$db = Database::getInstance();
$scheduleId = (int) ($_GET['id'] ?? 0);

if (!$scheduleId) {
    redirect('modules/maintenance/', 'Schedule ID missing.', 'error');
}

try {
    // Fetch Schedule details + Vehicle info
    $schedule = $db->fetchOne(
        "SELECT s.*, v.plate_number, v.brand, v.model, v.year_model, v.color, v.current_status as vehicle_status, v.primary_photo_path
         FROM maintenance_schedules s
         JOIN vehicles v ON s.vehicle_id = v.vehicle_id
         WHERE s.schedule_id = ?",
        [$scheduleId]
    );

    if (!$schedule) {
        redirect('modules/maintenance/', 'Maintenance Schedule not found.', 'error');
    }
    
    // Process Completion Request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            redirect('modules/maintenance/service-view.php?id=' . $scheduleId, 'Invalid security token.', 'error');
        }
        
        $authUser->requirePermission('maintenance.update');
        
        $db->beginTransaction();
        try {
            $db->execute("UPDATE maintenance_schedules SET status = 'completed', last_service_date = CURDATE() WHERE schedule_id = ?", [$scheduleId]);
            $db->execute("UPDATE vehicles SET current_status = 'available' WHERE vehicle_id = ? AND current_status = 'maintenance'", [$schedule['vehicle_id']]);
            $db->commit();
            
            $_SESSION['success_message'] = 'Maintenance confirmed and completed successfully! Vehicle returned to active duty.';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $db->rollback();
            redirect('modules/maintenance/service-view.php?id=' . $scheduleId, 'Failed to complete maintenance: ' . $e->getMessage(), 'error');
        }
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    redirect('modules/maintenance/', 'Error loading Service Detail.', 'error');
}

$pageTitle = 'Service Schedule — ' . $schedule['plate_number'];
require_once '../../includes/header.php';
?>

<div class="fade-in max-w-6xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 mb-6 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Maintenance Hub
        </a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600">Service Detail</span>
    </div>

    <!-- Main Layout Grid -->
    <div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
        <!-- Summary Card (Left Column) -->
        <div class="flex flex-col gap-6">
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <style>
                        #vehicle3dStage{perspective:900px;width:100%;height:140px;display:flex;align-items:center;justify-content:center;margin-bottom:var(--space-4);}
                        #vehicle3dCard{width:220px;height:140px;border-radius:14px;background:linear-gradient(135deg,#1e293b,#334155);box-shadow:0 15px 40px rgba(0,0,0,.35),0 4px 12px rgba(0,0,0,.2);transform-style:preserve-3d;animation:spin3d 8s linear infinite;overflow:hidden;position:relative;}
                        #vehicle3dCard img{width:100%;height:100%;object-fit:cover;border-radius:14px;}
                        #vehicle3dCard:hover{animation-play-state:paused;}
                        #vehicle3dCard .car-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;}
                        @keyframes spin3d{0%{transform:rotateY(-25deg) rotateX(5deg)}50%{transform:rotateY(25deg) rotateX(-5deg)}100%{transform:rotateY(-25deg) rotateX(5deg)}}
                    </style>

                    <div id="vehicle3dStage">
                        <div id="vehicle3dCard">
                            <?php if (!empty($schedule['primary_photo_path'])): ?>
                                <img src="<?php echo BASE_URL . ltrim($schedule['primary_photo_path'], '/'); ?>" alt="<?php echo htmlspecialchars($schedule['vehicle_id'] ?? ''); ?>">
                            <?php else: ?>
                                <div class="car-placeholder">
                                    <i data-lucide="wrench" style="width: 48px; height: 48px;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h2 style="margin-bottom: var(--space-2);">
                        <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $schedule['service_type']))) ?>
                    </h2>
                    <p
                        style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                        <?= htmlspecialchars($schedule['plate_number']) ?>
                    </p>
                    <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: var(--space-4);">
                        <?= htmlspecialchars($schedule['brand'] . ' ' . $schedule['model']) ?>
                    </p>

                    <?php
                    $statusColor = match ($schedule['status']) {
                        'scheduled' => 'var(--info, #3b82f6)',
                        'active' => 'var(--primary)',
                        'in_progress' => 'var(--warning, #f59e0b)',
                        'overdue' => 'var(--danger)',
                        'completed' => 'var(--success)',
                        'paused' => 'var(--secondary-500)',
                        default => 'var(--secondary-500)',
                    };
                    ?>
                    <div
                        style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: <?= $statusColor ?>; color: white; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">
                        <span style="width: 6px; height: 6px; background: white; border-radius: 50%;"></span>
                        <?= htmlspecialchars($schedule['status']) ?>
                    </div>

                    <div
                        style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
                        <p
                            style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                            Schedule Info</p>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">ID</span>
                            <strong>#SVC-<?= str_pad($scheduleId, 4, '0', STR_PAD_LEFT) ?></strong>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Schedule Basis</span>
                            <strong><?= ucfirst(str_replace('_', ' ', $schedule['schedule_basis'] ?? 'N/A')) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Next Due</span>
                            <span
                                style="font-weight: bold; color: <?= $schedule['status'] === 'overdue' ? 'var(--danger)' : 'var(--text-main)' ?>;">
                                <?= $schedule['next_due_date'] ? formatDate($schedule['next_due_date']) : 'N/A' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php if (in_array($schedule['status'], ['scheduled', 'active', 'overdue'])): ?>
                    <a href="service-start.php?id=<?= $scheduleId ?>" class="btn btn-primary"
                        style="justify-content: center;">
                        <i data-lucide="play-circle" class="w-4 h-4"></i> Initiate Service
                    </a>
                <?php endif; ?>
                <?php if ($schedule['status'] === 'in_progress'): ?>
                    <form id="completeForm" method="POST" style="display:none;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="complete">
                    </form>
                    <button type="button" class="btn btn-success"
                        style="justify-content: center;" onclick="confirmComplete()">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Complete Maintenance
                    </button>
                    <script>
                    function confirmComplete() {
                        openGcrModal({
                            title: 'Complete Maintenance',
                            message: 'Are you sure you want to mark this maintenance event as complete? The vehicle will be removed from the workshop and made available for rent again.',
                            variant: 'success',
                            confirmLabel: 'Confirm Completion',
                            icon: 'check-circle',
                            onConfirm: function() {
                                document.getElementById('completeForm').submit();
                            }
                        });
                    }
                    </script>
                <?php endif; ?>
                <?php if (!in_array($schedule['status'], ['completed', 'in_progress'])): ?>
                    <a href="schedule-edit.php?id=<?= $scheduleId ?>" class="btn btn-secondary"
                        style="justify-content: center;">
                        <i data-lucide="edit-3" class="w-4 h-4"></i> Modify Schedule
                    </a>
                <?php endif; ?>
                <a href="../asset-tracking/vehicle-details.php?id=<?= $schedule['vehicle_id'] ?>" class="btn btn-ghost"
                    style="justify-content: center;">
                    <i data-lucide="truck" class="w-4 h-4"></i> Asset Profile
                </a>
            </div>
        </div>

        <!-- Detail Sections (Right Column) -->
        <div class="flex flex-col gap-6">
            <!-- Service Parameters -->
            <div class="card">
                <div class="card-body">
                    <h2 style="margin-bottom: var(--space-4); margin-top: 0;">Service Parameters</h2>
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Next
                                Due Mileage</label>
                            <p style="font-weight: bold; margin: 0; font-size: 1.1rem;">
                                <?= $schedule['next_due_mileage'] ? number_format($schedule['next_due_mileage']) . ' km' : 'N/A' ?>
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Service
                                Interval</label>
                            <p style="font-weight: bold; margin: 0;">
                                <?= $schedule['interval_months'] ? $schedule['interval_months'] . ' Months' : '' ?>
                                <?= ($schedule['interval_months'] && $schedule['interval_mileage']) ? ' / ' : '' ?>
                                <?= $schedule['interval_mileage'] ? number_format($schedule['interval_mileage']) . ' km' : '' ?>
                                <?php if (!$schedule['interval_months'] && !$schedule['interval_mileage']): ?>
                                    Not Specified
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vehicle Information -->
            <div class="card">
                <div class="card-body">
                    <h2 style="margin-bottom: var(--space-4); margin-top: 0;">Asset Target</h2>

                    <div class="grid"
                        style="grid-template-columns: 1fr 1fr; gap: var(--space-4); background: var(--primary-50); padding: var(--space-4); border-radius: var(--radius-md);">
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Vehicle</label>
                            <p style="font-weight: bold; margin: 0; color: var(--primary-900);">
                                <?= htmlspecialchars($schedule['brand'] . ' ' . $schedule['model']) ?>
                                (<?= htmlspecialchars($schedule['year_model']) ?>)
                            </p>
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Asset
                                Status</label>
                            <p style="font-weight: bold; margin: 0; color: var(--primary-900);">
                                <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $schedule['vehicle_status']))) ?>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($schedule['notes'])): ?>
                        <div
                            style="margin-top: var(--space-4); border-top: 1px solid var(--border-color); padding-top: var(--space-4);">
                            <label
                                style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Technical
                                Notes & Remarks</label>
                            <p
                                style="margin: 0; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.5; white-space: pre-wrap;">
                                <?= htmlspecialchars($schedule['notes'] ?? '') ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Previous Service -->
            <div class="card">
                <div class="card-body">
                    <h2
                        style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="history" style="color: var(--primary);"></i> Previous Service
                    </h2>

                    <?php if ($schedule['last_service_date']): ?>
                        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <div
                                style="border: 1px solid var(--success-200); background: var(--success-50); padding: var(--space-4); border-radius: var(--radius-md); display: flex; align-items: center; gap: 12px;">
                                <i data-lucide="calendar-check"
                                    style="color: var(--success-600); width: 24px; height: 24px;"></i>
                                <div>
                                    <label
                                        style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--success-700); margin-bottom: 2px;">Date
                                        Executed</label>
                                    <p style="font-weight: bold; margin: 0; color: var(--success-900);">
                                        <?= formatDate($schedule['last_service_date']) ?>
                                    </p>
                                </div>
                            </div>
                            <div
                                style="border: 1px solid var(--secondary-200); background: var(--secondary-50); padding: var(--space-4); border-radius: var(--radius-md); display: flex; align-items: center; gap: 12px;">
                                <i data-lucide="gauge" style="color: var(--secondary-500); width: 24px; height: 24px;"></i>
                                <div>
                                    <label
                                        style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 2px;">Odometer
                                        Reading</label>
                                    <p style="font-weight: bold; margin: 0; color: var(--text-main);">
                                        <?= number_format($schedule['last_service_mileage'] ?? 0) ?> km
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div
                            style="text-align: center; padding: var(--space-5); background: var(--secondary-50); border-radius: var(--radius-lg); border: 1px dashed var(--secondary-200);">
                            <i data-lucide="info"
                                style="color: var(--text-muted); opacity: 0.5; width: 24px; height: 24px; margin-bottom: 8px;"></i>
                            <p style="font-weight: bold; font-size: 0.875rem; color: var(--text-secondary); margin: 0;">No
                                prior service history recorded for this parameter set.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
</script>

<?php require_once '../../includes/footer.php'; ?>