<?php
/**
 * AJAX: Update PR Status
 * Path: modules/procurement/ajax/update-pr-status.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$prId = (int) ($input['pr_id'] ?? 0);
$status = sanitize($input['status'] ?? '');

$allowed = ['draft', 'pending_approval', 'approved', 'rejected', 'ordered', 'received', 'cancelled'];

if (!$prId || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid PR ID or status']);
    exit;
}

$db = Database::getInstance();
$db->execute(
    "UPDATE procurement_requests SET status = ?, updated_at = NOW(), updated_by = ? WHERE procurement_id = ?",
    [$status, $_SESSION['user_id'], $prId]
);

echo json_encode(['success' => true, 'message' => 'PR status updated to ' . $status]);
