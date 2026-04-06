<?php
/**
 * API Endpoint: Get Single Customer
 * Method: GET
 * Query Params: id (required)
 * Response: {success, data, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/api/v1/bootstrap.php';

handleCORSPreflight();

$response = [
    'success' => false,
    'data' => null,
    'message' => ''
];

try {
    // Authenticate
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    $user = authenticateAPI($token);

    if (!$user) {
        throw new Exception('Unauthorized', 401);
    }

    if (!hasPermission($user, 'customers.view')) {
        throw new Exception('Forbidden', 403);
    }

    // Get customer ID
    $customerId = $_GET['id'] ?? null;

    if (empty($customerId)) {
        throw new Exception('Customer ID is required', 400);
    }

    $db = Database::getInstance();

    // Get customer
    $customer = $db->fetchOne(
        "SELECT c.*, 
                CONCAT(cb.first_name, ' ', cb.last_name) as blacklisted_by_name
         FROM customers c
         LEFT JOIN users cb ON c.blacklisted_by = cb.user_id
         WHERE c.customer_id = ? AND c.deleted_at IS NULL",
        [$customerId]
    );

    if (!$customer) {
        throw new Exception('Customer not found', 404);
    }

    // Get rental history
    $customer['rental_history'] = $db->fetchAll(
        "SELECT ra.*, v.plate_number, v.brand, v.model
         FROM rental_agreements ra
         JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
         WHERE ra.customer_id = ?
         ORDER BY ra.rental_start_date DESC
         LIMIT 10",
        [$customerId]
    );

    // Get recent damage reports
    $customer['damage_reports'] = $db->fetchAll(
        "SELECT dr.*, ra.agreement_number, v.plate_number
         FROM damage_reports dr
         JOIN rental_agreements ra ON dr.agreement_id = ra.agreement_id
         JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
         WHERE ra.customer_id = ?
         ORDER BY dr.discovered_date DESC
         LIMIT 5",
        [$customerId]
    );

    $response['success'] = true;
    $response['data'] = $customer;
    $response['message'] = 'Customer retrieved successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
