<?php
// modules/backups/index.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/BackupManager.php';

// Only System Admin can access this
if ($authUser->getData()['role'] !== 'system_admin') {
    $_SESSION['flash_message'] = "Unauthorized access to Backups module.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . BASE_URL . "modules/dashboard/");
    exit;
}

$backupManager = new BackupManager();

$pageTitle = "Backup & Recovery";
require_once '../../includes/header.php';

$backups = $backupManager->getBackups();
?>

<div class="page-header">
    <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div
                style="width:48px;height:48px;background:var(--primary-light);border:1px solid var(--border-color);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--primary);">
                <i data-lucide="database" style="width:24px;height:24px;"></i>
            </div>
            <div>
                <h1 style="margin:0;font-size:1.5rem;font-weight:800;letter-spacing:-0.02em;">Backup & Recovery</h1>
                <p style="margin:0;color:var(--text-muted);font-size:0.875rem;font-weight:600;">
                    Manage database backups to ensure data integrity
                </p>
            </div>
        </div>
        <div class="page-actions" style="margin:0;">
            <button onclick="createBackup()" class="btn btn-primary" id="btnCreateBackup">
                <i data-lucide="save" style="width:16px;height:16px;"></i> Create Manual Backup
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-container" style="border:none;">
        <?php if (count($backups) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Backup ID / Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Created By</th>
                    <th>Date Created</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <div style="width:36px;height:36px;background:var(--secondary-light);color:var(--text-secondary);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                    <i data-lucide="file-json" style="width:18px;height:18px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:0.875rem;color:var(--text-main);">
                                        <?php echo htmlspecialchars($b['backup_id']); ?>
                                    </div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                                        <?php echo htmlspecialchars($b['filename']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $b['backup_type'] === 'manual' ? 'info' : 'secondary'; ?>" style="font-family:monospace;">
                                <?php echo strtoupper($b['backup_type']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size:0.75rem;color:var(--text-secondary);font-weight:600;">
                                <?php echo round($b['file_size'] / 1024 / 1024, 2); ?> MB
                            </div>
                        </td>
                        <td>
                            <div style="font-size:0.8rem;color:var(--text-main);">
                                <?php echo htmlspecialchars($b['first_name'] ? $b['first_name'] . ' ' . $b['last_name'] : 'System'); ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:0.8rem;">
                                <?php echo date('M d, Y', strtotime($b['created_at'])); ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--text-muted);">
                                <?php echo date('h:i A', strtotime($b['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                                $statusClass = 'warning';
                                if ($b['status'] === 'completed') $statusClass = 'success';
                                if ($b['status'] === 'failed') $statusClass = 'danger';
                            ?>
                            <span class="badge badge-<?php echo $statusClass; ?>">
                                <?php echo ucfirst($b['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.25rem;justify-content:flex-end;">
                                <?php if ($b['status'] === 'completed'): ?>
                                    <a href="action.php?action=download&id=<?php echo urlencode($b['backup_id']); ?>" class="btn btn-ghost btn-sm" title="Download SQL Dump">
                                        <i data-lucide="download" style="width:16px;height:16px;"></i>
                                    </a>
                                    <button type="button" onclick="confirmRestore('<?php echo htmlspecialchars($b['backup_id']); ?>')" class="btn btn-ghost btn-sm" title="Restore System" style="color:var(--warning);">
                                        <i data-lucide="refresh-cw" style="width:16px;height:16px;"></i>
                                    </button>
                                <?php endif; ?>
                                <button type="button" onclick="confirmDelete('<?php echo htmlspecialchars($b['backup_id']); ?>')" class="btn btn-ghost btn-sm" title="Delete Backup" style="color:var(--danger);">
                                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div style="padding:4rem 1rem;text-align:center;color:var(--text-muted);">
                <div style="width:64px;height:64px;background:var(--secondary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <i data-lucide="database" style="width:32px;height:32px;color:var(--text-secondary);"></i>
                </div>
                <h3 style="font-size:1.125rem;font-weight:700;color:var(--text-main);margin:0 0 0.5rem;">No backups available</h3>
                <p style="margin:0;font-size:0.875rem;">Create a manual backup to ensure system data safety.</p>
                <button onclick="createBackup()" class="btn btn-primary" style="margin-top: 1rem;">
                    Create First Backup
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function createBackup() {
    const btn = document.getElementById('btnCreateBackup');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="spin" style="width:16px;height:16px;"></i> Generating Backup...';
        lucide.createIcons();
    }
    
    fetch('action.php?action=create', { method: 'POST' })
        .then(res => {
            // If we get redirected or a bad status, the backup might still have finished
            if (!res.ok || res.redirected) {
                console.warn('Backup response issue, checking results anyway...');
                setTimeout(() => window.location.reload(), 2000);
                return;
            }
            return res.json();
        })
        .then(data => {
            if (!data) return; // Handled by reload above
            if (data.success) {
                window.location.reload();
            } else {
                alert('Backup failed: ' + (data.error || 'Unknown error'));
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="save" style="width:16px;height:16px;"></i> Create Manual Backup';
                    lucide.createIcons();
                }
            }
        })
        .catch(err => {
            // Instead of immediate alert, wait 2 seconds and reload to see if it actually worked
            // This prevents the "Network Error" alert from appearing if the backup was successful
            console.error('Fetch error:', err);
            setTimeout(() => window.location.reload(), 2000);
        });
}

function confirmRestore(id) {
    if(typeof openGcrModal === 'function') {
        openGcrModal({
            title: 'DANGER: Initiate System Restore',
            message: 'Are you absolutely sure you want to restore the database from <strong>' + id + '</strong>? This will overwrite all current system data. <br><br>Type <strong>RESTORE</strong> below to confirm:',
            variant: 'danger',
            confirmLabel: 'Execute Restore',
            icon: 'alert-triangle',
            onConfirm: function() {
                executeRestore(id);
            },
            requirePrompt: 'RESTORE'
        });
    } else {
        const text = prompt('Type RESTORE to confirm data restoration from ' + id + ':');
        if (text === 'RESTORE') {
            executeRestore(id);
        }
    }
}

function executeRestore(id) {
    fetch('action.php?action=restore', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('System restored successfully!');
            window.location.reload();
        } else {
            alert('Restore failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => alert('A network error occurred.'));
}

function confirmDelete(id) {
    if(typeof openGcrModal === 'function') {
        openGcrModal({
            title: 'Delete Backup',
            message: 'Are you sure you want to permanently delete backup ' + id + '?',
            variant: 'danger',
            confirmLabel: 'Yes, Delete',
            icon: 'trash-2',
            onConfirm: function() {
                executeDelete(id);
            }
        });
    } else {
        if (confirm('Are you sure you want to delete this backup?')) {
            executeDelete(id);
        }
    }
}

function executeDelete(id) {
    fetch('action.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Delete failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => alert('A network error occurred.'));
}
</script>

<style>
.spin { animation: spin 2s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<?php require_once '../../includes/footer.php'; ?>
