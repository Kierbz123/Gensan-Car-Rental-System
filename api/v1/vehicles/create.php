<?php
/**
 * API Endpoint: Create Vehicle
 * Method: POST
 * Request: FormData (multipart for file upload)
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

    if (!hasPermission($user, 'vehicles.create')) {
        throw new Exception('Forbidden', 403);
    }

    // Get POST data
    $data = $_POST;

    // Handle file upload
    if (isset($_FILES['primary_photo']) && $_FILES['primary_photo']['tmp_name']) {
        $data['primary_photo'] = $_FILES['primary_photo'];
    }

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
            throw new Exception("{$field} is required", 400);
        }
    }

    // Create vehicle
    $vehicle = new Vehicle();
    $result = $vehicle->create($data, $user['user_id']);

    $response['success'] = true;
    $response['data'] = $result;
    $response['message'] = 'Vehicle created successfully';
    http_response_code(201);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
