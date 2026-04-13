<?php
/**
 * Backup and Recovery Manager
 * Handles generating taking database dumps and restoring them
 */

class BackupManager
{
    private $db;
    private $backupPath;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->backupPath = BACKUPS_PATH;

        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Create a backup of the database
     */
    public function createBackup($type = 'manual', $userId = null)
    {
        $backupId = 'BKP-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -4));
        $filename = $backupId . '.sql';
        $filepath = $this->backupPath . $filename;

        // Insert initial tracking record
        $this->db->execute(
            "INSERT INTO backups (backup_id, filename, file_size, backup_type, status, created_by) 
             VALUES (?, ?, 0, ?, 'in_progress', ?)",
            [$backupId, $filename, $type, $userId]
        );

        $fp = null;
        try {
            $fp = fopen($filepath, 'w');
            if (!$fp) {
                throw new Exception("Failed to open backup file for writing.");
            }

            $tables = [];
            $result = $this->db->fetchAll("SHOW TABLES");
            foreach ($result as $row) {
                $tables[] = array_values($row)[0];
            }

            fwrite($fp, "-- Gensan Car Rental System Database Backup\n");
            fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($fp, "-- Backup ID: {$backupId}\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+00:00';\n\n");

            foreach ($tables as $table) {
                // Get table creation
                $createResult = $this->db->fetchOne("SHOW CREATE TABLE `{$table}`");
                fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fp, $createResult['Create Table'] . ";\n\n");

                // Get table data
                $rows = $this->db->fetchAll("SELECT * FROM `{$table}`");
                if (!empty($rows)) {
                    $insertPrefix = "INSERT INTO `{$table}` VALUES ";
                    $batchValues = [];
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $val) {
                            if (is_null($val)) {
                                $values[] = "NULL";
                            } else {
                                $values[] = $this->db->getConnection()->quote((string) $val);
                            }
                        }
                        $batchValues[] = "(" . implode(",", $values) . ")";
                        
                        // Batch inserts to prevent massive statements
                        if (count($batchValues) >= 100) {
                            fwrite($fp, $insertPrefix . implode(", ", $batchValues) . ";\n");
                            $batchValues = [];
                        }
                    }
                    if (count($batchValues) > 0) {
                        fwrite($fp, $insertPrefix . implode(", ", $batchValues) . ";\n");
                    }
                    fwrite($fp, "\n");
                }
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);
            $fp = null;

            $fileSize = filesize($filepath);

            // Update record as completed
            $this->db->execute(
                "UPDATE backups SET status = 'completed', file_size = ? WHERE backup_id = ?",
                [$fileSize, $backupId]
            );

            // Log event if AuditLogger exists
            if (class_exists('AuditLogger') && $userId) {
                AuditLogger::log($userId, null, null, 'create', 'settings', 'backups', $backupId, "Created database backup: {$backupId}", null, null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, 'POST', '/settings/backups', 'info');
            }

            return $backupId;

        } catch (Exception $e) {
            if ($fp) fclose($fp);
            // Mark failed
            $this->db->execute(
                "UPDATE backups SET status = 'failed' WHERE backup_id = ?",
                [$backupId]
            );
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            throw new Exception("Backup failed: " . $e->getMessage());
        }
    }

    /**
     * Restore database from a backup ID
     */
    public function restoreBackup($backupId, $restoredBy = null)
    {
        $backup = $this->db->fetchOne("SELECT * FROM backups WHERE backup_id = ?", [$backupId]);
        if (!$backup) {
            throw new Exception("Backup record not found.");
        }

        $filepath = $this->backupPath . $backup['filename'];
        if (!file_exists($filepath)) {
            throw new Exception("Backup file missing from storage.");
        }

        // We use native PDO prepare and execute to run large chunks.
        // Reading the whole file at once is fine for small/medium DBs.
        $sql = file_get_contents($filepath);
        if ($sql === false) {
            throw new Exception("Failed to read backup file.");
        }

        try {
            // Use exec on the connection directly since prepared statements don't support multi-query
            $this->db->getConnection()->exec($sql);

            if (class_exists('AuditLogger') && $restoredBy) {
                AuditLogger::log($restoredBy, null, null, 'restore', 'settings', 'backups', $backupId, "Restored database from backup: {$backupId}", null, null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, 'POST', '/settings/backups/restore', 'warning');
            }

            return true;

        } catch (Exception $e) {
            throw new Exception("Restoration failed, database may be in an inconsistent state. Error: " . $e->getMessage());
        }
    }

    /**
     * Delete a backup
     */
    public function deleteBackup($backupId, $deletedBy = null)
    {
        $backup = $this->db->fetchOne("SELECT filename FROM backups WHERE backup_id = ?", [$backupId]);
        if (!$backup) {
            return false;
        }

        $filepath = $this->backupPath . $backup['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $this->db->execute("DELETE FROM backups WHERE backup_id = ?", [$backupId]);

        if (class_exists('AuditLogger') && $deletedBy) {
            AuditLogger::log($deletedBy, null, null, 'delete', 'settings', 'backups', $backupId, "Deleted database backup: {$backupId}", null, null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, 'POST', '/settings/backups/delete', 'info');
        }

        return true;
    }

    /**
     * Get list of backups
     */
    public function getBackups()
    {
        return $this->db->fetchAll(
            "SELECT b.*, u.first_name, u.last_name 
             FROM backups b 
             LEFT JOIN users u ON b.created_by = u.user_id 
             ORDER BY b.created_at DESC"
        );
    }
    
    /**
     * Get detailed info for a backup
     */
    public function getBackupById($backupId)
    {
        return $this->db->fetchOne("SELECT * FROM backups WHERE backup_id = ?", [$backupId]);
    }
}
