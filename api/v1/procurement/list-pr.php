<?php
/**
 * API Endpoint: List Purchase Requests
 * Method: GET
 * Query Params: status, requestor_id, department, urgency, date_from, date_to, page, per_page
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

    if (!hasPermission($user, 'procurement.view')) {
        throw new Exception('Forbidden', 403);
    }

    // Build filters
    $filters = [
        'status' => $_GET['status'] ?? null,
        'requestor_id' => $_GET['requestor_id'] ?? null,
        'department' => $_GET['department'] ?? null,
        'urgency' => $_GET['urgency'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null
    ];

    // Special filter for pending approvals
    if (isset($_GET['pending_my_approval']) && $_GET['pending_my_approval'] == '1') {
        $filters['pending_my_approval'] = $user['user_id'];
    }

    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = min(100, intval($_GET['per_page'] ?? ITEMS_PER_PAGE));

    // Get PRs
    $pr = new ProcurementRequest();
    $result = $pr->getAll(array_filter($filters), $page, $perPage);

    $response['success'] = true;
    $response['data'] = $result['data'];
    $response['pagination'] = [
        'total' => $result['total'],
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total_pages' => $result['total_pages']
    ];
    $response['message'] = 'Purchase requests retrieved successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
