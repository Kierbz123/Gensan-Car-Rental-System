<?php
/**
 * API Endpoint: Get Maintenance History for Vehicle
 * Method: GET
 * Query Params: vehicle_id (required), limit, page
 * Response: {success, data, pagination, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/api/v1/bootstrap.php';

handleCORSPreflight();

$response = [
    'success' => false,
    'data' => [],
    'pagination' => null,
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

    if (!hasPermission($user, 'maintenance.view')) {
        throw new Exception('Forbidden', 403);
    }

    // Get vehicle ID
    $vehicleId = $_GET['vehicle_id'] ?? null;

    if (empty($vehicleId)) {
        throw new Exception('Vehicle ID is required', 400);
    }

    $limit = min(100, intval($_GET['limit'] ?? 50));
    $page = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $db = Database::getInstance();

    // Get total count
    $count = $db->fetchColumn(
        "SELECT COUNT(*) FROM maintenance_logs WHERE vehicle_id = ?",
        [$vehicleId]
    );

    // Get history
    $history = $db->fetchAll(
        "SELECT ml.*, 
                CONCAT(u.first_name, ' ', u.last_name) as logger_name
         FROM maintenance_logs ml
         LEFT JOIN users u ON ml.created_by = u.user_id
         WHERE ml.vehicle_id = ?
         ORDER BY ml.service_date DESC
         LIMIT ? OFFSET ?",
        [$vehicleId, $limit, $offset]
    );

    // Decode JSON fields if they are stored as JSON
    foreach ($history as &$record) {
        if (!empty($record['parts_used'])) {
            $record['parts_used'] = json_decode($record['parts_used'], true);
        }
    }

    $response['success'] = true;
    $response['data'] = $history;
    $response['pagination'] = [
        'total' => $count,
        'page' => $page,
        'per_page' => $limit,
        'total_pages' => ceil($count / $limit)
    ];
    $response['message'] = 'Maintenance history retrieved successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
