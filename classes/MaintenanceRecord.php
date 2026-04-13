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
        // Server-side MIME validation — never trust the browser-provided type
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($detectedMime, $allowedMime, true)) {
            throw new Exception("Invalid photo file type. Only JPG, PNG, WebP, and GIF are allowed.");
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExts, true)) {
            throw new Exception("Invalid photo file extension.");
        }

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
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_service
            FROM maintenance_schedules
            WHERE status != 'completed'
        ");
    }

    /**
     * Get maintenance queue/schedules
     */
    public function getAllSchedules($filters = [], $page = 1, $perPage = 20)
    {
        $where = ["s.status != 'completed'"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "s.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['upcoming'])) {
            $where[] = "s.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND s.status = 'active'";
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR s.service_type LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        $whereClause = implode(' AND ', $where);

        $sortBy = 's.next_due_date';
        $sortOrder = 'ASC';

        if (!empty($filters['sort_by'])) {
            $allowedSorts = ['s.service_type', 'v.plate_number', 's.next_due_date', 's.status'];
            if (in_array($filters['sort_by'], $allowedSorts)) {
                $sortBy = $filters['sort_by'];
            }
        }

        if (!empty($filters['sort_order']) && in_array(strtoupper($filters['sort_order']), ['ASC', 'DESC'])) {
            $sortOrder = strtoupper($filters['sort_order']);
        }

        $totalCount = (int) ($this->db->fetchColumn(
            "SELECT COUNT(*) FROM maintenance_schedules s JOIN vehicles v ON s.vehicle_id = v.vehicle_id WHERE {$whereClause}",
            $params
        ) ?? 0);
        
        $offset = ($page - 1) * $perPage;

        $prs = $this->db->fetchAll("
            SELECT s.*, v.plate_number, v.brand, v.model, v.current_status as vehicle_status
            FROM maintenance_schedules s
            JOIN vehicles v ON s.vehicle_id = v.vehicle_id
            WHERE {$whereClause}
            ORDER BY {$sortBy} {$sortOrder}
            LIMIT ? OFFSET ?
        ", array_merge($params, [$perPage, $offset]));

        return [
            'data' => $prs,
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalCount > 0 ? ceil($totalCount / $perPage) : 1
        ];
    }

    /**
     * Fetch combined maintenance logs explicitly linking the search filter string 
     */
    public function getRecentHistory($filters = [])
    {
        $params = [];
        $searchFilter = "";

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $searchFilter = "WHERE (v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR r.service_type LIKE ?)";
            $params = [$search, $search, $search, $search];
        }

        $combinedSql = "
            SELECT 
                log_id as id,
                vehicle_id,
                service_type,
                service_description,
                service_date,
                mileage_at_service,
                status,
                created_at,
                'log' as record_type
            FROM maintenance_logs
            WHERE status = 'completed'
            
            UNION ALL
            
            SELECT 
                schedule_id as id,
                vehicle_id,
                service_type,
                notes as service_description,
                last_service_date as service_date,
                last_service_mileage as mileage_at_service,
                status,
                created_at,
                'schedule' as record_type
            FROM maintenance_schedules
            WHERE status = 'completed'
        ";

        return $this->db->fetchAll(
            "SELECT r.*, v.plate_number, v.brand, v.model
             FROM ($combinedSql) r
             JOIN vehicles v ON r.vehicle_id = v.vehicle_id
             {$searchFilter}
             ORDER BY r.created_at DESC
             LIMIT 15",
            $params
        );
    }
}
