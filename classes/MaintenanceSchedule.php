<?php
// /var/www/html/gensan-car-rental-system/classes/MaintenanceSchedule.php

/**
 * Maintenance Schedule and Record Management
 */

class MaintenanceSchedule
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get upcoming maintenance across fleet
     */
    public function getUpcoming($days = 30, $vehicleId = null)
    {
        $sql = "SELECT ms.*, v.plate_number, v.brand, v.model,
                       DATEDIFF(ms.next_due_date, CURDATE()) as days_until_due
                FROM maintenance_schedules ms
                JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
                WHERE ms.status IN ('scheduled', 'active')
                  AND DATEDIFF(ms.next_due_date, CURDATE()) <= ? 
                  AND DATEDIFF(ms.next_due_date, CURDATE()) >= 0";
        $params = [$days];

        if ($vehicleId) {
            $sql .= " AND ms.vehicle_id = ?";
            $params[] = $vehicleId;
        }

        $sql .= " ORDER BY days_until_due ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get overdue maintenance
     */
    public function getOverdue()
    {
        return $this->db->fetchAll(
            "SELECT ms.*, v.plate_number, v.brand, v.model,
                    DATEDIFF(CURDATE(), ms.next_due_date) as days_overdue
             FROM maintenance_schedules ms
             JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
             WHERE ms.status IN ('scheduled', 'active', 'overdue')
               AND ms.next_due_date < CURDATE()
             ORDER BY ms.next_due_date ASC"
        );
    }

    /**
     * Create maintenance schedule
     */
    public function createSchedule($data, $createdBy)
    {
        $this->db->execute(
            "INSERT INTO maintenance_schedules 
             (vehicle_id, service_type, schedule_basis, interval_months, 
              interval_mileage, next_due_date, next_due_mileage, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['vehicle_id'],
                $data['service_type'],
                $data['schedule_basis'],
                $data['interval_months'] ?? null,
                $data['interval_mileage'] ?? null,
                $data['next_due_date'] ?? null,
                $data['next_due_mileage'] ?? null,
                $data['notes'] ?? null,
                $createdBy
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Record maintenance service
     */
    public function recordService($data, $createdBy)
    {
        $this->db->beginTransaction();

        try {
            // Insert maintenance log
            $logId = $this->db->insert(
                "INSERT INTO maintenance_logs 
                 (vehicle_id, schedule_id, service_date, completion_date,
                  service_type, service_description, mileage_at_service,
                  mechanic_id, supervisor_id, labor_cost, parts_cost, other_costs,
                  parts_used, service_rating, before_photos, after_photos,
                  next_service_recommended_date, next_service_recommended_mileage,
                  notes, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['vehicle_id'],
                    $data['schedule_id'] ?? null,
                    $data['service_date'],
                    $data['completion_date'] ?? null,
                    $data['service_type'],
                    $data['service_description'] ?? $data['notes'] ?? '',
                    $data['mileage_at_service'],
                    $data['mechanic_id'] ?? null,
                    $data['supervisor_id'] ?? null,
                    $data['labor_cost'] ?? 0,
                    $data['parts_cost'] ?? 0,
                    $data['other_costs'] ?? 0,
                    json_encode($data['parts_used'] ?? []),
                    $data['service_rating'] ?? null,
                    json_encode($data['before_photos'] ?? []),
                    json_encode($data['after_photos'] ?? []),
                    $data['next_service_recommended_date'] ?? null,
                    $data['next_service_recommended_mileage'] ?? null,
                    $data['notes'] ?? null,
                    $data['completion_date'] ? 'completed' : 'in_progress',
                    $createdBy
                ]
            );

            // Update schedule if applicable
            if (!empty($data['schedule_id'])) {
                $data['log_id'] = $logId; // Pass the newly created log_id
                $this->updateScheduleAfterService($data['schedule_id'], $data);
            }

            // Update vehicle mileage if higher
            $this->db->execute(
                "UPDATE vehicles 
                 SET mileage = GREATEST(mileage, ?),
                     current_status = IF(current_status = 'maintenance', 'available', current_status)
                 WHERE vehicle_id = ?",
                [$data['mileage_at_service'], $data['vehicle_id']]
            );

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
                    "Recorded maintenance for {$data['vehicle_id']}: {$data['service_type']}",
                    null,
                    json_encode(['vehicle_id' => $data['vehicle_id'], 'service_type' => $data['service_type'], 'cost' => (($data['labor_cost'] ?? 0) + ($data['parts_cost'] ?? 0) + ($data['other_costs'] ?? 0))]),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'POST',
                    '/maintenance/record',
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
     * Update schedule after service completion
     */
    private function updateScheduleAfterService($scheduleId, $serviceData)
    {
        $schedule = $this->db->fetchOne(
            "SELECT * FROM maintenance_schedules WHERE schedule_id = ?",
            [$scheduleId]
        );

        if (!$schedule)
            return;

        // Calculate next due date/mileage
        $nextDueDate = null;
        $nextDueMileage = null;

        if ($schedule['interval_months']) {
            $nextDueDate = date('Y-m-d', strtotime("+{$schedule['interval_months']} months"));
        }

        if ($schedule['interval_mileage']) {
            $nextDueMileage = $serviceData['mileage_at_service'] + $schedule['interval_mileage'];
        }

        $this->db->execute(
            "UPDATE maintenance_schedules 
             SET last_service_date = ?,
                 last_service_mileage = ?,
                 last_maintenance_id = ?,
                 next_due_date = ?,
                 next_due_mileage = ?,
                 status = 'active'
             WHERE schedule_id = ?",
            [
                $serviceData['service_date'],
                $serviceData['mileage_at_service'],
                $serviceData['log_id'] ?? null,
                $nextDueDate,
                $nextDueMileage,
                $scheduleId
            ]
        );
    }

    /**
     * Get maintenance history for vehicle
     */
    public function getHistory($vehicleId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT ml.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as mechanic_name,
                    ms.service_type as scheduled_service_type
             FROM maintenance_logs ml
             LEFT JOIN users u ON ml.mechanic_id = u.user_id
             LEFT JOIN maintenance_schedules ms ON ml.schedule_id = ms.schedule_id
             WHERE ml.vehicle_id = ?
             ORDER BY ml.service_date DESC
             LIMIT ?",
            [$vehicleId, $limit]
        );
    }

    /**
     * Get maintenance costs summary
     */
    public function getCostSummary($dateFrom = null, $dateTo = null, $vehicleId = null)
    {
        $where = ["1=1"];
        $params = [];

        if ($dateFrom) {
            $where[] = "service_date >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $where[] = "service_date <= ?";
            $params[] = $dateTo;
        }

        if ($vehicleId) {
            $where[] = "vehicle_id = ?";
            $params[] = $vehicleId;
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_services,
                SUM(labor_cost) as total_labor,
                SUM(parts_cost) as total_parts,
                SUM(other_costs) as other_costs,
                SUM(total_cost) as grand_total,
                AVG(total_cost) as average_cost
             FROM maintenance_logs
             WHERE {$whereClause}",
            $params
        );
    }
}
