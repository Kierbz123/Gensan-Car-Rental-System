<?php
// /var/www/html/gensan-car-rental-system/classes/Vehicle.php

/**
 * Vehicle Management Class
 * Core asset tracking functionality
 */

class Vehicle
{
    private $db;
    private $vehicleId;
    private $vehicleData;

    public function __construct($vehicleId = null)
    {
        $this->db = Database::getInstance();
        $this->vehicleId = $vehicleId;

        if ($vehicleId) {
            $this->loadVehicleData();
        }
    }

    /**
     * Create new vehicle
     */
    public function create($data, $createdBy)
    {
        // Validate required fields
        $required = [
            'category_id',
            'plate_number',
            'brand',
            'model',
            'year_model',
            'color',
            'fuel_type',
            'transmission',
            'acquisition_date',
            'daily_rental_rate'
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("{$field} is required.");
            }
        }

        // Check for duplicate plate number
        $exists = $this->db->fetchOne(
            "SELECT vehicle_id FROM vehicles WHERE plate_number = ? AND deleted_at IS NULL",
            [$data['plate_number']]
        );

        if ($exists) {
            throw new Exception("Plate number already exists in the system.");
        }

        // Check for duplicate engine number
        if (!empty($data['engine_number'])) {
            $existsEngine = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM vehicles WHERE engine_number = ?",
                [$data['engine_number']]
            );

