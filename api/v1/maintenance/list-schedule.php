<?php
/**
 * API Endpoint: List Maintenance Schedule
 * Method: GET
 * Query Params: vehicle_id, status, overdue, upcoming_days, page, per_page
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

    $db = Database::getInstance();

    // Check for specific filters
    if (isset($_GET['overdue']) && $_GET['overdue'] == '1') {
        // Get overdue maintenance
        $data = $db->fetchAll(
            "SELECT * FROM upcoming_maintenance 
             WHERE urgency = 'overdue'
             ORDER BY next_due_date ASC"
        );

        $response['success'] = true;
        $response['data'] = $data;
        $response['message'] = 'Overdue maintenance retrieved';
        http_response_code(200);
        echo json_encode($response);
        exit;
    }

    if (!empty($_GET['upcoming_days'])) {
        // Get upcoming maintenance
        $days = intval($_GET['upcoming_days']);
        $maintenance = new MaintenanceSchedule();
        $data = $maintenance->getUpcoming($days, $_GET['vehicle_id'] ?? null);

        $response['success'] = true;
        $response['data'] = $data;
        $response['message'] = 'Upcoming maintenance retrieved';
        http_response_code(200);
        echo json_encode($response);
        exit;
    }

    // Standard query
    $where = ["1=1"];
    $params = [];

    if (!empty($_GET['vehicle_id'])) {
        $where[] = "vehicle_id = ?";
        $params[] = $_GET['vehicle_id'];
    }

    if (!empty($_GET['status'])) {
        $where[] = "ms.status = ?";
        $params[] = $_GET['status'];
    }

    $whereClause = implode(' AND ', $where);

    $count = $db->fetchColumn(
        "SELECT COUNT(*) FROM maintenance_schedules ms WHERE {$whereClause}",
        $params
    );

    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = min(100, intval($_GET['per_page'] ?? ITEMS_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    $schedules = $db->fetchAll(
        "SELECT ms.*, v.plate_number, v.brand, v.model, vc.category_name
         FROM maintenance_schedules ms
         JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
         JOIN vehicle_categories vc ON v.category_id = vc.category_id
         WHERE {$whereClause}
         ORDER BY ms.next_due_date ASC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    $response['success'] = true;
    $response['data'] = $schedules;
    $response['pagination'] = [
        'total' => $count,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($count / $perPage)
    ];
    $response['message'] = 'Maintenance schedules retrieved successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
