<?php
/**
 * Create New User Account
 * Path: modules/settings/user-add.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('system_admin');

$db = Database::getInstance();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        // Trim and sanitize all inputs
        $employeeId = trim($_POST['employee_id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; // do not trim passwords
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $role = trim($_POST['role'] ?? '');

        $allowedDepts = ['system_admin', 'fleet_manager', 'procurement', 'maintenance'];
        $allowedRoles = ['system_admin', 'fleet_manager', 'procurement_officer', 'maintenance_supervisor'];

        // Server-side validation
        if (!$employeeId || !$username || !$firstName || !$lastName || !$password || !$department || !$role) {
            $errors[] = 'All required fields must be filled in.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif (!in_array($department, $allowedDepts, true)) {
            $errors[] = 'Invalid department selected.';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Invalid role selected.';
        } else {
            $userObj = new User();
            try {
                $data = [
                    'employee_id' => $employeeId,
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'middle_name' => $middleName ?: null,
                    'phone' => $phone ?: null,
                    'department' => $department,
                    'role' => $role,
                    'must_change_password' => isset($_POST['must_change'])
                ];

                $userObj->create($data, $_SESSION['user_id']);

                $_SESSION['success_message'] = 'Account created successfully for ' . htmlspecialchars($firstName . ' ' . $lastName);
                header('Location: user-management.php');
                exit;

            } catch (Exception $e) {
                error_log('User create error by user_id ' . $_SESSION['user_id'] . ': ' . $e->getMessage());
                $errors[] = 'Failed to create account. The username or Employee ID may already exist.';
            }
        }
    }
}

$pageTitle = 'Create Account';
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Create Account</h1>
        <p>Onboard a new system user and define their access level.</p>
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
                    <label for="employee_id">Employee ID <span class="text-danger">*</span></label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" class="form-control" name="employee_id" id="employee_id" required
                            placeholder="e.g. EMP-2024-001" style="font-family:monospace; text-transform:uppercase;">
                        <button type="button" class="btn btn-secondary" onclick="generateEmployeeId()" style="white-space:nowrap; padding:0 12px; font-size:0.8125rem;">
                            <i data-lucide="zap" style="width:14px;height:14px;margin-right:4px;"></i> Auto
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="username">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="username" id="username" required>
                </div>

                <div class="form-group">
                    <label for="first_name">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name" id="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="last_name" id="last_name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" id="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Temporary Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="password" id="password" required>
                </div>

                <div class="form-group">
                    <label for="department">Department <span class="text-danger">*</span></label>
                    <select name="department" id="department" class="form-control" required>
                        <option value="system_admin">System Admin</option>
                        <option value="fleet_manager">Fleet Manager</option>
                        <option value="procurement">Procurement</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="role">Role <span class="text-danger">*</span></label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="system_admin">System Administrator</option>
                        <option value="fleet_manager">Fleet Manager</option>
                        <option value="procurement_officer">Procurement Officer</option>
                        <option value="maintenance_supervisor">Maintenance Supervisor</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-top: var(--space-6);">
                <label class="form-checkbox-label">
                    <input type="checkbox" name="must_change" checked> Force password change on first login
                </label>
            </div>
        </div>

        <div class="card-footer-actions">
            <a href="user-management.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create User Account</button>
        </div>
    </form>
<script>
function generateEmployeeId() {
    const year = new Date().getFullYear();
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let rand = '';
    for (let i = 0; i < 4; i++) {
        rand += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('employee_id').value = `EMP-${year}-${rand}`;
}
</script>

<?php require_once '../../includes/footer.php'; ?>