            if ($existsEngine && $existsEngine['count'] > 0) {
                throw new Exception("Engine number already registered.");
            }
        }

        // Check for duplicate chassis number
        if (!empty($data['chassis_number'])) {
            $existsChassis = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM vehicles WHERE chassis_number = ?",
                [$data['chassis_number']]
            );

            if ($existsChassis && $existsChassis['count'] > 0) {
                throw new Exception("Chassis number already registered.");
            }
        }

        // Generate vehicle ID
        $categoryCode = $this->getCategoryCode($data['category_id']);
        $vehicleId = $this->generateVehicleId($categoryCode);

        // Handle photo upload
        $photoPath = null;
        if (!empty($data['primary_photo']) && $data['primary_photo']['tmp_name']) {
            $photoPath = $this->uploadVehiclePhoto($data['primary_photo'], $vehicleId);
        }

        // Insert vehicle
        $this->db->execute(
            "INSERT INTO vehicles 
             (vehicle_id, category_id, plate_number, engine_number, chassis_number,
              year_model, brand, model, variant, color, seating_capacity, fuel_type,
              transmission, acquisition_date, acquisition_cost, daily_rental_rate,
              weekly_rental_rate, monthly_rental_rate, security_deposit_amount,
              primary_photo_path, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $vehicleId,
                $data['category_id'],
                strtoupper($data['plate_number']),
                $data['engine_number'] ?? null,
                $data['chassis_number'] ?? null,
                $data['year_model'],
                $data['brand'],
                $data['model'],
                $data['variant'] ?? null,
                $data['color'],
                $data['seating_capacity'] ?? 5,
                $data['fuel_type'],
                $data['transmission'],
                $data['acquisition_date'],
                $data['acquisition_cost'] ?? null,
                $data['daily_rental_rate'],
                $data['weekly_rental_rate'] ?? null,
                $data['monthly_rental_rate'] ?? null,
                $data['security_deposit_amount'] ?? null,
                $photoPath,
                $data['notes'] ?? null,
                $createdBy
            ]
        );

        // Generate QR Code
        $this->generateQRCode($vehicleId);

        // Create initial maintenance schedules
        $this->createDefaultMaintenanceSchedules($vehicleId, $createdBy);

        // Log audit
        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $createdBy,
                null,
                null,
                'create',
                'asset_tracking',
                'vehicles',
                $vehicleId,
                "Created vehicle: {$data['brand']} {$data['model']} ({$vehicleId})",
                null,
                json_encode(['plate' => $data['plate_number'], 'category' => $categoryCode]),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/vehicles/create',
                'info'
            );
        }

        return $vehicleId;
    }

    /**
     * Update vehicle
     */
    public function update($vehicleId, $data, $updatedBy)
    {
        $vehicle = $this->db->fetchOne(
            "SELECT * FROM vehicles WHERE vehicle_id = ? AND deleted_at IS NULL",
            [$vehicleId]
        );

        if (!$vehicle) {
            throw new Exception("Vehicle not found.");
        }

        // Check plate number uniqueness if changed
        if (!empty($data['plate_number']) && $data['plate_number'] !== $vehicle['plate_number']) {
            $exists = $this->db->fetchOne(
                "SELECT vehicle_id FROM vehicles 
                 WHERE plate_number = ? AND vehicle_id != ? AND deleted_at IS NULL",
                [$data['plate_number'], $vehicleId]
            );

            if ($exists) {
                throw new Exception("Plate number already exists.");
            }
        }

        // Check engine number uniqueness if changed
        if (!empty($data['engine_number']) && $data['engine_number'] !== $vehicle['engine_number']) {
            $existsEngine = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM vehicles 
                 WHERE engine_number = ? AND vehicle_id != ?",
                [$data['engine_number'], $vehicleId]
            );

            if ($existsEngine && $existsEngine['count'] > 0) {
                throw new Exception("Engine number already registered.");
            }
        }

        // Check chassis number uniqueness if changed
        if (!empty($data['chassis_number']) && $data['chassis_number'] !== $vehicle['chassis_number']) {
            $existsChassis = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM vehicles 
                 WHERE chassis_number = ? AND vehicle_id != ?",
                [$data['chassis_number'], $vehicleId]
            );

            if ($existsChassis && $existsChassis['count'] > 0) {
                throw new Exception("Chassis number already registered.");
            }
        }

        // Build update query
        $updates = [];
        $params = [];
        $oldValues = [];
        $newValues = [];

        $updatableFields = [
            'plate_number',
            'engine_number',
            'chassis_number',
            'year_model',
            'brand',
            'model',
            'variant',
            'color',
            'seating_capacity',
            'fuel_type',
            'transmission',
            'daily_rental_rate',
            'weekly_rental_rate',
            'monthly_rental_rate',
            'security_deposit_amount',
            'notes'
        ];

        foreach ($updatableFields as $field) {
            if (isset($data[$field]) && $data[$field] !== $vehicle[$field]) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
                $oldValues[$field] = $vehicle[$field];
                $newValues[$field] = $data[$field];
            }
        }

        // Handle photo upload
        if (!empty($data['primary_photo']) && $data['primary_photo']['tmp_name']) {
            $photoPath = $this->uploadVehiclePhoto($data['primary_photo'], $vehicleId);
            $updates[] = "primary_photo_path = ?";
            $params[] = $photoPath;
            $oldValues['primary_photo_path'] = $vehicle['primary_photo_path'];
            $newValues['primary_photo_path'] = $photoPath;
        }

        if (empty($updates)) {
            return true; // Nothing to update
        }

        $params[] = $vehicleId;

        $this->db->execute(
            "UPDATE vehicles SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE vehicle_id = ?",
            $params
        );

        // Log audit
        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $updatedBy,
                null,
                null,
                'update',
                'asset_tracking',
                'vehicles',
                $vehicleId,
                "Updated vehicle: {$vehicleId}",
                json_encode($oldValues),
                json_encode($newValues),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/vehicles/update',
                'info'
            );
        }

        return true;
    }

    /**
     * Update vehicle status with logging
     */
    public function updateStatus(
        $vehicleId,
        $newStatus,
        $changedBy,
        $newLocation = null,
        $mileage = null,
        $reason = null,
        $relatedRentalId = null,
        $relatedMaintenanceId = null
    ) {
        $vehicle = $this->db->fetchOne(
            "SELECT current_status, current_location, mileage 
             FROM vehicles WHERE vehicle_id = ? AND deleted_at IS NULL",
            [$vehicleId]
        );

        if (!$vehicle) {
            throw new Exception("Vehicle not found.");
        }

        // Validate status transition
        $validTransitions = $this->getValidStatusTransitions($vehicle['current_status']);
        if (!in_array($newStatus, $validTransitions)) {
            throw new Exception("Invalid status transition from {$vehicle['current_status']} to {$newStatus}.");
        }

        // Log the status change to vehicle_status_logs (replaces missing stored procedure)
        $this->db->execute(
            "INSERT INTO vehicle_status_logs
                (vehicle_id, previous_status, new_status, previous_location, new_location, previous_mileage, new_mileage,
                 reason, changed_by, ip_address, user_agent, related_rental_id,
                 related_maintenance_id, changed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $vehicleId,
                $vehicle['current_status'],
                $newStatus,
                $vehicle['current_location'],
                $newLocation ?? $vehicle['current_location'],
                $vehicle['mileage'],
                $mileage,
                $reason,
                $changedBy,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $relatedRentalId,
                $relatedMaintenanceId
            ]
        );

        // Update vehicle record
        $updates = ["current_status = ?"];
        $params  = [$newStatus];

        if ($newLocation) {
            $updates[] = "current_location = ?";
            $params[]  = $newLocation;
        }

        if ($mileage && $mileage > $vehicle['mileage']) {
            $updates[] = "mileage = ?";
            $params[]  = $mileage;
        }

        $params[] = $vehicleId;

        $this->db->execute(
            "UPDATE vehicles SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE vehicle_id = ?",
            $params
        );

        return true;
    }

    /**
     * Get vehicle by ID
     */
    public function getById($vehicleId, $includeDeleted = false)
    {
        $sql = "SELECT v.*, vc.category_name, vc.category_code,
                       TIMESTAMPDIFF(DAY, v.acquisition_date, CURDATE()) as days_in_service
                FROM vehicles v
                JOIN vehicle_categories vc ON v.category_id = vc.category_id
                WHERE v.vehicle_id = ?";

        if (!$includeDeleted) {
            $sql .= " AND v.deleted_at IS NULL";
        }

        return $this->db->fetchOne($sql, [$vehicleId]);
    }

    /**
     * Get all vehicles with filtering
     */
    public function getAll($filters = [], $page = 1, $perPage = ITEMS_PER_PAGE)
    {
        $where = ["v.deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['category_id'])) {
            // Numeric ID or category_code string both supported
            if (is_numeric($filters['category_id'])) {
                $where[] = "v.category_id = ?";
                $params[] = $filters['category_id'];
            } else {
                $where[] = "vc.category_code = ?";
                $params[] = strtoupper($filters['category_id']);
            }
        }

        if (!empty($filters['status'])) {
            $where[] = "v.current_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['location'])) {
            $where[] = "v.current_location = ?";
            $params[] = $filters['location'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(v.vehicle_id LIKE ? OR v.plate_number LIKE ? OR 
                        v.brand LIKE ? OR v.model LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        if (!empty($filters['year_model'])) {
            $where[] = "v.year_model = ?";
            $params[] = $filters['year_model'];
        }

        if (!empty($filters['fuel_type'])) {
            $where[] = "v.fuel_type = ?";
            $params[] = $filters['fuel_type'];
        }

        $whereClause = implode(' AND ', $where);

        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM vehicles v 
             LEFT JOIN vehicle_categories vc ON v.category_id = vc.category_id 
             WHERE {$whereClause}",
            $params
        );

        // Get paginated results
        $offset = ($page - 1) * $perPage;

        $vehicles = $this->db->fetchAll(
            "SELECT v.*, vc.category_name, vc.category_code,
                    (SELECT COUNT(*) FROM rental_agreements 
                     WHERE vehicle_id = v.vehicle_id AND status IN ('active', 'returned', 'completed')) as total_rentals,
                    (SELECT MAX(service_date) FROM maintenance_logs 
                     WHERE vehicle_id = v.vehicle_id) as last_service_date,
                    (SELECT 
                        CASE 
                            WHEN MIN(expiry_date) < CURRENT_DATE() THEN 'breached'
                            WHEN MIN(expiry_date) <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'expiring'
                            ELSE 'valid'
                        END
                     FROM compliance_records 
                     WHERE vehicle_id = v.vehicle_id 
                       AND status NOT IN ('renewed', 'cancelled')
                       AND record_id = (
                           SELECT MAX(record_id)
                           FROM compliance_records c2
                           WHERE c2.vehicle_id = v.vehicle_id AND c2.compliance_type = compliance_records.compliance_type
                       )
                    ) as compliance_status
             FROM vehicles v
             JOIN vehicle_categories vc ON v.category_id = vc.category_id
             WHERE {$whereClause}
             ORDER BY v.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $vehicles,
            'total' => $count,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($count / $perPage)
        ];
    }

    /**
     * Get vehicle availability summary
     */
    public function getAvailabilitySummary()
    {
        return $this->db->fetchAll("SELECT * FROM vehicle_availability_summary");
    }

    /**
     * Get vehicle status history
     */
    public function getStatusHistory($vehicleId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT vsl.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
             FROM vehicle_status_logs vsl
             LEFT JOIN users u ON vsl.changed_by = u.user_id
             WHERE vsl.vehicle_id = ?
             ORDER BY vsl.changed_at DESC
             LIMIT ?",
            [$vehicleId, $limit]
        );
    }

    /**
     * Soft delete vehicle
     */
    public function delete($vehicleId, $deletedBy, $reason = null)
    {
        // Check if vehicle has active rentals
        $activeRental = $this->db->fetchOne(
            "SELECT agreement_id FROM rental_agreements 
             WHERE vehicle_id = ? AND status IN ('reserved', 'confirmed', 'active')",
            [$vehicleId]
        );

        if ($activeRental) {
            throw new Exception("Cannot delete vehicle with active or upcoming rentals.");
        }

        $this->db->execute(
            "UPDATE vehicles SET deleted_at = NOW(), deleted_by = ? WHERE vehicle_id = ?",
            [$deletedBy, $vehicleId]
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $deletedBy,
                null,
                null,
                'delete',
                'asset_tracking',
                'vehicles',
                $vehicleId,
                "Deleted vehicle: {$vehicleId}" . ($reason ? " - Reason: {$reason}" : ""),
                json_encode(['status' => 'active']),
                json_encode(['status' => 'deleted']),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/vehicles/delete',
                'warning'
            );
        }

        return true;
    }

    /**
     * Generate QR Code for vehicle
     * Uses phpqrcode library if available, falls back to GD-drawn matrix
     */
    public function generateQRCode($vehicleId)
    {
        $vehicle = $this->getById($vehicleId);
        if (!$vehicle) {
            throw new Exception("Vehicle {$vehicleId} not found.");
        }

        // The URL encoded into the QR — must be an absolute URL (with scheme + hostname) so that
        // mobile cameras (Google Lens, iPhone Camera, etc.) can open it directly after scanning.
        // APP_URL is set in config.php using HTTP_HOST; override with APP_URL in .env for production.
        $qrContent = APP_URL . 'modules/asset-tracking/vehicle-details.php?id=' . urlencode($vehicleId);

        // Metadata stored separately in DB
        $qrData = json_encode([
            'vehicle_id' => $vehicleId,
            'plate' => $vehicle['plate_number'],
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'url' => $qrContent,
            'generated' => date('Y-m-d H:i:s'),
        ]);

        $qrDir = QR_CODES_PATH;
        $qrPath = $qrDir . $vehicleId . '.png';

        // Ensure output directory exists
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0755, true);
        }

        // --- Load phpqrcode library ---
        $libPath = INCLUDES_PATH . 'phpqrcode-master/qrlib.php';
        if (!class_exists('QRcode') && file_exists($libPath)) {
            require_once $libPath;
        }

        if (class_exists('QRcode')) {
            // High error-correction, pixel size 10, margin 2
            QRcode::png($qrContent, $qrPath, QR_ECLEVEL_H, 10, 2);
        } else {
            // Pure-GD fallback: render a small grid as a rough visual placeholder
            // (not actually scannable, but keeps the system from crashing)
            $size = 300;
            $img = imagecreatetruecolor($size, $size);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefill($img, 0, 0, $white);
            // Draw a simple placeholder pattern
            for ($i = 0; $i < $size; $i += 10) {
                imagesetpixel($img, $i, $i, $black);
            }
            imagepng($img, $qrPath);
            imagedestroy($img);
        }

        // Store relative path (without BASE_PATH prefix) in database
        $relativePath = str_replace(BASE_PATH, '', $qrPath);

        $this->db->execute(
            "UPDATE vehicles SET qr_code_path = ?, qr_code_data = ? WHERE vehicle_id = ?",
            [$relativePath, $qrData, $vehicleId]
        );

        return $qrPath;
    }

    /**
     * Upload vehicle photo
     */
    private function uploadVehiclePhoto($file, $vehicleId)
    {
        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
            throw new Exception("Invalid file type. Only JPG, PNG, GIF, WebP allowed.");
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception("File size exceeds limit of " . (MAX_UPLOAD_SIZE / 1024 / 1024) . "MB");
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $vehicleId . '_primary_' . time() . '.' . $extension;
        $filepath = VEHICLE_PHOTOS_PATH . $filename;

        if (!is_dir(VEHICLE_PHOTOS_PATH)) {
            mkdir(VEHICLE_PHOTOS_PATH, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Failed to upload file.");
        }

        // Create thumbnail (placeholder for now)
        $this->createThumbnail($filepath, VEHICLE_PHOTOS_PATH . 'thumbs/' . $filename, 300, 200);

        return str_replace(BASE_PATH, '', $filepath);
    }

    /**
     * Create image thumbnail
     */
    private function createThumbnail($source, $dest, $width, $height)
    {
        // Implementation using GD or ImageMagick
        // ... thumbnail creation code ...
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0755, true);
        }
        // Simplified skip for now
    }

    /**
     * Generate vehicle ID
     */
    private function generateVehicleId($categoryCode)
    {
        $result = $this->db->fetchOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(vehicle_id, -4) AS UNSIGNED)), 0) + 1 as next_seq
             FROM vehicles
             WHERE vehicle_id LIKE ?",
            ["GCR-{$categoryCode}-%"]
        );

        $sequence = str_pad($result['next_seq'], 4, '0', STR_PAD_LEFT);
        return "GCR-{$categoryCode}-{$sequence}";
    }

    /**
     * Get category code from ID
     */
    private function getCategoryCode($categoryId)
    {
        $result = $this->db->fetchOne(
            "SELECT category_code FROM vehicle_categories WHERE category_id = ?",
            [$categoryId]
        );
        return $result['category_code'] ?? 'XX';
    }

    /**
     * Create default maintenance schedules
     */
    private function createDefaultMaintenanceSchedules($vehicleId, $createdBy)
    {
        $defaultSchedules = [
            ['service_type' => 'oil_change', 'interval_months' => 3, 'interval_mileage' => 5000],
            ['service_type' => 'tire_rotation', 'interval_months' => 6, 'interval_mileage' => 10000],
            ['service_type' => 'general_checkup', 'interval_months' => 6, 'interval_mileage' => 10000],
            ['service_type' => 'brake_inspection', 'interval_months' => 6, 'interval_mileage' => 10000],
            ['service_type' => 'aircon_cleaning', 'interval_months' => 6, 'interval_mileage' => null],
        ];

        foreach ($defaultSchedules as $schedule) {
            $this->db->execute(
                "INSERT INTO maintenance_schedules 
                 (vehicle_id, service_type, schedule_basis, interval_months, 
                  interval_mileage, next_due_date, created_by)
                 VALUES (?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? MONTH), ?)",
                [
                    $vehicleId,
                    $schedule['service_type'],
                    $schedule['interval_mileage'] ? 'time_and_mileage' : 'time_only',
                    $schedule['interval_months'],
                    $schedule['interval_mileage'],
                    $schedule['interval_months'],
                    $createdBy
                ]
            );
        }
    }

    /**
     * Get valid status transitions
     */
    public function getValidStatusTransitions($currentStatus)
    {
        $transitions = [
            VEHICLE_STATUS_AVAILABLE => [
                VEHICLE_STATUS_RENTED,
                VEHICLE_STATUS_RESERVED,
                VEHICLE_STATUS_MAINTENANCE,
                VEHICLE_STATUS_CLEANING,
                VEHICLE_STATUS_OUT_OF_SERVICE
            ],
            VEHICLE_STATUS_RENTED => [
                VEHICLE_STATUS_AVAILABLE,
                VEHICLE_STATUS_MAINTENANCE,
                VEHICLE_STATUS_CLEANING
            ],
            VEHICLE_STATUS_MAINTENANCE => [
                VEHICLE_STATUS_AVAILABLE,
                VEHICLE_STATUS_OUT_OF_SERVICE
            ],
            VEHICLE_STATUS_RESERVED => [
                VEHICLE_STATUS_AVAILABLE,
                VEHICLE_STATUS_RENTED
            ],
            VEHICLE_STATUS_CLEANING => [
                VEHICLE_STATUS_AVAILABLE,
                VEHICLE_STATUS_MAINTENANCE
            ],
            VEHICLE_STATUS_OUT_OF_SERVICE => [
                VEHICLE_STATUS_MAINTENANCE,
                VEHICLE_STATUS_RETIRED
            ],
            VEHICLE_STATUS_RETIRED => [] // Terminal state
        ];

        return $transitions[$currentStatus] ?? [];
    }

    /**
     * Load vehicle data
     */
    private function loadVehicleData()
    {
        $this->vehicleData = $this->getById($this->vehicleId);
    }

    /**
     * Get decommissioned (soft-deleted) vehicles
     */
    public function getDecommissioned(): array
    {
        return $this->db->fetchAll(
            "SELECT v.*, vc.category_name, vc.category_code,
                    v.deleted_at AS decommissioned_at
             FROM vehicles v
             JOIN vehicle_categories vc ON v.category_id = vc.category_id
             WHERE v.deleted_at IS NOT NULL
             ORDER BY v.deleted_at DESC"
        );
    }

    /**
     * Recommission a decommissioned vehicle (restore from soft-delete)
     */
    public function recommission(string $vehicleId, int $restoredBy): bool
    {
        $vehicle = $this->getById($vehicleId, true);
        if (!$vehicle) {
            throw new Exception("Vehicle not found.");
        }
        if ($vehicle['deleted_at'] === null) {
            throw new Exception("Vehicle is already active.");
        }

        $this->db->execute(
            "UPDATE vehicles SET deleted_at = NULL, current_status = 'available', updated_at = NOW() WHERE vehicle_id = ?",
            [$vehicleId]
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $restoredBy, null, null,
                'recommission', 'asset_tracking', 'vehicles', $vehicleId,
                "Recommissioned vehicle: {$vehicleId}",
                json_encode(['status' => 'decommissioned']),
                json_encode(['status' => 'available']),
                $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST', '/vehicles/recommission', 'info'
            );
        }

        return true;
    }

    /**
     * Get compliance status summary for a vehicle
     */
    public function getComplianceSummary($vehicleId)
    {
        return $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_instruments,
                SUM(CASE WHEN expiry_date < CURRENT_DATE() THEN 1 ELSE 0 END) as expired_count,
                SUM(CASE WHEN expiry_date >= CURRENT_DATE() AND expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon_count,
                MIN(expiry_date) as earliest_expiry
            FROM compliance_records c
            WHERE vehicle_id = ? AND status NOT IN ('renewed', 'cancelled')
              AND record_id = (
                  SELECT MAX(record_id)
                  FROM compliance_records c2
                  WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
              )
        ", [$vehicleId]);
    }
}
