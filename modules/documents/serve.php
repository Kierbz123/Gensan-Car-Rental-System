<?php
// modules/documents/serve.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/DocumentManager.php';

if (!$authUser) {
    header("HTTP/1.1 401 Unauthorized");
    exit('Unauthorized access.');
}

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId) {
    header("HTTP/1.1 400 Bad Request");
    exit('Invalid document ID.');
}

$document = DocumentManager::getDocument($documentId);
if (!$document) {
    header("HTTP/1.1 404 Not Found");
    exit('Document not found or archived.');
}

// TODO: Further role-base checking
// Example: if ($document['entity_type'] == 'vehicle' && !$authUser->hasPermission('vehicles.view')) { ... }

$baseDir  = rtrim(defined('BASE_PATH') ? BASE_PATH : __DIR__ . '/../../', '/');
$filePath = $baseDir . '/' . ltrim($document['file_path'], '/');

// Guard against directory traversal: resolved path must stay inside STORAGE_DIR
$allowedBase = realpath($baseDir . '/' . DocumentManager::STORAGE_DIR);
$resolvedFile = realpath($filePath);
if (!$resolvedFile || !$allowedBase || strpos($resolvedFile, $allowedBase) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    exit('Access denied.');
}

if (!file_exists($resolvedFile)) {
    header("HTTP/1.1 404 Not Found");
    exit('File physically missing from server.');
}

// Use server-side finfo for MIME — never trust the DB value alone
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $resolvedFile) ?: 'application/octet-stream';
finfo_close($finfo);

// Safe download filename: use the stored basename, not the user-supplied title
$safeFilename = basename($resolvedFile);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);

// If download is explicitly requested
if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $safeFilename . '"');
}

header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($resolvedFile));
readfile($resolvedFile);
exit;
