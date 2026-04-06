<?php
/**
 * API Endpoint: Update Vehicle
 * Method: PUT/PATCH/POST
 * Request: FormData or JSON
 * Response: {success, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/api/v1/bootstrap.php';

handleCORSPreflight();

$response = [
    'success' => false,
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

    if (!hasPermission($user, 'vehicles.update')) {
        throw new Exception('Forbidden', 403);
    }

    // Get vehicle ID
    $vehicleId = $_GET['id'] ?? null;

    if (empty($vehicleId)) {
        throw new Exception('Vehicle ID is required', 400);
    }

    // Get input data
    $data = [];
    $input = file_get_contents('php://input');

    // Check if multipart form data
    $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
    if (strpos($contentType, 'multipart/form-data') !== false) {
        $data = $_POST;
        if (isset($_FILES['primary_photo']) && $_FILES['primary_photo']['tmp_name']) {
            $data['primary_photo'] = $_FILES['primary_photo'];
        }
    } else {
        $data = json_decode($input, true);
    }

    // Update vehicle
    $vehicle = new Vehicle();
    $vehicle->update($vehicleId, $data, $user['user_id']);

    $response['success'] = true;
    $response['message'] = 'Vehicle updated successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
