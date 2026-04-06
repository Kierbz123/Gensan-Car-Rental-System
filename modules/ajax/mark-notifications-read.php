<?php
/**
 * POST /modules/ajax/mark-notifications-read.php
 * Records the "last seen" timestamp so the bell badge clears.
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$_SESSION['notifications_last_seen'] = time();
echo json_encode(['success' => true]);
