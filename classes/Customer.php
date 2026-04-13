<?php
// /var/www/html/gensan-car-rental-system/classes/Customer.php

/**
 * Customer Management Class
 */

class Customer
{
    private $db;
    private $customerId;

    public function __construct($customerId = null)
    {
        $this->db = Database::getInstance();
        $this->customerId = $customerId;
    }

    /**
     * Create new customer
     */
    public function create($data, $createdBy)
    {
        // Validate required fields
        $required = ['first_name', 'last_name', 'phone_primary'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("{$field} is required.");
            }
        }

        // Validate email format if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address format.");
        }

        // Validate phone_primary length (max 20 chars to match DB column)
        if (strlen($data['phone_primary']) > 20) {
            throw new Exception("Primary phone number is too long (max 20 characters).");
        }

        // Generate customer code
        $customerCode = $this->generateCustomerCode();

        // Handle ID photo upload
        $idFrontPath = null;
        $idBackPath = null;
        $profilePicturePath = null;

        if (!empty($data['id_photo_front']) && $data['id_photo_front']['tmp_name']) {
            $this->validateUploadedFile($data['id_photo_front']);
            $idFrontPath = $this->uploadIDPhoto($data['id_photo_front'], $customerCode, 'front');
        }

        if (!empty($data['id_photo_back']) && $data['id_photo_back']['tmp_name']) {
            $this->validateUploadedFile($data['id_photo_back']);
            $idBackPath = $this->uploadIDPhoto($data['id_photo_back'], $customerCode, 'back');
        }

        if (!empty($data['profile_picture']) && $data['profile_picture']['tmp_name']) {
            $this->validateUploadedFile($data['profile_picture'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
            $profilePicturePath = $this->uploadProfilePicture($data['profile_picture'], $customerCode);
        }

        $customerId = $this->db->insert(
            "INSERT INTO customers 
             (customer_code, customer_type, first_name, last_name, middle_name,
              date_of_birth, gender, phone_primary, phone_secondary, email,
              address, city, province, zip_code,
              id_type, id_number, id_expiry_date, id_photo_front_path, id_photo_back_path,
              profile_picture_path,
              company_name, company_address, company_phone, company_email, authorized_representative,
              emergency_name, emergency_phone, emergency_relationship,
              notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $customerCode,
                $data['customer_type'] ?? 'walk_in',
                $data['first_name'],
                $data['last_name'],
                $data['middle_name'] ?? null,
                !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                $data['gender'] ?? null,
                $data['phone_primary'],
                $data['phone_secondary'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? 'General Santos City',
                $data['province'] ?? 'South Cotabato',
                $data['zip_code'] ?? null,
                $data['id_type'] ?? 'drivers_license',
                $data['id_number'] ?? null,
                $data['id_expiry_date'] ?? null,
                $idFrontPath,
                $idBackPath,
                $profilePicturePath,
                $data['company_name'] ?? null,
                $data['company_address'] ?? null,
                $data['company_phone'] ?? null,
                $data['company_email'] ?? null,
                $data['authorized_representative'] ?? null,
                $data['emergency_name'] ?? null,
                $data['emergency_phone'] ?? null,
                $data['emergency_relationship'] ?? null,
                $data['notes'] ?? null,
                $createdBy
            ]
        );

        // Log audit
        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $createdBy,
                null,
                null,
                'create',
                'customers',
                'customers',
                $customerId,
                "Created customer: {$data['first_name']} {$data['last_name']} ({$customerCode})",
                null,
                json_encode(['customer_code' => $customerCode, 'type' => $data['customer_type'] ?? 'walk_in']),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/customers/create',
                'info'
            );
        }

        return ['customer_id' => $customerId, 'customer_code' => $customerCode];
    }

    /**
     * Update customer
     */
    public function update($customerId, $data, $updatedBy)
    {
        // Validate required fields
        $required = ['first_name', 'last_name', 'phone_primary'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("{$field} is required.");
            }
        }

        // Fix empty date of birth throwing errors for DATE type
        $dob = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;

        $updates = [
            "customer_type = ?",
            "first_name = ?",
            "last_name = ?",
            "middle_name = ?",
            "date_of_birth = ?",
            "phone_primary = ?",
            "phone_secondary = ?",
            "email = ?",
            "address = ?",
            "city = ?",
            "province = ?",
            "notes = ?"
        ];
        $params = [
            $data['customer_type'] ?? 'walk_in',
            $data['first_name'],
            $data['last_name'],
            $data['middle_name'] ?? null,
            $dob,
            $data['phone_primary'],
            $data['phone_secondary'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? 'General Santos City',
            $data['province'] ?? 'South Cotabato',
            $data['notes'] ?? null
        ];

        $customerCode = $this->db->fetchColumn("SELECT customer_code FROM customers WHERE customer_id = ?", [$customerId]);

        if (!empty($data['id_photo_front']) && !empty($data['id_photo_front']['tmp_name'])) {
            $idFrontPath = $this->uploadIDPhoto($data['id_photo_front'], $customerCode, 'front');
            $updates[] = "id_photo_front_path = ?";
            $params[] = $idFrontPath;
        }

        if (!empty($data['id_photo_back']) && !empty($data['id_photo_back']['tmp_name'])) {
            $idBackPath = $this->uploadIDPhoto($data['id_photo_back'], $customerCode, 'back');
            $updates[] = "id_photo_back_path = ?";
            $params[] = $idBackPath;
        }

        if (!empty($data['profile_picture']) && !empty($data['profile_picture']['tmp_name'])) {
            $profilePicturePath = $this->uploadProfilePicture($data['profile_picture'], $customerCode);
            $updates[] = "profile_picture_path = ?";
            $params[] = $profilePicturePath;
        }

        $params[] = $customerId;

        $this->db->execute(
            "UPDATE customers SET " . implode(', ', $updates) . " WHERE customer_id = ?",
            $params
        );

        // Log audit
        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $updatedBy,
                null,
                null,
                'update',
                'customers',
                'customers',
                $customerId,
                "Updated customer profile for: {$data['first_name']} {$data['last_name']}",
                null,
                null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/customers/edit',
                'info'
            );
        }

        return true;
    }

    /**
     * Delete (soft-delete) customer
     */
    public function delete($customerId, $deletedBy)
    {
        // Prevent deletion if customer has active rentals
        $activeRentals = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM rental_agreements WHERE customer_id = ? AND status IN (?, ?, ?)",
            [$customerId, RENTAL_STATUS_RESERVED, RENTAL_STATUS_CONFIRMED, RENTAL_STATUS_ACTIVE]
        );

        if ($activeRentals > 0) {
            throw new Exception("Cannot delete customer with active or reserved rental agreements.");
        }

        $customer = $this->db->fetchOne("SELECT * FROM customers WHERE customer_id = ?", [$customerId]);
        if (!$customer) {
            throw new Exception("Customer not found.");
        }

        $this->db->execute(
            "UPDATE customers SET deleted_at = NOW() WHERE customer_id = ?",
            [$customerId]
        );

        // Log audit
        if (class_exists('AuditLogger')) {
            AuditLogger::log(
                $deletedBy,
                null,
                null,
                'delete',
                'customers',
                'customers',
                $customerId,
                "Deleted customer profile: {$customer['first_name']} {$customer['last_name']} ({$customer['customer_code']})",
                json_encode(['id' => $customerId]),
                null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                'POST',
                '/customers/delete',
                'warning'
            );
        }

        return true;
    }

    /**
     * Create rental agreement
     */
    public function createRental($data, $createdBy)
    {
        // Validate vehicle availability
        $vehicle = $this->db->fetchOne(
            "SELECT current_status, daily_rental_rate FROM vehicles WHERE vehicle_id = ?",
            [$data['vehicle_id']]
        );

        if (!$vehicle || $vehicle['current_status'] !== VEHICLE_STATUS_AVAILABLE) {
            throw new Exception("Vehicle is not available for rental.");
        }

        // Validate customer
        $customer = $this->db->fetchOne(
            "SELECT is_blacklisted, credit_rating FROM customers WHERE customer_id = ?",
            [$data['customer_id']]
        );

        if (!$customer) {
            throw new Exception("Customer not found.");
        }

        if ($customer['is_blacklisted']) {
            throw new Exception("Customer is blacklisted and cannot rent.");
        }

        // Generate agreement number
        $agreementNumber = $this->generateAgreementNumber();

        // Calculate totals
        $dailyRate = $data['daily_rate'] ?? $vehicle['daily_rental_rate'];
        $start = new DateTime($data['rental_start_date']);
        $end = new DateTime($data['rental_end_date']);
        $interval = $start->diff($end);
        $days = $interval->days > 0 ? $interval->days : 1;
        $baseAmount = $dailyRate * $days;

        $subtotal = $baseAmount
            + ($data['additional_driver_fee'] ?? 0)
            + ($data['insurance_fee'] ?? 0)
            + ($data['gps_fee'] ?? 0)
            + ($data['child_seat_fee'] ?? 0)
            + ($data['other_charges'] ?? 0)
            - ($data['discount_amount'] ?? 0);

        $taxAmount = $subtotal * 0.12; // 12% VAT
        $totalAmount = $subtotal + $taxAmount;

        $this->db->beginTransaction();

        try {
            $this->db->execute(
                "INSERT INTO rental_agreements 
                 (agreement_number, customer_id, vehicle_id, rental_start_date, rental_end_date,
                  daily_rate, additional_driver_fee, insurance_fee, gps_fee, child_seat_fee,
                  other_charges, discount_amount, discount_reason, subtotal, tax_amount, total_amount,
                  security_deposit, fuel_policy, mileage_limit, excess_mileage_charge,
                  pickup_location, return_location, notes, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $agreementNumber,
                    $data['customer_id'],
                    $data['vehicle_id'],
                    $data['rental_start_date'],
                    $data['rental_end_date'],
                    $dailyRate,
                    $data['additional_driver_fee'] ?? 0,
                    $data['insurance_fee'] ?? 0,
                    $data['gps_fee'] ?? 0,
                    $data['child_seat_fee'] ?? 0,
                    $data['other_charges'] ?? 0,
                    $data['discount_amount'] ?? 0,
                    $data['discount_reason'] ?? null,
                    $subtotal,
                    $taxAmount,
                    $totalAmount,
                    $data['security_deposit'],
                    $data['fuel_policy'] ?? 'full_to_full',
                    $data['mileage_limit'] ?? 0,
                    $data['excess_mileage_charge'] ?? 10.00,
                    $data['pickup_location'] ?? 'main_office',
                    $data['return_location'] ?? 'main_office',
                    $data['notes'] ?? null,
                    RENTAL_STATUS_RESERVED,
                    $createdBy
                ]
            );

            $agreementId = $this->db->lastInsertId();

            // Reserve vehicle
            $this->db->execute(
                "UPDATE vehicles SET current_status = ? WHERE vehicle_id = ?",
                [VEHICLE_STATUS_RESERVED, $data['vehicle_id']]
            );

            // Log status change
            $this->db->execute(
                "INSERT INTO vehicle_status_logs 
                 (vehicle_id, previous_status, new_status, reason, changed_by, related_rental_id)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $data['vehicle_id'],
                    VEHICLE_STATUS_AVAILABLE,
                    VEHICLE_STATUS_RESERVED,
                    'Rental agreement created: ' . $agreementNumber,
                    $createdBy,
                    $agreementId
                ]
            );

            $this->db->commit();

            // Generate PDF agreement
            $pdfPath = $this->generateAgreementPDF($agreementId);

            if (class_exists('AuditLogger')) {
                AuditLogger::log(
                    $createdBy,
                    null,
                    null,
                    'create',
                    'customers',
                    'rental_agreements',
                    $agreementId,
                    "Created rental agreement: {$agreementNumber}",
                    null,
                    json_encode(['vehicle_id' => $data['vehicle_id'], 'customer_id' => $data['customer_id'], 'total' => $totalAmount]),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'POST',
                    '/rentals/create',
                    'info'
                );
            }

            return ['agreement_id' => $agreementId, 'agreement_number' => $agreementNumber, 'pdf_path' => $pdfPath];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Process vehicle pickup
     */
    public function processPickup($agreementId, $data, $processedBy)
    {
        $agreement = $this->db->fetchOne(
            "SELECT * FROM rental_agreements WHERE agreement_id = ?",
            [$agreementId]
        );

        if (!$agreement || $agreement['status'] !== RENTAL_STATUS_RESERVED) {
            throw new Exception("Invalid agreement status for pickup.");
        }

        $this->db->beginTransaction();

        try {
            // Update agreement
            $this->db->execute(
                "UPDATE rental_agreements 
                 SET status = ?, 
                     picked_up_by_staff = ?,
                     picked_up_by = ?,
                     mileage_at_pickup = ?,
                     customer_signature_path = ?,
                     staff_signature_path = ?,
                     checklist_pickup_path = ?
                 WHERE agreement_id = ?",
                [
                    RENTAL_STATUS_ACTIVE,
                    $processedBy,
                    $data['picked_up_by'] ?? null,
                    $data['mileage_at_pickup'],
                    $data['customer_signature'] ?? null,
                    $data['staff_signature'] ?? null,
                    $data['checklist_path'] ?? null,
                    $agreementId
                ]
            );

            // Update vehicle status
            $this->db->execute(
                "UPDATE vehicles 
                 SET current_status = ?, 
                     current_location = 'with_customer',
                     mileage = ?
                 WHERE vehicle_id = ?",
                [
                    VEHICLE_STATUS_RENTED,
                    $data['mileage_at_pickup'],
                    $agreement['vehicle_id']
                ]
            );

            // Log status change
            $this->db->execute(
                "INSERT INTO vehicle_status_logs 
                 (vehicle_id, previous_status, new_status, previous_mileage, new_mileage,
                  reason, changed_by, related_rental_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $agreement['vehicle_id'],
                    VEHICLE_STATUS_RESERVED,
                    VEHICLE_STATUS_RENTED,
                    0, // Previous mileage not tracked here
                    $data['mileage_at_pickup'],
                    'Vehicle picked up by customer',
                    $processedBy,
                    $agreementId
                ]
            );

            $this->db->commit();

            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Process vehicle return
     */
    public function processReturn($agreementId, $data, $processedBy)
    {
        $agreement = $this->db->fetchOne(
            "SELECT * FROM rental_agreements WHERE agreement_id = ?",
            [$agreementId]
        );

        if (!$agreement || $agreement['status'] !== RENTAL_STATUS_ACTIVE) {
            throw new Exception("Invalid agreement status for return.");
        }

        // Calculate excess mileage charges
        $excessMileage = 0;
        $excessCharge = 0;

        if ($agreement['mileage_limit'] > 0) {
            $actualMileage = $data['mileage_at_return'] - $agreement['mileage_at_pickup'];
            if ($actualMileage > $agreement['mileage_limit']) {
                $excessMileage = $actualMileage - $agreement['mileage_limit'];
                $excessCharge = $excessMileage * $agreement['excess_mileage_charge'];
            }
        }

        $this->db->beginTransaction();

        try {
            // Update agreement
            $this->db->execute(
                "UPDATE rental_agreements 
                 SET status = ?,
                     actual_return_date = NOW(),
                     returned_by = ?,
                     received_by_staff = ?,
                     mileage_at_return = ?,
                     excess_mileage_charged = ?,
                     checklist_return_path = ?,
                     total_amount = total_amount + ?
                 WHERE agreement_id = ?",
                [
                    RENTAL_STATUS_RETURNED,
                    $data['returned_by'] ?? null,
                    $processedBy,
                    $data['mileage_at_return'],
                    $excessMileage,
                    $data['checklist_path'] ?? null,
                    $excessCharge,
                    $agreementId
                ]
            );

            // Update vehicle
            $this->db->execute(
                "UPDATE vehicles 
                 SET current_status = ?,
                     current_location = 'main_office',
                     mileage = ?
                 WHERE vehicle_id = ?",
                [
                    VEHICLE_STATUS_CLEANING, // Needs cleaning before available again
                    $data['mileage_at_return'],
                    $agreement['vehicle_id']
                ]
            );

            // Log status change
            $this->db->execute(
                "INSERT INTO vehicle_status_logs 
                 (vehicle_id, previous_status, new_status, previous_mileage, new_mileage,
                  reason, changed_by, related_rental_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $agreement['vehicle_id'],
                    VEHICLE_STATUS_RENTED,
                    VEHICLE_STATUS_CLEANING,
                    $agreement['mileage_at_pickup'],
                    $data['mileage_at_return'],
                    'Vehicle returned by customer',
                    $processedBy,
                    $agreementId
                ]
            );

            $this->db->commit();

            return ['excess_mileage' => $excessMileage, 'excess_charge' => $excessCharge];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get aggregate statistics for the dashboard
     */
    public function getStats()
    {
        return $this->db->fetchOne("
            SELECT 
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN customer_type = 'corporate' THEN 1 ELSE 0 END), 0) as corporate,
                COALESCE(SUM(CASE WHEN customer_type != 'corporate' THEN 1 ELSE 0 END), 0) as individual,
                COALESCE(SUM(CASE WHEN is_blacklisted = 1 THEN 1 ELSE 0 END), 0) as blacklisted
            FROM customers WHERE deleted_at IS NULL
        ");
    }

    /**
     * Retrieve paginated clients with advanced sorting algorithms mapped.
     */
    public function getAll($filters = [], $page = 1, $perPage = 10)
    {
        $where = ["deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = "customer_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['blacklisted'])) {
            $where[] = "is_blacklisted = 1";
        }

        if (!empty($filters['search'])) {
            $where[] = "(first_name LIKE ? OR last_name LIKE ? OR customer_code LIKE ? OR email LIKE ? OR phone_primary LIKE ?)";
            $s = "%" . $filters['search'] . "%";
            $params = array_merge($params, [$s, $s, $s, $s, $s]);
        }

        $whereClause = implode(' AND ', $where);

        $sortBy = 'created_at';
        $sortOrder = 'DESC';

        if (!empty($filters['sort_by'])) {
            $allowedSorts = ['first_name', 'phone_primary', 'id_type', 'customer_type', 'is_blacklisted'];
            if (in_array($filters['sort_by'], $allowedSorts)) {
                $sortBy = $filters['sort_by'];
            }
        }

        if (!empty($filters['sort_order']) && in_array(strtoupper($filters['sort_order']), ['ASC', 'DESC'])) {
            $sortOrder = strtoupper($filters['sort_order']);
        }

        $totalCount = (int) ($this->db->fetchColumn("SELECT COUNT(*) FROM customers WHERE $whereClause", $params) ?? 0);
        $offset = ($page - 1) * $perPage;

        $customers = $this->db->fetchAll(
            "SELECT * FROM customers WHERE $whereClause ORDER BY {$sortBy} {$sortOrder} LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        ) ?: [];

        return [
            'data' => $customers,
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalCount > 0 ? ceil($totalCount / $perPage) : 1
        ];
    }

    /**
     * Generate customer code
     */
    private function generateCustomerCode()
    {
        $result = $this->db->fetchOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code, -5) AS UNSIGNED)), 0) + 1 as next_seq
             FROM customers"
        );

        $sequence = str_pad($result['next_seq'], 5, '0', STR_PAD_LEFT);
        return "CUST-{$sequence}";
    }

    /**
     * Generate agreement number
     */
    private function generateAgreementNumber()
    {
        $year = date('Y');
        $result = $this->db->fetchOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(agreement_number, -4) AS UNSIGNED)), 0) + 1 as next_seq
             FROM rental_agreements
             WHERE YEAR(created_at) = ?",
            [$year]
        );

        $sequence = str_pad($result['next_seq'], 4, '0', STR_PAD_LEFT);
        return "RA-GCR-{$year}-{$sequence}";
    }

    /**
     * Validate an uploaded file for type and size
     */
    private function validateUploadedFile($file, $allowedTypes = null)
    {
        if ($allowedTypes === null) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
        }

        // Use finfo for reliable MIME detection (not the browser-supplied type)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($detectedType, $allowedTypes, true)) {
            throw new Exception("Invalid file type ({$detectedType}). Allowed: " . implode(', ', $allowedTypes));
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception("File size exceeds the maximum limit of " . (MAX_UPLOAD_SIZE / 1024 / 1024) . " MB.");
        }
    }

    /**
     * Upload ID photo
     */
    private function uploadIDPhoto($file, $customerCode, $side)
    {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $customerCode . '_ID_' . $side . '_' . time() . '.' . $extension;
        if (!is_dir(CUSTOMER_IDS_PATH)) {
            mkdir(CUSTOMER_IDS_PATH, 0755, true);
        }
        $filepath = CUSTOMER_IDS_PATH . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Failed to upload ID photo.");
        }

        return str_replace(BASE_PATH, '', $filepath);
    }

    /**
     * Upload Profile Picture
     */
    private function uploadProfilePicture($file, $customerCode)
    {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $customerCode . '_PROFILE_' . time() . '.' . $extension;
        if (!is_dir(CUSTOMER_IDS_PATH)) {
            mkdir(CUSTOMER_IDS_PATH, 0755, true);
        }
        $filepath = CUSTOMER_IDS_PATH . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Failed to upload profile picture.");
        }

        return str_replace(BASE_PATH, '', $filepath);
    }

    /**
     * Generate agreement PDF
     */
    private function generateAgreementPDF($agreementId)
    {
        // Implementation using TCPDF or similar library
        // Returns path to generated PDF
        return null; // Placeholder
    }
}
