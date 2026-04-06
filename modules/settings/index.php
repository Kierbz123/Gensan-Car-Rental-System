<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$pageTitle = 'Account Settings';
require_once '../../includes/header.php';

$authUser->requirePermission('settings.view');
$db = Database::getInstance();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo "<div class='card'><p>Session expired. Please <a href='../../login.php'>login again</a>.</p></div>";
    require_once '../../includes/footer.php';
    exit;
}

$user = $db->fetchOne("SELECT * FROM users WHERE user_id=?", [$userId]) ?: [];
?>

<div class="page-header">
    <div class="page-title">
        <h1>Account Settings</h1>
        <p>Manage your profile, security, and preferences.</p>
    </div>
</div>

<div class="grid-2">
    <?php
    $cards = [
        ['My Profile', 'Edit your name, email, and contact details.', 'user', 'profile.php'],
        ['Security', 'Change password and 2FA settings.', 'shield', 'security.php'],
        ['Reports', 'Access system analytics.', 'bar-chart-2', '../reports/index.php'],
        ['Audit Logs', 'Review system activity history.', 'file-text', '../compliance/audit-trail/index.php'],
    ];
    if ($authUser->getData()['role'] === 'system_admin') {
        $cards[] = ['User Management', 'Create and manage system user accounts.', 'users', 'user-management.php'];
    }
    foreach ($cards as [$title, $desc, $icon, $href]):
        ?>
        <a href="<?= $href ?>" class="card" style="display:flex; align-items:flex-start; gap:1rem; text-decoration:none;">
            <div class="stat-card-icon primary" style="flex-shrink:0;">
                <i data-lucide="<?= $icon ?>" style="width:20px;height:20px;"></i>
            </div>
            <div>
                <h3 class="card-title" style="margin-bottom:0.25rem;"><?= $title ?></h3>
                <p style="font-size:0.875rem; color:var(--text-muted);"><?= $desc ?></p>
            </div>
            <i data-lucide="chevron-right" style="width:16px;height:16px; margin-left:auto; color:var(--text-muted);"></i>
        </a>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-top:2rem;">
    <div class="card-header">
        <h2 class="card-title">Session Information</h2>
    </div>
    <div class="grid"
        style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-top: var(--space-4);">

        <!-- Username -->
        <div
            style="background: var(--info-50); border: 2px solid #000; border-radius: var(--radius-lg); padding: var(--space-4);">
            <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-2);">
                <div style="padding: var(--space-2); background: var(--info-100); border-radius: var(--radius-md);">
                    <i data-lucide="user" style="width: 20px; height: 20px; color: var(--info-600);"></i>
                </div>
                <span
                    style="font-size: 0.625rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: var(--info-600);">Username</span>
            </div>
            <div style="font-size: 1.25rem; font-weight: 900; color: var(--secondary-900);">
                <?= htmlspecialchars($user['username'] ?? '—') ?>
            </div>
        </div>

        <!-- Role -->
        <div
            style="background: var(--primary-50); border: 2px solid #000; border-radius: var(--radius-lg); padding: var(--space-4);">
            <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-2);">
                <div style="padding: var(--space-2); background: var(--primary-100); border-radius: var(--radius-md);">
                    <i data-lucide="shield" style="width: 20px; height: 20px; color: var(--primary-600);"></i>
                </div>
                <span
                    style="font-size: 0.625rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: var(--primary-600);">Role</span>
            </div>
            <div style="font-size: 1.25rem; font-weight: 900; color: var(--secondary-900);">
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? ''))) ?>
            </div>
        </div>

        <!-- Department -->
        <div
            style="background: var(--warning-50); border: 2px solid #000; border-radius: var(--radius-lg); padding: var(--space-4);">
            <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-2);">
                <div style="padding: var(--space-2); background: var(--warning-100); border-radius: var(--radius-md);">
                    <i data-lucide="briefcase" style="width: 20px; height: 20px; color: var(--warning-600);"></i>
                </div>
                <span
                    style="font-size: 0.625rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: var(--warning-600);">Department</span>
            </div>
            <div style="font-size: 1.25rem; font-weight: 900; color: var(--secondary-900);">
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['department'] ?? ''))) ?>
            </div>
        </div>

        <!-- Last Login -->
        <div
            style="background: var(--success-50); border: 2px solid #000; border-radius: var(--radius-lg); padding: var(--space-4);">
            <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-2);">
                <div style="padding: var(--space-2); background: var(--success-100); border-radius: var(--radius-md);">
                    <i data-lucide="clock" style="width: 20px; height: 20px; color: var(--success-600);"></i>
                </div>
                <span
                    style="font-size: 0.625rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: var(--success-600);">Last
                    Login</span>
            </div>
            <div style="font-size: 1.25rem; font-weight: 900; color: var(--secondary-900);">
                <?= !empty($user['last_login']) ? date('M d, Y', strtotime($user['last_login'])) : '—' ?>
            </div>
        </div>

    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>