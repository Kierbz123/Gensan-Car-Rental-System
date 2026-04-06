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

$baseDir = rtrim(defined('BASE_PATH') ? BASE_PATH : __DIR__ . '/../../', '/');
$filePath = $baseDir . '/' . ltrim($document['file_path'], '/');

if (!file_exists($filePath)) {
    header("HTTP/1.1 404 Not Found");
    exit('File physically missing from server.');
}

// Determine content type
$mimeType = $document['file_type'];

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);

// If download is explicitly requested
if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Disposition: attachment; filename="' . basename($document['title']) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($document['title']) . '"');
}

header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
