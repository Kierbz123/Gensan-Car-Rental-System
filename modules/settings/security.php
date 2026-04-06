<?php
/**
 * Security Settings — Change Password & Session Management
 * Path: modules/settings/security.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('settings.view');
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE user_id=?", [$userId]);
if (!$user) {
    redirect('modules/dashboard/', 'User session invalid. Please log in again.', 'error');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $errors[] = 'All fields are required.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $new)) {
            $errors[] = 'Password must contain at least one number.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            try {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                $db->execute("UPDATE users SET password_hash=?, password_changed_at=NOW(), login_attempts=0 WHERE user_id=?", [$hash, $userId]);
                $success = 'Password changed successfully. Please log in again from other devices.';
            } catch (Exception $e) {
                error_log("Password change error for user_id {$userId}: " . $e->getMessage());
                $errors[] = 'An error occurred while updating your password. Please try again.';
            }
        }
    }
}

// Recent sessions (simulated from audit_logs if exists)
try {
    $sessions = $db->fetchAll(
        "SELECT ip_address, user_agent, action_timestamp FROM audit_logs
         WHERE user_id=? AND action='login'
         ORDER BY action_timestamp DESC LIMIT 5",
        [$userId]
    ) ?: [];
} catch (Exception $e) {
    $sessions = [];
}

$pageTitle = 'Security Settings';
require_once '../../includes/header.php';
?>
<div class="page-header">
    <div class="page-title">
        <h1>Password &amp; Security</h1>
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

<!-- Change Password -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <h2 style="margin-bottom: var(--space-5); margin-top: 0; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="key-round" style="color: var(--primary);"></i> Change Password
        </h2>
        <form method="POST" autocomplete="off">
            <?= csrfField() ?>

            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" required
                    autocomplete="current-password">
            </div>

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" id="newPw" class="form-control" required
                    autocomplete="new-password" oninput="checkStrength(this.value)">
                <!-- Strength meter -->
                <div
                    style="margin-top: 8px; height: 6px; border-radius: var(--radius-full); background: var(--border-color); overflow: hidden;">
                    <div id="pwStrengthBar"
                        style="height: 100%; border-radius: var(--radius-full); transition: width 0.3s ease; width: 0%; background: #ef4444;">
                    </div>
                </div>
                <p id="pwStrengthLabel"
                    style="font-size: 0.75rem; font-weight: bold; color: var(--text-muted); margin-top: 4px;"></p>
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required
                    autocomplete="new-password">
            </div>

            <!-- Requirements -->
            <div
                style="background: var(--primary-50); border-radius: var(--radius-md); padding: var(--space-4); font-size: 0.875rem; color: var(--text-secondary); margin-bottom: var(--space-5);">
                <?php foreach (['min8' => 'At least 8 characters', 'upper' => 'One uppercase letter', 'num' => 'One number'] as $id => $req): ?>
                    <div id="req-<?= htmlspecialchars($id) ?>"
                        style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span class="req-dot"
                            style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="check" style="width: 10px; height: 10px; display: none; color: white;"></i>
                        </span>
                        <?= htmlspecialchars($req) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i data-lucide="shield-check"></i> Update Password
            </button>
        </form>
    </div>
</div>

<!-- Recent Login Activity -->
<?php if (!empty($sessions)): ?>
    <div class="card">
        <div class="card-body">
            <h2 style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="activity" style="color: var(--info);"></i> Recent Login Activity
            </h2>
            <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                <?php foreach ($sessions as $s):
                    $ua = mb_substr($s['user_agent'] ?? 'Unknown', 0, 60); ?>
                    <div
                        style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3); background: var(--primary-50); border-radius: var(--radius-md);">
                        <i data-lucide="monitor" style="color: var(--text-muted); flex-shrink: 0;"></i>
                        <div style="min-width: 0; overflow: hidden;">
                            <p
                                style="font-size: 0.875rem; font-weight: bold; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($ua) ?>
                            </p>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0;">
                                <?= htmlspecialchars($s['ip_address'] ?? '—') ?> &middot;
                                <?= !empty($s['action_timestamp']) ? date('M d, Y H:i', strtotime($s['action_timestamp'])) : '—' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<script>
    lucide.createIcons();
    function checkStrength(pw) {
        var score = 0;
        var checks = { min8: pw.length >= 8, upper: /[A-Z]/.test(pw), num: /[0-9]/.test(pw) };
        if (checks.min8) score++;
        if (checks.upper) score++;
        if (checks.num) score++;
        if (pw.length >= 12) score++;
        if (/[!@#$%^&*]/.test(pw)) score++;
        var bar = document.getElementById('pwStrengthBar');
        var label = document.getElementById('pwStrengthLabel');
        var colors = ['#ef4444', '#f97316', '#f59e0b', '#22c55e', '#10b981'];
        var labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
        bar.style.width = (score / 5 * 100) + '%';
        bar.style.background = colors[Math.min(score, 4)];
        label.textContent = labels[Math.min(score, 4)];
        label.style.color = colors[Math.min(score, 4)];
        // Update requirement dots
        Object.entries(checks).forEach(function (entry) {
            var el = document.getElementById('req-' + entry[0]);
            if (!el) return;
            var dot = el.querySelector('.req-dot');
            var icon = el.querySelector('[data-lucide]');
            if (entry[1]) {
                dot.style.background = '#10b981';
                dot.style.borderColor = '#10b981';
                if (icon) icon.classList.remove('hidden');
            } else {
                dot.style.background = '';
                dot.style.borderColor = '';
                if (icon) icon.classList.add('hidden');
            }
        });
        lucide.createIcons();
    }
</script>
<?php if ($success): ?>
    <div id="security-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($success) ?></span>
        <button onclick="document.getElementById('security-toast').remove()"
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
            var t = document.getElementById('security-toast');
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