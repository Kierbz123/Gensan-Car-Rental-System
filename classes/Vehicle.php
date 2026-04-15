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

        // ── Server-Side Format & Range Validation (FIX-09) ────────────────────
        $currentYear = (int) date('Y');
        $yearModel   = (int) $data['year_model'];
        if ($yearModel < 1990 || $yearModel > $currentYear + 1) {
            throw new Exception("Year model must be between 1990 and " . ($currentYear + 1) . ".");
        }

        if (!preg_match('/^[A-Z0-9\s\-]{2,20}$/i', $data['plate_number'])) {
            throw new Exception("Invalid plate number format. Only letters, numbers, spaces, and dashes allowed (2–20 characters).");
        }

        $validFuelTypes = ['gasoline', 'diesel', 'hybrid', 'electric'];
        if (!in_array($data['fuel_type'], $validFuelTypes)) {
            throw new Exception("Invalid fuel type. Allowed: " . implode(', ', $validFuelTypes) . ".");
        }

        $validTransmissions = ['manual', 'automatic', 'cvt'];
        if (!in_array($data['transmission'], $validTransmissions)) {
            throw new Exception("Invalid transmission type. Allowed: " . implode(', ', $validTransmissions) . ".");
        }

        foreach (['daily_rental_rate', 'weekly_rental_rate', 'monthly_rental_rate', 'acquisition_cost'] as $rateField) {
            if (isset($data[$rateField]) && $data[$rateField] !== '' && (float) $data[$rateField] < 0) {
                throw new Exception(str_replace('_', ' ', ucwords($rateField, '_')) . " cannot be negative.");
            }
        }

        $seats = (int) ($data['seating_capacity'] ?? 5);
        if ($seats < 1 || $seats > 50) {
            throw new Exception("Seating capacity must be between 1 and 50.");
        }

        // Check for duplicate plate number (PERF-02: LIMIT 1 stops scan after first match)
        $exists = $this->db->fetchOne(
            "SELECT vehicle_id FROM vehicles WHERE plate_number = ? AND deleted_at IS NULL LIMIT 1",
            [$data['plate_number']]
        );

        if ($exists) {
            throw new Exception("Plate number already exists in the system.");
        }

        // Check for duplicate engine number
        if (!empty($data['engine_number'])) {
            $existsEngine = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM vehicles WHERE engine_number = ? AND deleted_at IS NULL",
                [$data['engine_number']]
            );

            if ($existsEngine && $existsEngine['count'] > 0) {
                throw new Exception("Engine number already registered.");
            }
        }

        // Check for duplicate chassis number
        if (!empty($data['chassis_number'])) {
            $existsChassis = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM vehicles WHERE chassis_number = ? AND deleted_at IS NULL",
                [$data['chassis_number']]
            );

            if ($existsChassis && $existsChassis['count'] > 0) {
                throw new Exception("Chassis number already registered.");
            }
        }

        // Generate category code (read-only, safe outside the transaction)
        $categoryCode = $this->getCategoryCode($data['category_id']);

        // ── Begin atomic DB transaction (BUG-01) ──────────────────────────────
        // generateVehicleId() issues SELECT ... FOR UPDATE, which requires an
        // active InnoDB transaction to prevent concurrent duplicate IDs.
        // All DB writes (vehicle row, QR code path, maintenance schedules,
        // compliance stubs) succeed or fail atomically.
        $this->db->beginTransaction();
        $photoPath = null;

        try {
            // ID generation uses FOR UPDATE lock — must be inside the transaction
            $vehicleId = $this->generateVehicleId($categoryCode);

            // Photo upload is a filesystem operation (cannot be rolled back by DB),
            // so we do it inside the try block and clean up manually on failure.
            if (!empty($data['primary_photo']) && $data['primary_photo']['tmp_name']) {
                $photoPath = $this->uploadVehiclePhoto($data['primary_photo'], $vehicleId);
            }

            // Insert vehicle with explicit initial status (BUG-03)
            $this->db->execute(
                "INSERT INTO vehicles
                 (vehicle_id, category_id, plate_number, engine_number, chassis_number,
                  year_model, brand, model, variant, color, seating_capacity, fuel_type,
                  transmission, acquisition_date, acquisition_cost, daily_rental_rate,
                  weekly_rental_rate, monthly_rental_rate, security_deposit_amount,
                  primary_photo_path, notes, current_status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?)",
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

            // Generate QR Code (updates vehicles.qr_code_path inside the transaction)
            $this->generateQRCode($vehicleId);

            // Create default maintenance schedules
            $this->createDefaultMaintenanceSchedules($vehicleId, $createdBy);

            // Auto-create mandatory compliance stubs (ATL-04)
            $this->createDefaultComplianceRecords($vehicleId, $createdBy);

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollback();
            // Clean up the uploaded photo file if the DB side failed
            if ($photoPath) {
                $fullPath = BASE_PATH . ltrim($photoPath, '/');
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
            throw $e; // Re-throw so vehicle-add.php displays the error
        }

        // Audit log runs outside the transaction (non-critical, must not cause rollback)
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

        // Check engine number uniqueness if changed (BUG-06: added deleted_at IS NULL)
        if (!empty($data['engine_number']) && $data['engine_number'] !== $vehicle['engine_number']) {
            $existsEngine = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM vehicles
                 WHERE engine_number = ? AND vehicle_id != ? AND deleted_at IS NULL",
                [$data['engine_number'], $vehicleId]
            );

            if ($existsEngine && $existsEngine['count'] > 0) {
                throw new Exception("Engine number already registered.");
            }
        }

        // Check chassis number uniqueness if changed (BUG-06: added deleted_at IS NULL)
        if (!empty($data['chassis_number']) && $data['chassis_number'] !== $vehicle['chassis_number']) {
            $existsChassis = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM vehicles
                 WHERE chassis_number = ? AND vehicle_id != ? AND deleted_at IS NULL",
                [$data['chassis_number'], $vehicleId]
            );

            if ($existsChassis && $existsChassis['count'] > 0) {
                throw new Exception("Chassis number already registered.");
            }
        }

        // Normalise plate number to uppercase before any uniqueness/update logic
        if (isset($data['plate_number'])) {
            $data['plate_number'] = strtoupper(trim($data['plate_number']));
        }

        // ── Server-Side Validation (mirrors create() guards) ─────────────────
        if (isset($data['year_model'])) {
            $currentYear = (int) date('Y');
            $yearModel   = (int) $data['year_model'];
            if ($yearModel < 1990 || $yearModel > $currentYear + 1) {
                throw new Exception("Year model must be between 1990 and " . ($currentYear + 1) . ".");
            }
        }
        if (isset($data['plate_number']) && !preg_match('/^[A-Z0-9\s\-]{2,20}$/i', $data['plate_number'])) {
            throw new Exception("Invalid plate number format. Only letters, numbers, spaces, and dashes allowed (2–20 characters).");
        }
        $validFuelTypes = ['gasoline', 'diesel', 'hybrid', 'electric'];
        if (isset($data['fuel_type']) && !in_array($data['fuel_type'], $validFuelTypes, true)) {
            throw new Exception("Invalid fuel type. Allowed: " . implode(', ', $validFuelTypes) . ".");
        }
        $validTransmissions = ['manual', 'automatic', 'cvt'];
        if (isset($data['transmission']) && !in_array($data['transmission'], $validTransmissions, true)) {
            throw new Exception("Invalid transmission type. Allowed: " . implode(', ', $validTransmissions) . ".");
        }
        foreach (['daily_rental_rate', 'weekly_rental_rate', 'monthly_rental_rate'] as $rateField) {
            if (isset($data[$rateField]) && $data[$rateField] !== '' && (float) $data[$rateField] < 0) {
                throw new Exception(ucwords(str_replace('_', ' ', $rateField)) . " cannot be negative.");
            }
        }

        // Build update query
        $updates = [];
        $params = [];
        $oldValues = [];
        $newValues = [];

        $updatableFields = [
            'category_id',
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

        // PERF-01: Replaced 3 correlated sub-queries (O(n) extra queries) with
        // derived-table LEFT JOINs — executes in a single optimized query plan.
        $vehicles = $this->db->fetchAll(
            "SELECT v.*, vc.category_name, vc.category_code,
                    COALESCE(ra_agg.total_rentals, 0) AS total_rentals,
                    ml_agg.last_service_date,
                    cr_agg.compliance_status
             FROM vehicles v
             JOIN vehicle_categories vc ON v.category_id = vc.category_id
             LEFT JOIN (
                 SELECT vehicle_id, COUNT(*) AS total_rentals
                 FROM rental_agreements
                 WHERE status IN ('active', 'returned', 'completed')
                 GROUP BY vehicle_id
             ) ra_agg ON ra_agg.vehicle_id = v.vehicle_id
             LEFT JOIN (
                 SELECT vehicle_id, MAX(service_date) AS last_service_date
                 FROM maintenance_logs
                 GROUP BY vehicle_id
             ) ml_agg ON ml_agg.vehicle_id = v.vehicle_id
             LEFT JOIN (
                 SELECT vehicle_id,
                        CASE
                            WHEN MIN(expiry_date) < CURRENT_DATE()                             THEN 'breached'
                            WHEN MIN(expiry_date) <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'expiring'
                            ELSE 'valid'
                        END AS compliance_status
                 FROM compliance_records
                 WHERE status NOT IN ('renewed', 'cancelled')
                   AND expiry_date IS NOT NULL
                   AND expiry_date != '0000-00-00'
                 GROUP BY vehicle_id
             ) cr_agg ON cr_agg.vehicle_id = v.vehicle_id
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

        // Load the token helper (idempotent — safe to call multiple times)
        if (!function_exists('buildScanUrl')) {
            require_once INCLUDES_PATH . 'qr-token.php';
        }

        // The URL encoded into the QR — points to the PUBLIC vehicle-scan.php page.
        // Protected by an HMAC token so no login is required; token is stateless.
        // APP_URL already resolves localhost → LAN IP so phones on the same Wi-Fi can open it.
        $qrContent = buildScanUrl($vehicleId);

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

        if (!class_exists('QRcode')) {
            throw new Exception(
                "QR code library (phpqrcode) is not installed. " .
                "Place the library at: " . INCLUDES_PATH . "phpqrcode-master/qrlib.php"
            );
        }

        // High error-correction (H = 30% recovery), pixel size 10, margin 2
        QRcode::png($qrContent, $qrPath, QR_ECLEVEL_H, 10, 2);

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
        // BUG-02: Check PHP's own upload error code FIRST, before touching the file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds the server\'s upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form\'s MAX_FILE_SIZE limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing server temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension.',
            ];
            throw new Exception($uploadErrors[$file['error']] ?? 'Unknown upload error (code ' . $file['error'] . ').');
        }

        // BUG-02: Use finfo to verify the actual MIME type from file content,
        // not the browser-supplied $file['type'] which is trivially spoofable.
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
            throw new Exception("Invalid file type ({$mimeType}). Only JPG, PNG, GIF, WebP allowed.");
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception("File size exceeds limit of " . (MAX_UPLOAD_SIZE / 1024 / 1024) . " MB.");
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename  = $vehicleId . '_primary_' . time() . '.' . $extension;
        $filepath  = VEHICLE_PHOTOS_PATH . $filename;

        if (!is_dir(VEHICLE_PHOTOS_PATH)) {
            mkdir(VEHICLE_PHOTOS_PATH, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Failed to save uploaded file. Check directory permissions.");
        }

        // Create thumbnail using real GD resizing (BUG-07 fixed in createThumbnail)
        $this->createThumbnail($filepath, VEHICLE_PHOTOS_PATH . 'thumbs/' . $filename, 300, 200);

        return str_replace(BASE_PATH, '', $filepath);
    }

    /**
     * Create a proportionally-scaled image thumbnail using the GD extension (BUG-07).
     *
     * @param string $source  Absolute path to the source image
     * @param string $dest    Absolute path to write the thumbnail
     * @param int    $width   Maximum thumbnail width in pixels
     * @param int    $height  Maximum thumbnail height in pixels
     */
    private function createThumbnail($source, $dest, $width, $height)
    {
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0755, true);
        }

        $info = @getimagesize($source);
        if (!$info) {
            return; // Not a valid image — skip silently (photo still saved)
        }

        [$srcW, $srcH, $type] = $info;

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG  => @imagecreatefrompng($source),
            IMAGETYPE_GIF  => @imagecreatefromgif($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default        => null
        };

        if (!$src) {
            return; // GD cannot handle this format — skip
        }

        // Scale proportionally so neither dimension exceeds the target
        $ratio = min($width / $srcW, $height / $srcH);
        $newW  = max(1, (int) round($srcW * $ratio));
        $newH  = max(1, (int) round($srcH * $ratio));

        $thumb = imagecreatetruecolor($newW, $newH);

        // Preserve transparency for PNG/GIF
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefill($thumb, 0, 0, $transparent);
        }

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagepng($thumb, $dest, 8); // PNG-8 compression for thumbnail

        imagedestroy($src);
        imagedestroy($thumb);
    }

    /**
     * Generate a unique vehicle ID for the given category (BUG-08).
     *
     * Uses SELECT ... FOR UPDATE inside the caller's active transaction to prevent
     * two concurrent registrations from generating the same sequence number.
     * MUST be called within an open DB transaction (beginTransaction).
     */
    private function generateVehicleId($categoryCode)
    {
        // FOR UPDATE acquires an InnoDB gap/range lock on the matching rows,
        // preventing concurrent inserts from reading the same MAX() value.
        $result = $this->db->fetchOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(vehicle_id, '-', -1) AS UNSIGNED)), 0) + 1 AS next_seq
             FROM vehicles
             WHERE vehicle_id LIKE ?
             FOR UPDATE",
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

        // ATL-03: Log the recommission event so fleet managers can see when
        // and by whom a decommissioned vehicle was restored.
        $this->db->execute(
            "INSERT INTO vehicle_status_logs
                 (vehicle_id, previous_status, new_status, reason, changed_by, changed_at)
             VALUES (?, 'decommissioned', 'available', 'Vehicle recommissioned', ?, NOW())",
            [$vehicleId, $restoredBy]
        );

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
     * Auto-create mandatory compliance record stubs for a new vehicle (ATL-04).
     *
     * Creates 'pending' placeholder records for the four most critical compliance
     * types so the fleet manager is immediately alerted to fill in expiry dates.
     * Uses INSERT IGNORE to safely handle repeated calls (e.g. after recommission).
     */
    private function createDefaultComplianceRecords($vehicleId, $createdBy)
    {
        $mandatoryTypes = [
            'lto_registration',
            'insurance_comprehensive',
            'insurance_tpl',
            'emission_test',
        ];

        foreach ($mandatoryTypes as $type) {
            $this->db->execute(
                "INSERT IGNORE INTO compliance_records
                 (vehicle_id, compliance_type, status, expiry_date, created_by)
                 VALUES (?, ?, 'pending', NULL, ?)",
                [$vehicleId, $type, $createdBy]
            );
        }
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
