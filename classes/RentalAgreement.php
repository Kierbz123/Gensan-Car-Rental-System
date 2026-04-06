<?php
// /var/www/html/gensan-car-rental-system/classes/RentalAgreement.php

/**
 * Rental Agreement Management Class
 * Handles the core rental business logic
 */

class RentalAgreement
{
    private $db;
    private $agreementId;

    public function __construct($agreementId = null)
    {
        $this->db = Database::getInstance();
        $this->agreementId = $agreementId;
    }

    /**
     * Create a new rental reservation/agreement
     */
    public function create($data, $createdBy)
    {
        $this->db->beginTransaction();

        try {
            // Validate vehicle availability
            $vehicle = new Vehicle($data['vehicle_id']);
            $vehicleData = $vehicle->getById($data['vehicle_id']);

            if ($vehicleData['current_status'] !== VEHICLE_STATUS_AVAILABLE) {
                throw new Exception("Vehicle is not available for rental.");
            }

            // Calculate total amount (aligned with DB schema logic: hours / 24)
            $startTs = strtotime($data['start_date']);
            $endTs = strtotime($data['end_date']);
            $hours = ($endTs - $startTs) / 3600;
            $days = ceil($hours / 24);
            if ($days < 1)
                $days = 1;

            $totalAmount = $days * $data['rental_rate'];
            if (!empty($data['additional_fees'])) {
                $totalAmount += $data['additional_fees'];
            }

            // Generate Agreement Number
            $year = date('Y');
            $result = $this->db->fetchOne(
                "SELECT COALESCE(MAX(CAST(SUBSTRING(agreement_number, -4) AS UNSIGNED)), 0) + 1 as next_seq
                 FROM rental_agreements
                 WHERE YEAR(created_at) = ?",
                [$year]
            );
            $agreementNumber = "RA-GCR-" . $year . "-" . str_pad($result['next_seq'], 4, '0', STR_PAD_LEFT);

            $rentalType = $data['rental_type'] ?? 'self_drive';
            $driverId = !empty($data['driver_id']) ? (int) $data['driver_id'] : null;
            $chauffeurFee = (float) ($data['chauffeur_fee'] ?? 0);

            // Insert agreement
            $agreementId = $this->db->insert(
                "INSERT INTO rental_agreements 
                 (agreement_number, customer_id, vehicle_id, rental_start_date, rental_end_date, daily_rate, 
                  total_amount, security_deposit, status, pickup_location, 
                  return_location, rental_type, driver_id, chauffeur_fee, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $agreementNumber,
                    $data['customer_id'],
                    $data['vehicle_id'],
                    $data['start_date'],
                    $data['end_date'],
                    $data['rental_rate'],
                    $totalAmount,
                    $data['security_deposit'] ?? 0,
                    RENTAL_STATUS_CONFIRMED,
                    $data['pickup_location'] ?? 'main_office',
                    $data['return_location'] ?? 'main_office',
                    $rentalType,
                    $driverId,
                    $chauffeurFee,
                    $createdBy
                ]
            );

            // Driver status is set to on_duty only upon vehicle dispatch (checkout), not at reservation time.

            // Update vehicle status
            $vehicle->updateStatus(
                $data['vehicle_id'],
                VEHICLE_STATUS_RESERVED,
                $createdBy,
                null,
                null,
                "Reserved for Rental #{$agreementId}",
                $agreementId
            );

            $this->db->commit();

            // Log audit
            if (class_exists('AuditLogger')) {
                AuditLogger::log(
                    $createdBy,
                    null,
                    null,
                    'create',
                    'rentals',
                    'rental_agreements',
                    $agreementId,
                    "Created rental agreement #{$agreementId}",
                    null,
                    json_encode($data),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'POST',
                    '/rentals/create',
                    'info'
                );
            }

            return $agreementId;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Complete the check-out (release vehicle)
     */
    public function checkout($agreementId, $mileage, $processedBy)
    {
        $agreement = $this->getById($agreementId);

        if ($agreement['status'] !== RENTAL_STATUS_CONFIRMED && $agreement['status'] !== RENTAL_STATUS_RESERVED) {
            throw new Exception("Agreement is not in a state that can be checked out.");
        }

        $this->db->execute(
            "UPDATE rental_agreements 
             SET status = ?, mileage_at_pickup = ?
             WHERE agreement_id = ?",
            [RENTAL_STATUS_ACTIVE, $mileage, $agreementId]
        );

        // Mark driver as on_duty when vehicle is physically dispatched for a chauffeur rental
        if (!empty($agreement['driver_id']) && $agreement['rental_type'] === 'chauffeur') {
            $this->db->execute(
                "UPDATE drivers SET status = 'on_duty', updated_at = NOW() WHERE driver_id = ?",
                [$agreement['driver_id']]
            );
        }

        $vehicle = new Vehicle($agreement['vehicle_id']);
        $vehicle->updateStatus(
            $agreement['vehicle_id'],
            VEHICLE_STATUS_RENTED,
            $processedBy,
            null,
            $mileage,
            "Vehicle checked out for Rental #{$agreementId}",
            $agreementId
        );

        return true;
    }

    /**
     * Cancel a rental agreement
     *
     * Only agreements in 'reserved', 'confirmed', or 'active' status
     * may be cancelled.  The vehicle is returned to 'available' and the
     * driver (if any) is freed.
     *
     * @param int    $agreementId
     * @param int    $cancelledBy  User ID performing the action
     * @param string $reason       Optional cancellation reason
     * @return bool
     * @throws Exception
     */
    public function cancel($agreementId, $cancelledBy, $reason = null)
    {
        $agreement = $this->getById($agreementId);

        if (!$agreement) {
            throw new Exception('Rental agreement not found.');
        }

        $cancellable = [RENTAL_STATUS_RESERVED, RENTAL_STATUS_CONFIRMED, RENTAL_STATUS_ACTIVE];
        if (!in_array($agreement['status'], $cancellable)) {
            throw new Exception("This agreement cannot be cancelled (current status: {$agreement['status']}).");
        }

        $this->db->beginTransaction();

        try {
            // Mark the agreement cancelled
            $this->db->execute(
                "UPDATE rental_agreements
                 SET status = ?, cancellation_reason = ?
                 WHERE agreement_id = ?",
                [RENTAL_STATUS_CANCELLED, $reason, $agreementId]
            );

            // Restore vehicle to available
            $vehicle = new Vehicle();
            $vehicle->updateStatus(
                $agreement['v_id'],
                VEHICLE_STATUS_AVAILABLE,
                $cancelledBy,
                null,
                null,
                "Rental #{$agreementId} cancelled." . ($reason ? " Reason: {$reason}" : ''),
                $agreementId
            );

            // Free the chauffeur driver if assigned
            if (!empty($agreement['driver_id'])) {
                $this->db->execute(
                    "UPDATE drivers SET status = 'available', updated_at = NOW() WHERE driver_id = ?",
                    [$agreement['driver_id']]
                );
            }

            $this->db->commit();

            // Audit log
            if (class_exists('AuditLogger')) {
                AuditLogger::log(
                    $cancelledBy,
                    null,
                    null,
                    'update',
                    'rentals',
                    'rental_agreements',
                    $agreementId,
                    "Cancelled rental agreement #{$agreementId}" . ($reason ? " — {$reason}" : ''),
                    json_encode(['status' => $agreement['status']]),
                    json_encode(['status' => RENTAL_STATUS_CANCELLED]),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'POST',
                    '/rentals/cancel',
                    'warning'
                );
            }

            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Complete the check-in (return vehicle)
     */
    public function checkin($agreementId, $mileage, $processedBy, $receivedBy)
    {
        $agreement = $this->getById($agreementId);

        if ($agreement['status'] !== RENTAL_STATUS_ACTIVE) {
            throw new Exception("Rental is not active.");
        }

        $this->db->execute(
            "UPDATE rental_agreements 
             SET status = ?, actual_return_date = NOW(), mileage_at_return = ?
             WHERE agreement_id = ?",
            [RENTAL_STATUS_RETURNED, $mileage, $agreementId]
        );

        // Free the driver when chauffeur rental is returned
        if (!empty($agreement['driver_id'])) {
            $this->db->execute(
                "UPDATE drivers SET status = 'available', updated_at = NOW() WHERE driver_id = ?",
                [$agreement['driver_id']]
            );
        }

        $vehicle = new Vehicle($agreement['vehicle_id']);
        $vehicle->updateStatus(
            $agreement['vehicle_id'],
            VEHICLE_STATUS_CLEANING,
            $processedBy,
            null,
            $mileage,
            "Vehicle returned from Rental #{$agreementId}. Pending cleaning.",
            $agreementId
        );

        return true;
    }

    /**
     * Get agreement details
     */
    public function getById($agreementId)
    {
        return $this->db->fetchOne(
            "SELECT ra.*, 
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    c.email as customer_email, c.phone_primary as customer_phone,
                    v.brand, v.model, v.plate_number, v.vehicle_id as v_id,
                    CONCAT(d.first_name, ' ', d.last_name) as driver_name,
                    d.phone as driver_phone, d.license_number as driver_license,
                    d.license_expiry as driver_license_expiry, d.employee_code as driver_code
             FROM rental_agreements ra
             JOIN customers c ON ra.customer_id = c.customer_id
             JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
             LEFT JOIN drivers d ON ra.driver_id = d.driver_id
             WHERE ra.agreement_id = ?",
            [$agreementId]
        );
    }

    /**
     * Get rental statistics
     */
    public function getStats()
    {
        return $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = ? AND rental_end_date < CURDATE() THEN 1 ELSE 0 END) as overdue
            FROM rental_agreements",
            [RENTAL_STATUS_RESERVED, RENTAL_STATUS_CONFIRMED, RENTAL_STATUS_ACTIVE, RENTAL_STATUS_ACTIVE]
        );
    }

    /**
     * List agreements with filters and pagination metadata
     */
    public function getAll($filters = [], $page = 1, $perPage = 20)
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status_exclude'])) {
            if (is_array($filters['status_exclude'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status_exclude']), '?'));
                $where[] = "ra.status NOT IN ($placeholders)";
                $params = array_merge($params, array_values($filters['status_exclude']));
            } else {
                $where[] = "ra.status != ?";
                $params[] = $filters['status_exclude'];
            }
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $where[] = "ra.status IN ($placeholders)";
                $params = array_merge($params, array_values($filters['status']));
            } else {
                $where[] = "ra.status = ?";
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['customer_id'])) {
            $where[] = "ra.customer_id = ?";
            $params[] = $filters['customer_id'];
        }

        if (!empty($filters['vehicle_id'])) {
            $where[] = "ra.vehicle_id = ?";
            $params[] = $filters['vehicle_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(ra.agreement_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search, $search, $search]);
        }


        $whereClause = implode(' AND ', $where);

        // Get total count for pagination
        $totalCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM rental_agreements ra WHERE {$whereClause}",
            $params
        );

        $offset = ($page - 1) * $perPage;

        $prs = $this->db->fetchAll(
            "SELECT ra.*, 
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    v.plate_number, v.brand, v.model
             FROM rental_agreements ra
             JOIN customers c ON ra.customer_id = c.customer_id
             JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
             WHERE {$whereClause}
             ORDER BY ra.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'data' => $prs,
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($totalCount / $perPage)
        ];
    }
}
