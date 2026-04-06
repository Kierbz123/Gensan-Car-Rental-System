<?php
/**
 * AJAX: Upload Compliance Document
 * Path: modules/compliance/ajax/upload-document.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];

if (empty($_FILES['document'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$verification = verifyUpload($_FILES['document'], $allowedTypes);
if (!$verification['valid']) {
    echo json_encode(['success' => false, 'message' => $verification['error']]);
    exit;
}

try {
    $data = [
        'vehicle_id' => (int) $_POST['vehicle_id'],
        'compliance_type' => sanitize($_POST['compliance_type']),
        'document_number' => sanitize($_POST['document_number'] ?? ''),
        'issuing_authority' => sanitize($_POST['issuing_authority'] ?? ''),
        'issue_date' => $_POST['issue_date'] ?? null,
        'expiry_date' => $_POST['expiry_date'] ?? null,
        'renewal_cost' => $_POST['renewal_cost'] ?? null,
        'notes' => sanitize($_POST['notes'] ?? ''),
        'document_file' => $_FILES['document'],
    ];

    $compliance = new ComplianceRecord();
    $recordId = $compliance->addRecord($data, $_SESSION['user_id']);

    echo json_encode(['success' => true, 'record_id' => $recordId, 'message' => 'Document uploaded successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
