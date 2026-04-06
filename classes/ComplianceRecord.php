<?php
// /var/www/html/gensan-car-rental-system/classes/ComplianceRecord.php

/**
 * Compliance and Regulatory Record Management
 */

class ComplianceRecord
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Add compliance record
     */
    public function addRecord($data, $createdBy)
    {
        // Handle document upload
        $documentPath = null;
        if (!empty($data['document_file']) && $data['document_file']['tmp_name']) {
            $documentPath = $this->uploadDocument($data['document_file'], $data['vehicle_id'], $data['compliance_type']);
        }

        $recordId = $this->db->insert(
            "INSERT INTO compliance_records 
             (vehicle_id, compliance_type, document_number, issuing_authority,
              issue_date, expiry_date, renewal_cost, document_file_path, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['vehicle_id'],
                $data['compliance_type'],
                $data['document_number'],
                $data['issuing_authority'] ?? null,
                $data['issue_date'],
                $data['expiry_date'],
                $data['renewal_cost'] ?? null,
                $documentPath,
                $data['notes'] ?? null,
                $createdBy
            ]
        );

        return $recordId;
    }

    /**
     * Get expiring compliance records
     */
    public function getExpiring($days = 30)
    {
        return $this->db->fetchAll(
            "SELECT cr.*, v.plate_number, v.brand, v.model,
                    DATEDIFF(cr.expiry_date, CURDATE()) as days_remaining
             FROM compliance_records cr
             JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
             WHERE cr.status IN ('active', 'renewal_pending')
             AND cr.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             AND cr.expiry_date >= CURDATE()
             AND v.deleted_at IS NULL
             AND v.current_status != 'retired'
             ORDER BY cr.expiry_date ASC",
            [$days]
        );
    }

    /**
     * Get expired compliance records
     */
    public function getExpired()
    {
        return $this->db->fetchAll(
            "SELECT cr.*, v.plate_number, v.brand, v.model
             FROM compliance_records cr
             JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
             WHERE cr.expiry_date < CURDATE()
             AND cr.status = 'expired'
             AND v.deleted_at IS NULL
             AND v.current_status != 'retired'
             ORDER BY cr.expiry_date DESC"
        );
    }

    /**
     * Mark record as renewed
     */
    public function markRenewed($oldRecordId, $newRecordId, $updatedBy)
    {
        $this->db->execute(
            "UPDATE compliance_records 
             SET status = 'renewed', renewed_record_id = ?
             WHERE record_id = ?",
            [$newRecordId, $oldRecordId]
        );

        return true;
    }

    /**
     * Upload compliance document
     */
    private function uploadDocument($file, $vehicleId, $complianceType)
    {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $vehicleId . '_' . $complianceType . '_' . time() . '.' . $extension;
        $filepath = DOCUMENTS_PATH . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Failed to upload document.");
        }

        return str_replace(BASE_PATH, '', $filepath);
    }

    /**
     * Check and update compliance statuses
     * Should be run daily via cron job
     */
    public function updateStatuses()
    {
        // Update records that have expired
        $this->db->execute(
            "UPDATE compliance_records 
             SET status = 'expired'
             WHERE expiry_date < CURDATE() 
             AND status = 'active'"
        );

        // Update records entering renewal window
        $this->db->execute(
            "UPDATE compliance_records 
             SET status = 'renewal_pending'
             WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             AND expiry_date >= CURDATE()
             AND status = 'active'"
        );

        // Create notifications for expiring records
        $expiring = $this->getExpiring(7); // Critical: 7 days

        foreach ($expiring as $record) {
            // Check if notification already sent
            $existing = $this->db->fetchOne(
                "SELECT notification_id FROM notifications 
                 WHERE type = 'compliance_expiring' 
                 AND related_record_id = ?
                 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$record['record_id']]
            );

            if (!$existing) {
                // Notify fleet managers and admins
                $users = $this->db->fetchAll(
                    "SELECT user_id FROM users 
                     WHERE role IN (?, ?) AND status = 'active'",
                    [ROLE_FLEET_MANAGER, ROLE_SYSTEM_ADMIN]
                );

                foreach ($users as $user) {
                    $this->db->execute(
                        "INSERT INTO notifications 
                         (user_id, type, title, message, related_module, related_record_id)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $user['user_id'],
                            'compliance_expiring',
                            'Compliance Document Expiring Soon',
                            "{$record['compliance_type']} for {$record['plate_number']} expires in {$record['days_remaining']} days",
                            'compliance',
                            $record['record_id']
                        ]
                    );
                }
            }
        }

        return true;
    }
}
