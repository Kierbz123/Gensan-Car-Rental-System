<?php
/**
 * AJAX: Mark Compliance Record as Renewed
 * Path: modules/compliance/ajax/mark-renewed.php
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
$oldRecordId = (int) ($input['old_record_id'] ?? 0);
$newRecordId = (int) ($input['new_record_id'] ?? 0);

if (!$oldRecordId || !$newRecordId) {
    echo json_encode(['success' => false, 'message' => 'Both record IDs are required']);
    exit;
}

try {
    $compliance = new ComplianceRecord();
    $compliance->markRenewed($oldRecordId, $newRecordId, $_SESSION['user_id']);
    echo json_encode(['success' => true, 'message' => 'Record marked as renewed']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
