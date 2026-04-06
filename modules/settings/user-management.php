<?php
/**
 * User Management Dashboard
 * Path: modules/settings/user-management.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Strict administrative access
$authUser->requirePermission('system_admin');

$db = Database::getInstance();
$userObj = new User();

$page = (int) ($_GET['page'] ?? 1);
$filters = [
    'role' => $_GET['role'] ?? '',
    'department' => $_GET['department'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$result = $userObj->getAll($filters, $page, ITEMS_PER_PAGE);
$users = $result['data'] ?? [];
$total = $result['total'] ?? 0;

$pageTitle = 'User Management';
require_once '../../includes/header.php';

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>User Management</h1>
        <p>Control system access, roles, and administrative privileges.</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Settings
        </a>
        <a href="user-add.php" class="btn btn-primary">
            <i data-lucide="user-plus" style="width:16px;height:16px;"></i> Create Account
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-filters">
            <form method="GET" class="card-header-form">
                <input type="text" name="search" class="form-control"
                    value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Search name, username, email...">
                <select name="role" class="form-control form-control--inline">
                    <option value="">All Roles</option>
                    <option value="system_admin" <?= $filters['role'] === 'system_admin' ? 'selected' : '' ?>>Admin
                    </option>
                    <option value="fleet_manager" <?= $filters['role'] === 'fleet_manager' ? 'selected' : '' ?>>Manager
                    </option>
                    <option value="procurement_officer" <?= $filters['role'] === 'procurement_officer' ? 'selected' : '' ?>>Procurement</option>
                    <option value="maintenance_supervisor" <?= $filters['role'] === 'maintenance_supervisor' ? 'selected' : '' ?>>Maintenance</option>
                    <option value="customer_service_staff" <?= $filters['role'] === 'customer_service_staff' ? 'selected' : '' ?>>CS Staff</option>
                    <option value="mechanic" <?= $filters['role'] === 'mechanic' ? 'selected' : '' ?>>Mechanic</option>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="user-management.php" class="btn btn-ghost btn-sm" title="Reset Filters"><i
                            data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></a>
                </div>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>User / Employee ID</th>
                    <th>Role & Dept</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)):
                    foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;">
                                    <?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?>
                                </div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= htmlspecialchars($u['email'] ?? '—') ?> | <strong>
                                        <?= htmlspecialchars($u['employee_id'] ?? '—') ?>
                                    </strong>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:0.875rem; font-weight:500;">
                                    <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $u['role'] ?? ''))) ?>
                                </div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= htmlspecialchars(ucfirst($u['department'] ?? '')) ?>
                                </div>
                            </td>
                            <td>
                                <span
                                    class="badge <?= ($u['status'] ?? '') === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                    <?= htmlspecialchars(strtoupper($u['status'] ?? 'inactive')) ?>
                                </span>
                            </td>
                            <td style="font-size:0.875rem; color:var(--text-muted);">
                                <?= !empty($u['last_login']) ? date('M d, H:i', strtotime($u['last_login'])) : 'Never' ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                                    <a href="user-edit.php?id=<?= (int) $u['user_id'] ?>"
                                        style="font-size:0.75rem; font-weight:700; color:var(--primary-600); text-decoration:none; border:1px solid var(--primary-100); padding:0.25rem 0.6rem; border-radius:4px;">EDIT</a>

                                    <a href="javascript:void(0)"
                                        onclick="confirmDelete(<?= (int) $u['user_id'] ?>, '<?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')), ENT_QUOTES) ?>')"
                                        style="font-size:0.75rem; font-weight:700; color:#d9534f; text-decoration:none; border:1px solid #f2dede; padding:0.25rem 0.6rem; border-radius:4px;">DELETE</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">No users found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total > ITEMS_PER_PAGE): ?>
    <div style="margin-top:2rem; display:flex; justify-content:center;">
        <?= pagination($total, $page, ITEMS_PER_PAGE) ?>
    </div>
<?php endif; ?>

<script>
    function confirmDelete(userId, userName) {
        openGcrModal(
            'Delete User Account',
            'Are you sure you want to delete the account for ' + userName + '? This action is permanent and will revoke all system access.',
            function () {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'user-delete.php';
                form.innerHTML = '<input type="hidden" name="id" value="' + userId + '">' +
                    '<input type="hidden" name="csrf_token" value="<?= $_SESSION["csrf_token"] ?? "" ?>">';
                document.body.appendChild(form);
                form.submit();
            }
        );
    }
    lucide.createIcons();
</script>

<?php if ($successMsg): ?>
    <div id="user-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($successMsg) ?></span>
        <button onclick="document.getElementById('user-toast').remove()"
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
            var t = document.getElementById('user-toast');
            if (t) {
                t.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                setTimeout(function () { if (t) t.remove(); }, 400);
            }
        }, 3500);
    </script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>