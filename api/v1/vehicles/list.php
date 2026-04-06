<?php
/**
 * API Endpoint: List Vehicles
 * Method: GET
 * Query Params: category, status, location, search, page, per_page
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

    // Check permission
    if (!hasPermission($user, 'vehicles.view')) {
        throw new Exception('Forbidden', 403);
    }

    // Get filters from query params
    $filters = [
        'category_id' => $_GET['category'] ?? null,
        'status' => $_GET['status'] ?? null,
        'location' => $_GET['location'] ?? null,
        'search' => $_GET['search'] ?? null,
        'year_model' => $_GET['year'] ?? null,
        'fuel_type' => $_GET['fuel'] ?? null
    ];

    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = min(100, intval($_GET['per_page'] ?? ITEMS_PER_PAGE));

    // Get vehicles
    $vehicle = new Vehicle();
    $result = $vehicle->getAll(array_filter($filters), $page, $perPage);

    $response['success'] = true;
    $response['data'] = $result['data'];
    $response['pagination'] = [
        'total' => $result['total'],
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total_pages' => $result['total_pages']
    ];
    $response['message'] = 'Vehicles retrieved successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
