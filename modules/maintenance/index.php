<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$pageTitle = "Maintenance Hub";
require_once '../../includes/header.php';

$authUser->requirePermission('maintenance.view');

try {
    $maintObj = new MaintenanceRecord();
    $stats = $maintObj->getStats();
    $schedules = $maintObj->getAllSchedules();

    // Fetch Recent History (Last 15 records) for Sidebar
    $combinedSql = "
        SELECT 
            log_id as id,
            vehicle_id,
            service_type,
            service_description,
            service_date,
            mileage_at_service,
            status,
            created_at,
            'log' as record_type
        FROM maintenance_logs
        
        UNION ALL
        
        SELECT 
            schedule_id as id,
            vehicle_id,
            service_type,
            notes as service_description,
            last_service_date as service_date,
            last_service_mileage as mileage_at_service,
            status,
            created_at,
            'schedule' as record_type
        FROM maintenance_schedules
        WHERE status = 'completed'
    ";

    $history = $db->fetchAll(
        "SELECT r.*, v.plate_number, v.brand, v.model
         FROM ($combinedSql) r
         JOIN vehicles v ON r.vehicle_id = v.vehicle_id
         ORDER BY r.created_at DESC
         LIMIT 15"
    );

} catch (Exception $e) {
    $stats = ['total_active' => 0, 'overdue' => 0, 'upcoming' => 0, 'in_service' => 0];
    $schedules = [];
    $history = [];
}

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Maintenance Hub</h1>
        <p>Monitoring diagnostics, preventative scheduling, and workshop performance.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-secondary" style="margin-right: 8px;" 
                onclick="document.getElementById('maintenance-history-panel').classList.add('open')">
            <i data-lucide="history" style="width:16px;height:16px;"></i> History
        </button>
        <a href="schedule-add.php" class="btn btn-primary">
            <i data-lucide="calendar-plus" style="width:16px;height:16px;"></i> Schedule Service
        </a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon danger"><i data-lucide="alert-octagon" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['overdue'] ?? 0 ?></div>
        <div class="stat-label">Critical Overdue</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon warning"><i data-lucide="clock" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['upcoming'] ?? 0 ?></div>
        <div class="stat-label">Due Soon (7d)</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon primary"><i data-lucide="wrench" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['in_service'] ?? 0 ?></div>
        <div class="stat-label">In Workshop</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon success"><i data-lucide="shield-check" style="width:20px;height:20px;"></i></div>
        <div class="stat-value">97%</div>
        <div class="stat-label">Fleet Integrity</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Service Queue</h2>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Service Type</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($schedules)):
                    foreach ($schedules as $s):
                        $overdue = strtotime($s['next_due_date']) < time();
                        $badgeCls = match (strtolower($s['status'])) {
                            'scheduled' => 'badge-info',
                            'in_progress' => 'badge-warning',
                            'overdue' => 'badge-danger',
                            'active' => 'badge-primary',
                            'paused' => 'badge-secondary',
                            default => 'badge-secondary'
                        };
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($s['brand'] . ' ' . $s['model']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($s['plate_number']) ?>
                                </div>
                            </td>
                            <td><?= ucfirst(str_replace('_', ' ', $s['service_type'])) ?></td>
                            <td>
                                <div
                                    style="color:<?= $overdue ? 'var(--danger)' : 'var(--text-main)' ?>; font-weight:<?= $overdue ? '700' : '400' ?>;">
                                    <?= date('M d, Y', strtotime($s['next_due_date'])) ?>
                                </div>
                            </td>
                            <td><span class="badge <?= $badgeCls ?>"><?= strtoupper($s['status']) ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="service-view.php?id=<?= $s['schedule_id'] ?>" class="btn btn-ghost btn-sm">View</a>
                                    
                                    <?php if ($s['status'] === 'scheduled'): ?>
                                        <a href="service-start.php?id=<?= $s['schedule_id'] ?>"
                                            class="btn btn-primary btn-sm">Initiate</a>
                                    <?php elseif ($s['status'] === 'in_progress'): ?>
                                        <a href="service-view.php?id=<?= $s['schedule_id'] ?>" 
                                            class="btn btn-success btn-sm" title="Finalize Maintenance">
                                            <i data-lucide="check-circle" style="width:14px;height:14px;margin-right:4px;"></i> Complete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:3rem;color:var(--text-muted);">All units ready</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($successMsg): ?>
    <div id="maintenance-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($successMsg) ?></span>
        <button onclick="document.getElementById('maintenance-toast').remove()"
            style="background:none;border:none;cursor:pointer;color:#fff;padding:0;margin:0;display:flex;align-items:center;opacity:0.8;"
            aria-label="Dismiss">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    </div>
    <style>
        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(60px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }
    </style>
    <script>
        setTimeout(function () {
            var t = document.getElementById('maintenance-toast');
            if (t) {
                t.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                setTimeout(function () { if (t) t.remove(); }, 400);
            }
        }, 3500);
    </script>
<?php endif; ?>

<!-- Maintenance History Sidebar -->
<div id="maintenance-history-panel" 
     style="position:fixed;top:0;right:0;width:560px;max-width:100vw;height:100vh;background:var(--bg-surface,#fff);box-shadow:-4px 0 24px rgba(0,0,0,.15);z-index:10000;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-color);">
        <h2 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;font-weight:800;">
            <i data-lucide="history" style="width:20px;height:20px;color:var(--primary);"></i>
            Transaction History
        </h2>
        <button onclick="document.getElementById('maintenance-history-panel').classList.remove('open')" 
                style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:color 0.2s;"
                onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
            <i data-lucide="x" style="width:20px;height:20px;"></i>
        </button>
    </div>
    <div style="flex:1;overflow-y:auto;padding:1.5rem;background:var(--bg-body, #f4f6f8);">
        <?php if (empty($history)): ?>
            <div style="text-align:center;padding:3rem;color:var(--text-muted);">
                <i data-lucide="info" style="width:32px;height:32px;margin-bottom:1rem;opacity:0.5;"></i>
                <p>No recent maintenance history found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $h): 
                $hStatusCls = match($h['status']) {
                    'completed' => 'badge-success',
                    'in_progress' => 'badge-warning',
                    default => 'badge-secondary'
                };
            ?>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:1.25rem;margin-bottom:1rem;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-surface,#fff);box-shadow:0 1px 3px rgba(0,0,0,.04);transition:transform 0.2s, box-shadow 0.2s;"
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.box_shadow='0 4px 12px rgba(0,0,0,0.08)';"
                     onmouseout="this.style.transform='none'; this.style.box_shadow='0 1px 3px rgba(0,0,0,0.04)';">
                    <div style="flex-grow:1;padding-right:1rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <div style="font-weight:800;color:var(--primary);font-family:monospace;font-size:0.9rem;">
                                <?= strtoupper(str_replace('_', ' ', $h['service_type'])) ?>
                            </div>
                            <span class="badge <?= $hStatusCls ?>" style="font-size:0.65rem;text-transform:uppercase;padding:2px 6px;"><?= $h['status'] ?></span>
                        </div>
                        <div style="font-size:.95rem;font-weight:700;margin-bottom:6px;color:var(--text-main);">
                            <?= htmlspecialchars($h['brand'] . ' ' . $h['model']) ?>
                        </div>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                            <i data-lucide="car" style="width:12px;height:12px;"></i>
                            <span style="font-family:monospace;background:var(--secondary-50);padding:2px 6px;border-radius:4px;color:var(--secondary-700);font-weight:700;">
                                <?= htmlspecialchars($h['plate_number']) ?>
                            </span>
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:center;gap:12px;font-weight:600;">
                            <span style="display:flex;align-items:center;gap:4px;">
                                <i data-lucide="calendar" style="width:12px;height:12px;"></i>
                                <?= date('M d, Y', strtotime($h['service_date'])) ?>
                            </span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.5rem;min-width:80px;text-align:right;">
                        <a href="<?= $h['record_type'] === 'schedule' ? 'service-view.php?id=' : 'vehicle-details.php?id=' ?><?= $h['id'] ?>" 
                           class="btn btn-ghost btn-sm" style="font-weight:700;font-size:0.75rem;">Inspect</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <div style="text-align:center;padding:1rem 0;">
                <a href="history.php" class="btn btn-ghost btn-sm" style="text-decoration:underline;color:var(--primary);">
                    Explore Comprehensive Records
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    #maintenance-history-panel.open { transform: translateX(0) !important; }
</style>

<script>
    lucide.createIcons();
</script>

<?php require_once '../../includes/footer.php'; ?>