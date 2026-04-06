<?php
/**
 * API Endpoint: Get Single Vehicle
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

    if (!hasPermission($user, 'vehicles.view')) {
        throw new Exception('Forbidden', 403);
    }

    // Get vehicle ID
    $vehicleId = $_GET['id'] ?? null;

    if (empty($vehicleId)) {
        throw new Exception('Vehicle ID is required', 400);
    }

    // Get vehicle details
    $vehicle = new Vehicle();
    $data = $vehicle->getById($vehicleId);

    if (!$data) {
        throw new Exception('Vehicle not found', 404);
    }

    // Get additional data
    $db = Database::getInstance();

    // Get photos
    $data['photos'] = $db->fetchAll(
        "SELECT * FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC, upload_date DESC",
        [$vehicleId]
    );

    // Get status history
    $data['status_history'] = $vehicle->getStatusHistory($vehicleId, 10);

    // Get current rental if any
    $data['current_rental'] = $db->fetchOne(
        "SELECT ra.*, c.first_name, c.last_name, c.phone_primary
         FROM rental_agreements ra
         JOIN customers c ON ra.customer_id = c.customer_id
         WHERE ra.vehicle_id = ? AND ra.status IN ('reserved', 'confirmed', 'active')
         ORDER BY ra.rental_start_date DESC
         LIMIT 1",
        [$vehicleId]
    );

    // Get upcoming maintenance
    $data['upcoming_maintenance'] = $db->fetchAll(
        "SELECT * FROM upcoming_maintenance WHERE vehicle_id = ? AND days_until_due <= 30",
        [$vehicleId]
    );

    $response['success'] = true;
    $response['data'] = $data;
    $response['message'] = 'Vehicle retrieved successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
