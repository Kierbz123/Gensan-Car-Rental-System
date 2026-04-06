<?php
// modules/backups/action.php
set_time_limit(0); // Prevent script timeout
ini_set('memory_limit', '512M'); // Increase memory limit for large backups
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/BackupManager.php';

// Only System Admin can perform these actions
if ($authUser->getData()['role'] !== 'system_admin') {
    if (isset($_GET['action']) && $_GET['action'] === 'download') {
        die("Unauthorized access.");
    }
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$backupManager = new BackupManager();

try {
    if ($action === 'create') {
        $backupId = $backupManager->createBackup('manual', $authUser->getId());
        echo json_encode(['success' => true, 'backup_id' => $backupId]);
        exit;
    }

    if ($action === 'restore') {
        $id = $_POST['id'] ?? '';
        if (!$id) throw new Exception("Backup ID required");
        $backupManager->restoreBackup($id, $authUser->getId());
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (!$id) throw new Exception("Backup ID required");
        $backupManager->deleteBackup($id, $authUser->getId());
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'download') {
        $id = $_GET['id'] ?? '';
        if (!$id) throw new Exception("Backup ID required");
        
        $backup = $backupManager->getBackupById($id);
        if (!$backup || $backup['status'] !== 'completed') {
            die("Backup not found or incomplete.");
        }
        
        $filepath = BACKUPS_PATH . $backup['filename'];
        if (!file_exists($filepath)) {
            die("File not found on server.");
        }
        
        // Log the download action
        if (class_exists('AuditLogger')) {
            AuditLogger::log($authUser->getId(), null, null, 'download', 'settings', 'backups', $id, "Downloaded database backup: {$id}", null, null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, 'GET', '/settings/backups/download', 'info');
        }
        
        // Serve file for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    throw new Exception("Invalid action.");

} catch (Exception $e) {
    if ($action === 'download') {
        die("Error: " . $e->getMessage());
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
