<?php
/**
 * Edit User Account
 * Path: modules/settings/user-edit.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('system_admin');

$db = Database::getInstance();
$userObj = new User();
$errors = [];
$success = false;

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$userId) {
    header("Location: user-management.php");
    exit;
}

$targetUser = $db->fetchOne("SELECT * FROM users WHERE user_id = ? AND deleted_at IS NULL", [$userId]);
if (!$targetUser) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: user-management.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        try {
            $data = [
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'department' => $_POST['department'],
                'role' => $_POST['role'],
                'status' => $_POST['status'],
                'phone' => $_POST['phone'] ?? ''
            ];

            if (!empty($_POST['new_password'])) {
                $newHash = password_hash($_POST['new_password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                $db->execute("UPDATE users SET password_hash = ?, must_change_password = TRUE WHERE user_id = ?", [$newHash, $userId]);
            }

            $userObj->update($userId, $data, $_SESSION['user_id']);

            $_SESSION['success_message'] = "Account updated successfully for " . htmlspecialchars($data['first_name']);
            header("Location: user-management.php");
            exit;

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Account: ' . $targetUser['username'];
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Edit Account</h1>
        <p>Modify user profile, role, and system status for <strong><?= htmlspecialchars($targetUser['username']) ?></strong>.</p>
    </div>
</div>

<div class="card card-form-centered">
    <?php if (!empty($errors)): ?>
    <div class="card-body" style="padding-bottom: 0;">
        <div class="form-error-alert">
            <strong>Error:</strong> <?= htmlspecialchars($errors[0]) ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div class="card-body">
            <div class="form-grid form-grid--two">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($targetUser['employee_id']) ?>" disabled style="background: var(--bg-muted); color: var(--text-muted);">
                    <small class="form-text text-muted">Employee ID cannot be changed.</small>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($targetUser['username']) ?>" disabled style="background: var(--bg-muted); color: var(--text-muted);">
                    <small class="form-text text-muted">Username cannot be changed.</small>
                </div>

                <div class="form-group">
                    <label for="first_name">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name" id="first_name" value="<?= htmlspecialchars($targetUser['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="last_name" id="last_name" value="<?= htmlspecialchars($targetUser['last_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($targetUser['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" class="form-control" name="phone" id="phone" value="<?= htmlspecialchars($targetUser['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="department">Department <span class="text-danger">*</span></label>
                    <select name="department" id="department" class="form-control" required>
                        <?php foreach (['management', 'operations', 'maintenance', 'procurement', 'customer_service', 'admin'] as $dept): ?>
                        <option value="<?= $dept ?>" <?= $targetUser['department'] === $dept ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $dept)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="role">Role <span class="text-danger">*</span></label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="viewer" <?= $targetUser['role'] === 'viewer' ? 'selected' : '' ?>>Viewer (Read Only)</option>
                        <option value="customer_service_staff" <?= $targetUser['role'] === 'customer_service_staff' ? 'selected' : '' ?>>Customer Service Staff</option>
                        <option value="mechanic" <?= $targetUser['role'] === 'mechanic' ? 'selected' : '' ?>>Mechanic</option>
                        <option value="maintenance_supervisor" <?= $targetUser['role'] === 'maintenance_supervisor' ? 'selected' : '' ?>>Maintenance Supervisor</option>
                        <option value="procurement_officer" <?= $targetUser['role'] === 'procurement_officer' ? 'selected' : '' ?>>Procurement Officer</option>
                        <option value="fleet_manager" <?= $targetUser['role'] === 'fleet_manager' ? 'selected' : '' ?>>Fleet Manager</option>
                        <option value="system_admin" <?= $targetUser['role'] === 'system_admin' ? 'selected' : '' ?>>System Administrator</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Account Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="active" <?= $targetUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $targetUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $targetUser['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new_password" style="color: var(--danger);">Reset Password</label>
                    <div style="position: relative;">
                        <input type="password" class="form-control" name="new_password" id="new_password" placeholder="Leave blank to keep current" style="padding-right: 2.5rem;">
                        <button type="button" onclick="togglePassword('new_password', 'toggleEditIcon')" class="form-password-toggle">
                            <i id="toggleEditIcon" data-lucide="eye" style="width:18px;height:18px;"></i>
                        </button>
                    </div>
                    <small class="form-text text-muted">If set, the user will be forced to change it on their next login.</small>
                </div>
            </div>
        </div>

        <div class="card-footer-actions">
            <a href="user-management.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<script>
function togglePassword(inputId, iconId) {
    var passwordInput = document.getElementById(inputId);
    var toggleIcon = document.getElementById(iconId);
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.setAttribute('data-lucide', 'eye-off');
    } else {
        passwordInput.type = 'password';
        toggleIcon.setAttribute('data-lucide', 'eye');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
lucide.createIcons();
</script>
<?php require_once '../../includes/footer.php'; ?>
