<?php
// classes/DocumentManager.php

/**
 * Handles uploading, serving, and searching managed documents
 */
class DocumentManager
{
    private static $db;
    
    // Directory relative to root
    const STORAGE_DIR = 'assets/uploads/managed_documents/';

    public static function init()
    {
        if (!self::$db) {
            self::$db = Database::getInstance();
        }
    }

    /**
     * Upload and register a new document
     * 
     * @param array $fileData The $_FILES array item
     * @param string $entityType e.g., 'customer', 'vehicle'
     * @param string $entityId e.g., 'CUST-0001'
     * @param string $category e.g., 'insurance', 'identity'
     * @param string|null $title Custom title, defaults to original filename
     * @param int|null $userId ID of user uploading
     * @param string|null $expiresAt Date format YYYY-MM-DD
     * @return int The new document ID
     * @throws Exception if upload or DB insert fails
     */
    public static function uploadDocument($fileData, $entityType, $entityId, $category, $title = null, $userId = null, $expiresAt = null)
    {
        self::init();

        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error code: " . $fileData['error']);
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($fileData['size'] > $maxSize) {
            throw new Exception("File exceeds maximum allowed size of 10MB.");
        }

        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // docx
        ];
        
        $mimeType = mime_content_type($fileData['tmp_name']);
        if (!in_array($mimeType, $allowedMimeTypes)) {
            // Also check standard extensions
            $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            if (!in_array($ext, $allowedExts)) {
                throw new Exception("Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.");
            }
        }

        // Generate a secure unique filename
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $safeFilename = uniqid('doc_') . '_' . bin2hex(random_bytes(8)) . '.' . strtolower($extension);
        
        // Ensure directory exists
        $uploadPath = rtrim(defined('BASE_PATH') ? BASE_PATH : __DIR__ . '/../', '/') . '/' . self::STORAGE_DIR;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $fullPath = $uploadPath . $safeFilename;
        $dbPath = self::STORAGE_DIR . $safeFilename;

        if (!move_uploaded_file($fileData['tmp_name'], $fullPath)) {
            throw new Exception("Failed to move uploaded file to secure storage.");
        }

        $title = !empty($title) ? $title : $fileData['name'];
        $expiresAt = !empty($expiresAt) ? $expiresAt : null;

        // Save to DB
        self::$db->execute(
            "INSERT INTO documents (entity_type, entity_id, document_category, title, file_path, file_type, file_size, uploaded_by, expires_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $entityType,
                $entityId,
                $category,
                trim($title),
                $dbPath,
                $mimeType ?: $fileData['type'],
                $fileData['size'],
                $userId,
                $expiresAt
            ]
        );

        $docId = self::$db->lastInsertId();

        // Audit Logging
        if (class_exists('AuditLogger')) {
            try {
                // We need more context for AuditLogger usually, but we'll use what we have
                $ipStr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $uaStr = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                
                AuditLogger::log(
                    $userId,
                    null, // Name will be fetched by AuditLogger if null usually
                    null, // Role will be fetched if possible
                    'upload',
                    'documents',
                    'documents',
                    $docId,
                    "Uploaded document: {$title} for {$entityType} ID: {$entityId}",
                    null,
                    json_encode(['entity_type' => $entityType, 'entity_id' => $entityId, 'category' => $category, 'title' => $title]),
                    $ipStr,
                    $uaStr,
                    $_SERVER['REQUEST_METHOD'] ?? 'POST',
                    $_SERVER['REQUEST_URI'] ?? '/modules/documents/upload',
                    'info'
                );
            } catch (Exception $e) {
                error_log("Audit logging failed in DocumentManager: " . $e->getMessage());
            }
        }

        return $docId;
    }

    /**
     * Get documents for a specific entity
     */
    public static function getDocumentsByEntity($entityType, $entityId)
    {
        self::init();
        return self::$db->fetchAll(
            "SELECT d.*, 
                    COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'System') as uploader_name
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.user_id
             WHERE d.entity_type = ? AND d.entity_id = ? AND d.status = 'active'
             ORDER BY d.uploaded_at DESC",
            [$entityType, $entityId]
        );
    }

    /**
     * Search and filter documents (Admin view)
     */
    public static function searchDocuments($filters = [], $page = 1, $perPage = 25)
    {
        self::init();

        $where = ["d.status = 'active'"];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(d.title LIKE ? OR d.entity_id LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['entity_type'])) {
            $where[] = "d.entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $where[] = "d.entity_id = ?";
            $params[] = $filters['entity_id'];
        }

        if (!empty($filters['category'])) {
            $cats = explode(',', $filters['category']);
            if (count($cats) > 1) {
                $placeholders = implode(',', array_fill(0, count($cats), '?'));
                $where[] = "d.document_category IN ($placeholders)";
                foreach ($cats as $c) $params[] = trim($c);
            } else {
                $where[] = "d.document_category = ?";
                $params[] = $filters['category'];
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = "d.uploaded_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "d.uploaded_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        $count = self::$db->fetchColumn(
            "SELECT COUNT(*) FROM documents d WHERE {$whereClause}",
            $params
        );

        $offset = ($page - 1) * $perPage;

        $docs = self::$db->fetchAll(
            "SELECT d.*, 
                    COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'System') as uploader_name
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.user_id
             WHERE {$whereClause}
             ORDER BY d.uploaded_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $docs,
            'total' => $count,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($count / $perPage)
        ];
    }

    /**
     * Retrieve a document record by ID
     */
    public static function getDocument($documentId)
    {
        self::init();
        return self::$db->fetchOne(
            "SELECT * FROM documents WHERE document_id = ? AND status = 'active'",
            [$documentId]
        );
    }


    /**
     * Mark a document as archived (soft delete)
     */
    public static function archiveDocument($documentId, $userId = null)
    {
        self::init();
        $result = self::$db->execute(
            "UPDATE documents SET status = 'archived' WHERE document_id = ?",
            [$documentId]
        );

        // Audit Logging
        if ($result && class_exists('AuditLogger')) {
            try {
                $ipStr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $uaStr = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                
                AuditLogger::log(
                    $userId,
                    null,
                    null,
                    'archive',
                    'documents',
                    'documents',
                    $documentId,
                    "Archived document ID: {$documentId}",
                    json_encode(['status' => 'active']),
                    json_encode(['status' => 'archived']),
                    $ipStr,
                    $uaStr,
                    $_SERVER['REQUEST_METHOD'] ?? 'POST',
                    $_SERVER['REQUEST_URI'] ?? '/modules/documents/archive',
                    'warning'
                );
            } catch (Exception $e) {
                error_log("Audit logging failed in DocumentManager archive: " . $e->getMessage());
            }
        }

        return $result;
    }
}
