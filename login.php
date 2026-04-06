<?php
/**
 * Login Portal - Premium Redesign
 */
define('IS_LOGIN_PAGE', true);
require_once 'config/config.php';
require_once 'includes/session-manager.php';

if ($authUser) {
    header("Location: modules/dashboard/index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    try {
        $user = new User();
        $authenticatedUser = $user->authenticate($username, $password);
        if ($authenticatedUser) {
            $selectedRole = $_POST['role'] ?? '';
            if (!empty($selectedRole) && $authenticatedUser['role'] !== $selectedRole) {
                User::logout($authenticatedUser['user_id'], $_SESSION['session_id'] ?? null);
                $error = "Access Denied: Role mismatch for " . strtoupper(str_replace('_', ' ', $selectedRole));
            } else {
                $_SESSION['success_message'] = 'Login successful. Welcome back!';
                header("Location: modules/dashboard/index.php");
                exit;
            }
        } else {
            $error = "Authentication failed. Please check your credentials.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | GCR Admin</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/app.css?v=<?= filemtime(ASSETS_PATH . 'css/app.css') ?>">
    <style>
        /* Role Selection Cards CSS */
        .role-cards-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: var(--space-4);
            margin-top: var(--space-6);
        }

        .role-card {
            position: relative;
            width: calc(50% - (var(--space-4) / 2));
            height: 160px;
            background-color: var(--primary-50);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            perspective: 1000px;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-color);
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            text-decoration: none;
        }

        .role-card svg {
            width: 48px;
            height: 48px;
            color: var(--primary);
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .role-card:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.1);
            border-color: var(--accent);
        }

        .role-card__content {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            padding: var(--space-4);
            box-sizing: border-box;
            background-color: var(--primary-50);
            transform: rotateX(-90deg);
            transform-origin: bottom;
            transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            border: 1px solid var(--accent);
            border-radius: var(--radius-lg);
        }

        .role-card:hover .role-card__content {
            transform: rotateX(0deg);
        }

        .role-card__title {
            margin: 0;
            font-size: 1.125rem;
            color: var(--text-main);
            font-weight: 700;
        }

        .role-card:hover svg {
            transform: scale(0);
        }

        .role-card__description {
            margin: 8px 0 0;
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Hide view states */
        .hidden-view {
            display: none !important;
        }

        .back-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.875rem;
            margin-bottom: var(--space-4);
            padding: 0;
        }

        .back-btn:hover {
            color: var(--text-main);
            text-decoration: underline;
        }

        body {
            background-image: url('<?= ASSETS_URL ?>images/login-bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <i data-lucide="shield-check"></i>
                Gensan Car Rental System
            </div>

            <?php if ($error): ?>
                <div class="error-overlay"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- View 1: Role Selection -->
            <div id="roleSelectionView">
                <p style="text-align: center; color: var(--text-secondary); margin-bottom: var(--space-4);">Select Your
                    Role</p>
                <div class="role-cards-container">
                    <!-- System Admin card removed for security -->

                    <!-- Fleet Manager -->
                    <div class="role-card" onclick="selectRole('fleet_manager')">
                        <i data-lucide="car"></i>
                        <div class="role-card__content">
                            <p class="role-card__title">Fleet Manager</p>
                            <p class="role-card__description">Oversee vehicle inventory, track statuses, and manage
                                fleet availability.</p>
                        </div>
                    </div>

                    <!-- Procurement Officer -->
                    <div class="role-card" onclick="selectRole('procurement_officer')">
                        <i data-lucide="shopping-cart"></i>
                        <div class="role-card__content">
                            <p class="role-card__title">Procurement Officer</p>
                            <p class="role-card__description">Handle parts requests, supplier relationships, and
                                purchase orders.</p>
                        </div>
                    </div>

                    <!-- Maintenance Supervisor -->
                    <div class="role-card" onclick="selectRole('maintenance_supervisor')">
                        <i data-lucide="wrench"></i>
                        <div class="role-card__content">
                            <p class="role-card__title">Maintenance Supervisor</p>
                            <p class="role-card__description">Schedule preventative maintenance, assign mechanics, and
                                review repair logs.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View 2: Login Form -->
            <div id="loginFormView" class="hidden-view">
                <button type="button" class="back-btn" onclick="showRoleSelection()">
                    <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i> Back to roles
                </button>
                <p id="selectedRoleText"
                    style="text-align: center; font-weight: 600; color: var(--accent); margin-bottom: var(--space-4);">
                </p>

                <form method="POST" id="loginForm">
                    <div class="form-group">
                        <label for="username">System Identity (UID)</label>
                        <input type="text" name="username" id="username" class="form-control" required autofocus
                            placeholder="Enter username">
                    </div>

                    <div class="form-group" style="position:relative;">
                        <label for="password">Security Key</label>
                        <div style="position:relative;">
                            <input type="password" name="password" id="password" class="form-control" required
                                placeholder="Enter password">
                            <button type="button" onclick="togglePassword('password', 'pToggle')"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.6);cursor:pointer;padding:4px;">
                                <i id="pToggle" data-lucide="eye" style="width:20px;height:20px;"></i>
                            </button>
                        </div>
                    </div>

                    <input type="hidden" name="role" id="role" value="">

                    <button type="submit" class="btn btn-auth">Log-In</button>
                </form>
            </div>


        </div>
    </div>

    <script src="<?= ASSETS_URL ?>js/lucide.min.js"></script>
    <script>
        lucide.createIcons();

        function togglePassword(id, iconId) {
            const input = document.getElementById(id);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }

        function selectRole(roleCode) {
            // Set the hidden input value
            document.getElementById('role').value = roleCode;

            // Format role name for display
            const roleName = roleCode.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            document.getElementById('selectedRoleText').textContent = roleName + ' Login';

            // Switch views
            document.getElementById('roleSelectionView').classList.add('hidden-view');
            document.getElementById('loginFormView').classList.remove('hidden-view');

            // Focus username field
            setTimeout(() => document.getElementById('username').focus(), 100);
        }

        function showRoleSelection() {
            // Clear inputs
            document.getElementById('role').value = '';
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';

            // Switch views
            document.getElementById('loginFormView').classList.add('hidden-view');
            document.getElementById('roleSelectionView').classList.remove('hidden-view');
        }

        // Handle hidden admin access via URL parameter
        window.onload = function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('access') === 'admin') {
                selectRole('system_admin');
                return;
            }

            <?php if ($error): ?>
                // If there's an error, it means form was submitted, so show login view
                const submittedRole = "<?= htmlspecialchars($_POST['role'] ?? '') ?>";
                if (submittedRole) {
                    selectRole(submittedRole);
                } else {
                    document.getElementById('loginFormView').classList.remove('hidden-view');
                    document.getElementById('roleSelectionView').classList.add('hidden-view');
                }

                // Hide error message after 5 seconds
                const errorOverlay = document.querySelector('.error-overlay');
                if (errorOverlay) {
                    setTimeout(() => {
                        errorOverlay.style.transition = 'opacity 0.5s ease';
                        errorOverlay.style.opacity = '0';
                        setTimeout(() => errorOverlay.remove(), 500);
                    }, 5000);
                }
            <?php endif; ?>
        };


    </script>
</body>

</html>