<?php
// /var/www/html/gensan-car-rental-system/classes/MaintenanceRecord.php

/**
 * Maintenance and Service Record Management
 */
class MaintenanceRecord
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new maintenance log entry
     */
    public function create($data, $createdBy)
    {
        $this->db->beginTransaction();

        try {
            // Normalise mileage key — accept both naming conventions
            $mileage = $data['mileage_at_service'] ?? $data['odometer_reading'] ?? 0;

            $logId = $this->db->insert(
                "INSERT INTO maintenance_logs
                 (vehicle_id, service_date, service_type, service_description,
                  mileage_at_service, labor_cost, parts_cost, other_costs,
                  status, next_service_recommended_date, next_service_recommended_mileage,
                  notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['vehicle_id'],
                    $data['service_date'],
                    $data['service_type'],
                    // service_description is NOT NULL — fall back to notes if not provided
                    $data['service_description'] ?? $data['notes'] ?? '',
                    $mileage,
                    $data['labor_cost'] ?? 0,
                    $data['parts_cost'] ?? 0,
                    $data['other_costs'] ?? 0,
                    $data['status'] ?? 'completed',
                    // Accept both old and new key names for forwards compat
                    $data['next_service_recommended_date'] ?? $data['next_service_due_date'] ?? null,
                    $data['next_service_recommended_mileage'] ?? $data['next_service_due_mileage'] ?? null,
                    $data['notes'] ?? null,
                    $createdBy
                ]
            );

            // Update vehicle mileage if the new reading is higher
            $this->db->execute(
                "UPDATE vehicles SET mileage = GREATEST(mileage, ?), updated_at = NOW() WHERE vehicle_id = ?",
                [$mileage, $data['vehicle_id']]
            );

            // Handle photos if any
            if (!empty($data['photos']) && is_array($data['photos'])) {
                foreach ($data['photos'] as $index => $photo) {
                    if (!empty($photo['tmp_name'])) {
                        $path = $this->uploadPhoto($photo, $data['vehicle_id'], $logId, $index);
                        $this->db->execute(
                            "INSERT INTO maintenance_photos (log_id, photo_path, description) VALUES (?, ?, ?)",
                            [$logId, $path, $photo['description'] ?? 'Service photo']
                        );
                    }
                }
            }

            // Update maintenance schedule if this was a scheduled service
            if (!empty($data['schedule_id'])) {
                $this->db->execute(
                    "UPDATE maintenance_schedules
                     SET last_service_date    = ?,
                         last_service_mileage = ?,
                         last_maintenance_id  = ?,
                         next_due_date        = ?,
                         next_due_mileage     = ?,
                         status               = 'completed'
                     WHERE schedule_id = ?",
                    [
                        $data['service_date'],
                        $mileage,
                        $logId,
                        $data['next_service_recommended_date'] ?? $data['next_service_due_date'] ?? null,
                        $data['next_service_recommended_mileage'] ?? $data['next_service_due_mileage'] ?? null,
                        $data['schedule_id']
                    ]
                );
            }

            $this->db->commit();

            // Log audit
            if (class_exists('AuditLogger')) {
                AuditLogger::log(
                    $createdBy,
                    null,
                    null,
                    'create',
                    'maintenance',
                    'maintenance_logs',
                    $logId,
                    "Logged maintenance for vehicle {$data['vehicle_id']}",
                    null,
                    json_encode(['vehicle_id' => $data['vehicle_id'], 'total_cost' => ($data['labor_cost'] ?? 0) + ($data['parts_cost'] ?? 0) + ($data['other_costs'] ?? 0)]),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'POST',
                    '/maintenance/create',
                    'info'
                );
            }

            return $logId;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get maintenance history for a vehicle
     */
    public function getHistory($vehicleId)
    {
        return $this->db->fetchAll(
            "SELECT ml.*, CONCAT(u.first_name, ' ', u.last_name) as logger_name
             FROM maintenance_logs ml
             LEFT JOIN users u ON ml.created_by = u.user_id
             WHERE ml.vehicle_id = ?
             ORDER BY ml.service_date DESC",
            [$vehicleId]
        );
    }

    /**
     * Upload maintenance photo
     */
    private function uploadPhoto($file, $vehicleId, $logId, $index)
    {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $vehicleId . '_maint_' . $logId . '_' . $index . '_' . time() . '.' . $extension;
        $filepath = MAINTENANCE_PHOTOS_PATH . $filename;

        if (!is_dir(MAINTENANCE_PHOTOS_PATH)) {
            mkdir(MAINTENANCE_PHOTOS_PATH, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Failed to upload maintenance photo.");
        }

        return str_replace(BASE_PATH, '', $filepath);
    }

    /**
     * Get maintenance statistics
     */
    public function getStats()
    {
        return $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_active,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'active' THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as in_service
            FROM maintenance_schedules
        ");
    }

    /**
     * Get maintenance queue/schedules
     */
    public function getAllSchedules($filters = [])
    {
        $where = ["s.status != 'completed'"];
        $params = [];

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll("
            SELECT s.*, v.plate_number, v.brand, v.model, v.current_status as vehicle_status
            FROM maintenance_schedules s
            JOIN vehicles v ON s.vehicle_id = v.vehicle_id
            WHERE $whereClause
            ORDER BY s.next_due_date ASC
        ", $params);
    }
}
