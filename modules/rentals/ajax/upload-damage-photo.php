<?php
// modules/rentals/ajax/upload-damage-photo.php
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!$authUser->hasPermission('rentals.update')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$agreementId = (int) ($_POST['agreement_id'] ?? 0);
if (!$agreementId) {
    echo json_encode(['success' => false, 'message' => 'Agreement ID required.']);
    exit;
}

// Verify agreement exists
$db = Database::getInstance();
$exists = $db->fetchColumn("SELECT COUNT(*) FROM rental_agreements WHERE agreement_id = ?", [$agreementId]);
if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Agreement not found.']);
    exit;
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errMsg = match ($_FILES['photo']['error'] ?? -1) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large (max 5MB).',
        UPLOAD_ERR_NO_FILE => 'No file selected.',
        default => 'Upload error code: ' . ($_FILES['photo']['error'] ?? 'unknown'),
    };
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

// Validate MIME
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['photo']['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mimeType, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPEG, PNG, WebP images, and PDFs are allowed.']);
    exit;
}

// Validate size (5MB)
if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File exceeds 5 MB limit.']);
    exit;
}

// Build target directory: DAMAGE_PHOTOS_PATH/{agreement_id}/
$targetDir = rtrim(DAMAGE_PHOTOS_PATH, '/') . '/' . $agreementId . '/';
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

$ext = match ($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    default => 'jpg',
};
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$targetPath = $targetDir . $filename;

if (!move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

// Build URL relative to BASE_URL
$relativePath = 'assets/images/uploads/damage-photos/' . $agreementId . '/' . $filename;
$thumbUrl = BASE_URL . $relativePath;

echo json_encode([
    'success' => true,
    'path' => $relativePath,
    'thumb_url' => $thumbUrl,
    'filename' => $filename,
]);
