<?php
/**
 * My Profile — Settings Module
 * Path: modules/settings/profile.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('settings.view');
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE user_id = ?", [$userId]);
if (!$user) {
    redirect('modules/dashboard/', 'User not found', 'error');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $fn = trim($_POST['first_name'] ?? '');
        $ln = trim($_POST['last_name'] ?? '');
        $ph = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$fn || !$ln) {
            $errors[] = 'First and last name are required.';
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } else {
            try {
                $db->execute(
                    "UPDATE users SET first_name=?, last_name=?, phone=?, email=?, updated_at=NOW() WHERE user_id=?",
                    [$fn, $ln, $ph ?: null, $email ?: null, $userId]
                );
                $_SESSION['full_name'] = "$fn $ln";
                $success = 'Profile updated successfully.';
                $user = $db->fetchOne("SELECT * FROM users WHERE user_id=?", [$userId]);
            } catch (Exception $e) {
                error_log("Profile update error for user_id {$userId}: " . $e->getMessage());
                $errors[] = 'An error occurred while updating your profile. Please try again.';
            }
        }
    }
}

$firstName = $user['first_name'] ?? '';
$lastName = $user['last_name'] ?? '';
$initials = strtoupper(
    (mb_strlen($firstName) > 0 ? mb_substr($firstName, 0, 1) : '') .
    (mb_strlen($lastName) > 0 ? mb_substr($lastName, 0, 1) : '') ?: '?'
);
$pageTitle = 'My Profile';
require_once '../../includes/header.php';
?>
<div class="page-header">
    <div class="page-title">
        <h1>My Profile</h1>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Settings
        </a>
    </div>
</div>


<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i data-lucide="alert-circle"></i>
        <?= htmlspecialchars($errors[0]) ?>
    </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
    <!-- Avatar Card -->
    <div class="card" style="text-align: center;">
        <div class="card-body">
            <div
                style="width: 120px; height: 120px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; margin: 0 auto var(--space-4);">
                <?= htmlspecialchars($initials) ?>
            </div>
            <h2 style="margin-bottom: var(--space-2);">
                <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
            </h2>
            <p
                style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                <?= htmlspecialchars(str_replace('_', ' ', ucfirst($user['role'] ?? ''))) ?>
            </p>
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: var(--space-4);">
                <?= htmlspecialchars($user['department'] ?? '—') ?>
            </p>
            <div
                style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: var(--success); color: white; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">
                <span style="width: 6px; height: 6px; background: white; border-radius: 50%;"></span>
                <?= htmlspecialchars(ucfirst($user['status'] ?? 'active')) ?>
            </div>

            <div
                style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
                <p
                    style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                    Account Info</p>
                <div
                    style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                    <span style="color: var(--text-secondary);">ID</span>
                    <strong><?= htmlspecialchars($user['employee_id'] ?? '—') ?></strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                    <span style="color: var(--text-secondary);">Username</span>
                    <strong><?= htmlspecialchars($user['username'] ?? '—') ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                    <span style="color: var(--text-secondary);">Last Login</span>
                    <span><?= !empty($user['last_login']) ? date('M d, Y H:i', strtotime($user['last_login'])) : '—' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="card">
        <div class="card-body">
            <h2 style="margin-bottom: var(--space-4); margin-top: 0;">Personal Information</h2>
            <form method="POST">
                <?= csrfField() ?>
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control"
                            value="<?= htmlspecialchars($user['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control"
                            value="<?= htmlspecialchars($user['last_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control"
                            value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid"
                    style="grid-template-columns: 1fr 1fr; gap: var(--space-4); background: var(--primary-50); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-5);">
                    <div>
                        <label
                            style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Department</label>
                        <p style="font-weight: bold; margin: 0;">
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['department'] ?? '—'))) ?>
                        </p>
                    </div>
                    <div>
                        <label
                            style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Role</label>
                        <p style="font-weight: bold; margin: 0;">
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? '—'))) ?>
                        </p>
                    </div>
                </div>

                <div style="display: flex; gap: var(--space-3);">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i data-lucide="save"></i> Save Changes
                    </button>
                    <a href="security.php" class="btn btn-secondary">
                        <i data-lucide="shield"></i> Change Password
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>

<?php if ($success): ?>
    <div id="profile-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($success) ?></span>
        <button onclick="document.getElementById('profile-toast').remove()"
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
            var t = document.getElementById('profile-toast');
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