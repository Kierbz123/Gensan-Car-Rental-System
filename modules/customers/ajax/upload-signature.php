<?php
/**
 * AJAX: Upload Customer Signature
 * Path: modules/customers/ajax/upload-signature.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$agreementId = (int) ($_POST['agreement_id'] ?? 0);
$signatureData = $_POST['signature_data'] ?? ''; // Base64 PNG from signature pad

if (!$agreementId || !$signatureData) {
    echo json_encode(['success' => false, 'message' => 'Agreement ID and signature data required']);
    exit;
}

// Decode base64 image
$imageData = preg_replace('/^data:image\/\w+;base64,/', '', $signatureData);
$imageBytes = base64_decode($imageData);
$filename = 'sig_' . $agreementId . '_' . time() . '.png';
$savePath = UPLOAD_PATH . 'signatures/' . $filename;

if (!is_dir(dirname($savePath))) {
    mkdir(dirname($savePath), 0755, true);
}

if (file_put_contents($savePath, $imageBytes) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save signature']);
    exit;
}

$relativePath = 'uploads/signatures/' . $filename;

$db = Database::getInstance();
$db->execute(
    "UPDATE rental_agreements SET customer_signature_path = ?, updated_at = NOW() WHERE agreement_id = ?",
    [$relativePath, $agreementId]
);

echo json_encode(['success' => true, 'path' => BASE_URL . $relativePath]);
