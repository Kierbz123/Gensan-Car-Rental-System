<?php
/**
 * Logout Handler
 * Path: logout.php
 */
require_once 'config/config.php';
require_once 'includes/session-manager.php';

// $authUser is provided by session-manager.php
if ($authUser) {
    User::logout($authUser->getId(), $_SESSION['session_id'] ?? null);
} else {
    User::logout();
}

header("Location: login.php?msg=logged_out");
exit;
