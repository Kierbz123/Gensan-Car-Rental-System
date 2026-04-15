<?php
// classes/Driver.php

/**
 * Driver / Chauffeur Management Class
 * Handles CRUD and scheduling logic for company chauffeurs
 */
class Driver
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -------------------------------------------------------
    // Create new driver
    // -------------------------------------------------------
    public function create(array $data, int $createdBy): int
    {
        // Check for duplicate license number before inserting
        $existing = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM drivers WHERE license_number = ? AND deleted_at IS NULL",
            [$data['license_number']]
        );
        if ($existing > 0) {
            throw new Exception("A driver with license number '{$data['license_number']}' is already registered.");
        }

        // Validate email format if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address format.");
        }

        // Auto-generate employee code — use count + timestamp suffix to avoid collisions on deletion
        $count = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM drivers");
        $code  = 'DRV-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        // Guarantee uniqueness in case of concurrent inserts
        while ($this->db->fetchColumn("SELECT COUNT(*) FROM drivers WHERE employee_code = ?", [$code]) > 0) {
            $code = 'DRV-' . str_pad(++$count + 1, 4, '0', STR_PAD_LEFT);
        }

        // Handle profile photo upload
        $profilePhotoPath = null;
        if (!empty($data['profile_photo']) && !empty($data['profile_photo']['tmp_name'])) {
            $profilePhotoPath = $this->uploadProfilePhoto($data['profile_photo'], $code);
        }

        $id = $this->db->insert(
            "INSERT INTO drivers
             (employee_code, first_name, last_name, phone, email,
              license_number, license_expiry, license_type, status, notes, profile_photo_path, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, ?)",
            [
                $code,
                $data['first_name'],
                $data['last_name'],
                $data['phone'],
                $data['email'] ?? null,
                $data['license_number'],
                $data['license_expiry'],
                $data['license_type'] ?? 'professional',
                $data['notes'] ?? null,
                $profilePhotoPath,
                $createdBy
            ]
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $createdBy,
                null,
                null,
                'create',
                'drivers',
                'drivers',
                $id,
                "Created driver {$code} — {$data['first_name']} {$data['last_name']}",
                null,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/drivers/add',
                'info'
            );
        }

        return (int) $id;
    }

    // -------------------------------------------------------
    // Update driver record
    // -------------------------------------------------------
    public function update(int $driverId, array $data, int $updatedBy): bool
    {
        // 1. Validate Email
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address format.");
        }

        // 2. Prevent Duplicate License Assignment
        $existingLicense = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM drivers WHERE license_number = ? AND driver_id != ? AND deleted_at IS NULL",
            [$data['license_number'], $driverId]
        );
        if ($existingLicense > 0) {
            throw new Exception("A different driver with license number '{$data['license_number']}' is already registered.");
        }

        // 3. Prevent marking available/inactive if currently tied to an active dispatch
        if (isset($data['status']) && $data['status'] !== 'on_duty') {
            $activeSchedules = (int) $this->db->fetchColumn(
               "SELECT COUNT(*) FROM rental_agreements 
                WHERE driver_id = ? AND status IN ('active', 'confirmed', 'reserved')", 
               [$driverId]
            );
            if ($activeSchedules > 0) {
                throw new Exception("Status override rejected: Driver is currently assigned to an active dispatch route.");
            }
        }

        $old = $this->getById($driverId);

        $setClauses = [
            'first_name = ?', 'last_name = ?', 'phone = ?', 'email = ?',
            'license_number = ?', 'license_expiry = ?', 'license_type = ?',
            'status = ?', 'notes = ?', 'updated_at = NOW()'
        ];
        $params = [
            $data['first_name'],
            $data['last_name'],
            $data['phone'],
            $data['email'] ?? null,
            $data['license_number'],
            $data['license_expiry'],
            $data['license_type'] ?? 'professional',
            $data['status'] ?? 'available',
            $data['notes'] ?? null,
        ];

        // Handle profile photo upload on edit
        if (!empty($data['profile_photo']) && !empty($data['profile_photo']['tmp_name'])) {
            $existingCode = $this->db->fetchColumn(
                "SELECT employee_code FROM drivers WHERE driver_id = ?",
                [$driverId]
            );
            $profilePhotoPath = $this->uploadProfilePhoto($data['profile_photo'], $existingCode);
            $setClauses[] = 'profile_photo_path = ?';
            $params[] = $profilePhotoPath;
        }

        $params[] = $driverId;

        $this->db->execute(
            "UPDATE drivers SET " . implode(', ', $setClauses) . " WHERE driver_id = ?",
            $params
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $updatedBy,
                null,
                null,
                'update',
                'drivers',
                'drivers',
                $driverId,
                "Updated driver #{$driverId}",
                json_encode($old),
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/drivers/edit',
                'info'
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Soft-delete
    // -------------------------------------------------------
    public function delete(int $driverId, int $deletedBy): bool
    {
        $this->db->execute(
            "UPDATE drivers SET deleted_at = NOW() WHERE driver_id = ?",
            [$driverId]
        );

        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $deletedBy,
                null,
                null,
                'delete',
                'drivers',
                'drivers',
                $driverId,
                "Deleted driver #{$driverId}",
                null,
                null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/drivers/delete',
                'warning'
            );
        }

        return true;
    }

    // -------------------------------------------------------
    // Get single driver (with rental count)
    // -------------------------------------------------------
    public function getById(int $driverId): ?array
    {
        return $this->db->fetchOne(
            "SELECT d.*,
                    CONCAT(d.first_name, ' ', d.last_name) AS full_name,
                    COUNT(ra.agreement_id) AS total_assignments
             FROM drivers d
             LEFT JOIN rental_agreements ra
                    ON ra.driver_id = d.driver_id
                   AND ra.status NOT IN ('cancelled','no_show')
             WHERE d.driver_id = ? AND d.deleted_at IS NULL
             GROUP BY d.driver_id",
            [$driverId]
        ) ?: null;
    }

    // -------------------------------------------------------
    // List all active drivers (with optional filters)
    // -------------------------------------------------------
    public function getAll(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['d.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'd.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['license_type'])) {
            $where[] = 'd.license_type = ?';
            $params[] = $filters['license_type'];
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $where[] = "(d.first_name LIKE ? OR d.last_name LIKE ? OR d.employee_code LIKE ? OR d.license_number LIKE ?)";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        $whereClause = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM drivers d WHERE {$whereClause}",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            "SELECT d.*,
                    CONCAT(d.first_name, ' ', d.last_name) AS full_name,
                    DATEDIFF(d.license_expiry, CURDATE()) AS days_until_expiry
             FROM drivers d
             WHERE {$whereClause}
             ORDER BY d.last_name, d.first_name
             LIMIT ? OFFSET ?",
            array_merge($params, [(int)$perPage, (int)$offset])
        );

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    // -------------------------------------------------------
    // Get drivers available in a given date window
    // Excludes drivers already assigned to conflicting active rentals
    // -------------------------------------------------------
    public function getAvailable(string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            "SELECT d.driver_id,
                    CONCAT(d.first_name, ' ', d.last_name) AS full_name,
                    d.phone, d.license_number, d.license_expiry, d.license_type
             FROM drivers d
             WHERE d.deleted_at IS NULL
               AND d.status IN ('available', 'off_duty')
               AND d.driver_id NOT IN (
                   SELECT ra.driver_id
                   FROM rental_agreements ra
                   WHERE ra.driver_id IS NOT NULL
                     AND ra.status IN ('reserved','confirmed','active')
                     AND ra.rental_start_date < ?
                     AND ra.rental_end_date   > ?
               )
             ORDER BY d.last_name",
            [$endDate, $startDate]
        );
    }

    // -------------------------------------------------------
    // Update driver status
    // -------------------------------------------------------
    public function updateStatus(int $driverId, string $status): bool
    {
        $this->db->execute(
            "UPDATE drivers SET status = ?, updated_at = NOW() WHERE driver_id = ?",
            [$status, $driverId]
        );
        return true;
    }

    // -------------------------------------------------------
    // Get rental history for a driver
    // -------------------------------------------------------
    public function getAssignmentHistory(int $driverId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT ra.agreement_id, ra.agreement_number, ra.rental_start_date,
                    ra.rental_end_date, ra.actual_return_date, ra.status,
                    ra.chauffeur_fee,
                    CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                    v.brand, v.model, v.plate_number
             FROM rental_agreements ra
             JOIN customers c ON ra.customer_id = c.customer_id
             JOIN vehicles  v ON ra.vehicle_id  = v.vehicle_id
             WHERE ra.driver_id = ?
             ORDER BY ra.rental_start_date DESC
             LIMIT ?",
            [$driverId, $limit]
        );
    }

    // -------------------------------------------------------
    // Drivers whose license expires within N days
    // -------------------------------------------------------
    public function getExpiringLicenses(int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT *, DATEDIFF(license_expiry, CURDATE()) AS days_left
             FROM drivers
             WHERE deleted_at IS NULL
               AND DATEDIFF(license_expiry, CURDATE()) BETWEEN 0 AND ?
             ORDER BY license_expiry",
            [$days]
        );
    }

    // -------------------------------------------------------
    // Upload profile photo
    // -------------------------------------------------------
    private function uploadProfilePhoto(array $file, string $code): string
    {
        // Use finfo for server-side MIME detection — browser-supplied $file['type'] is spoofable
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($detectedType, $allowed, true)) {
            throw new Exception('Invalid image type. Allowed: JPG, PNG, GIF, WebP.');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('Profile photo must be under 5MB.');
        }

        $uploadDir = ASSETS_PATH . 'images/uploads/driver-photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $code . '_PHOTO_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save profile photo.');
        }

        // Standardize file separators for web (avoid broken image URLs on Windows servers)
        return str_replace('\\', '/', str_replace(BASE_PATH, '', $filepath));
    }
}
