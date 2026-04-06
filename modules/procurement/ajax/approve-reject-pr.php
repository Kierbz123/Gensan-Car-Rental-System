<?php
/**
 * AJAX: Approve or Reject Procurement Request
 * Path: modules/procurement/ajax/approve-reject-pr.php
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
$action = $input['action'] ?? ''; // 'approve' or 'reject'
$comments = sanitize($input['comments'] ?? '');

if (!$prId || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'PR ID and valid action required']);
    exit;
}

try {
    $pr = new ProcurementRequest($prId);
    if ($action === 'approve') {
        $pr->processApproval($prId, $_SESSION['user_id'], 'approve', $comments);
        $message = 'Purchase request approved.';
    } else {
        $pr->processApproval($prId, $_SESSION['user_id'], 'reject', $comments);
        $message = 'Purchase request rejected.';
    }
    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
