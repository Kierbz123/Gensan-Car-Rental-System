<?php
/**
 * API Endpoint: Delete Vehicle (Soft Delete)
 * Method: DELETE
 * Query Params: id, reason (optional)
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

    if (!hasPermission($user, 'vehicles.delete')) {
        throw new Exception('Forbidden', 403);
    }

    // Get vehicle ID
    $vehicleId = $_GET['id'] ?? null;

    if (empty($vehicleId)) {
        throw new Exception('Vehicle ID is required', 400);
    }

    $reason = $_GET['reason'] ?? null;

    // Delete vehicle
    $vehicle = new Vehicle();
    $vehicle->delete($vehicleId, $user['user_id'], $reason);

    $response['success'] = true;
    $response['message'] = 'Vehicle deleted successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
