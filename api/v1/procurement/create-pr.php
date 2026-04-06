<?php
/**
 * API Endpoint: Create Purchase Request
 * Method: POST
 * Request: {department, required_date, urgency, purpose_summary, items: [...]}
 * Response: {success, data, message}
 */

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/bootstrap.php';

handleCORSPreflight();

$response = [
    'success' => false,
    'data' => null,
    'message' => ''
];

try {
    // Enforce POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method Not Allowed', 405);
    }

    // Authenticate
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    $user = authenticateAPI($token);

    if (!$user) {
        throw new Exception('Unauthorized', 401);
    }

    if (!hasPermission($user, 'procurement.create')) {
        throw new Exception('Forbidden', 403);
    }

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input)) {
        throw new Exception('No data provided', 400);
    }

    // Validate required fields
    if (empty($input['required_date'])) {
        throw new Exception('required_date is required', 400);
    }

    // Validate items
    if (empty($input['items']) || !is_array($input['items'])) {
        throw new Exception('At least one item is required', 400);
    }

    foreach ($input['items'] as $index => $item) {
        if (empty($item['description']) || empty($item['quantity']) || empty($item['unit']) || !isset($item['estimated_unit_cost'])) {
            throw new Exception("Item " . ($index + 1) . " is missing required fields", 400);
        }
    }

    // Create PR
    $pr = new ProcurementRequest();
    $result = $pr->create($input, $user['user_id']);

    $response['success'] = true;
    $response['data'] = $result;
    $response['message'] = 'Purchase request created successfully';
    http_response_code(201);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
