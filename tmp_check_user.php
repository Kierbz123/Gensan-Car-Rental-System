<?php
define('GCR_SYSTEM', true);
require_once 'config/config.php';
require_once 'classes/Database.php';

$db = Database::getInstance();
$username = 'admin';
$user = $db->fetchOne(
    "SELECT user_id, username, role, status, password_hash FROM users WHERE username = ?",
    [$username]
);

if ($user) {
    echo "User found:\n";
    echo "ID: " . $user['user_id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Status: " . $user['status'] . "\n";
    echo "Password Match: " . (password_verify('Password123!', $user['password_hash']) ? 'YES' : 'NO') . "\n";
} else {
    echo "User not found.\n";
}
?>
