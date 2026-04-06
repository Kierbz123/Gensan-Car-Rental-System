<?php
/**
 * Document Functions - compliance document helpers
 * Path: modules/compliance/functions/document-functions.php
 */

/**
 * Get the public URL for a stored compliance document
 */
function getDocumentUrl(string $relativePath): string
{
    return BASE_URL . ltrim($relativePath, '/');
}

/**
 * Delete a compliance document file from disk
 */
function deleteDocumentFile(string $relativePath): bool
{
    $fullPath = BASE_PATH . ltrim($relativePath, '/');
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * Generate a preview thumbnail path for uploaded images (returns null for PDFs)
 */
function getDocumentThumbnail(string $relativePath): ?string
{
    $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        return getDocumentUrl($relativePath);
    }
    return null; // PDF or other type - show icon instead
}

/**
 * List all documents for a given compliance record
 */
function getRecordDocuments(int $recordId): array
{
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT * FROM compliance_records WHERE record_id = ?",
        [$recordId]
    );
}
