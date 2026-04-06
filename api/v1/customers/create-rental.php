<?php
/**
 * API Endpoint: Create Rental Agreement
 * Method: POST
 * Request: {customer_id, vehicle_id, rental_start_date, rental_end_date, daily_rate, security_deposit, ...}
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

    if (!hasPermission($user, 'rentals.create')) {
        throw new Exception('Forbidden', 403);
    }

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input)) {
        throw new Exception('No data provided', 400);
    }

    // Validate required fields
    $required = ['customer_id', 'vehicle_id', 'rental_start_date', 'rental_end_date', 'security_deposit'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("{$field} is required", 400);
        }
    }

    // Validate dates
    $start = new DateTime($input['rental_start_date']);
    $end = new DateTime($input['rental_end_date']);

    if ($end <= $start) {
        throw new Exception('End date must be after start date', 400);
    }

    // Create rental
    $customer = new Customer();
    $result = $customer->createRental($input, $user['user_id']);

    $response['success'] = true;
    $response['data'] = $result;
    $response['message'] = 'Rental agreement created successfully';
    http_response_code(201);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
