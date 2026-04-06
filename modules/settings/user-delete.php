<?php
/**
 * Delete User Account Handler
 * Path: modules/settings/user-delete.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

// Strict administrative access
$authUser->requirePermission('system_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user-management.php");
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = "Invalid security token.";
    header("Location: user-management.php");
    exit;
}

$userId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if (!$userId) {
    header("Location: user-management.php");
    exit;
}

// Check if trying to delete self
if ($userId === (int) $_SESSION['user_id']) {
    $_SESSION['error_message'] = "You cannot delete your own account.";
    header("Location: user-management.php");
    exit;
}

try {
    $userObj = new User();
    $userObj->softDelete($userId, $_SESSION['user_id']);

    $_SESSION['success_message'] = "User account has been deleted.";
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
}

header("Location: user-management.php");
exit;
