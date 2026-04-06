<?php
/**
 * API Endpoint: List Customers
 * Method: GET
 * Query Params: customer_type, credit_rating, is_blacklisted, search, page, per_page
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

    if (!hasPermission($user, 'customers.view')) {
        throw new Exception('Forbidden', 403);
    }

    // Build filters
    $where = ["c.deleted_at IS NULL"];
    $params = [];

    if (!empty($_GET['customer_type'])) {
        $where[] = "c.customer_type = ?";
        $params[] = $_GET['customer_type'];
    }

    if (!empty($_GET['credit_rating'])) {
        $where[] = "c.credit_rating = ?";
        $params[] = $_GET['credit_rating'];
    }

    if (isset($_GET['is_blacklisted'])) {
        $where[] = "c.is_blacklisted = ?";
        $params[] = filter_var($_GET['is_blacklisted'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    if (!empty($_GET['search'])) {
        $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone_primary LIKE ? OR c.email LIKE ? OR c.customer_code LIKE ?)";
        $search = "%{$_GET['search']}%";
        $params = array_merge($params, [$search, $search, $search, $search, $search]);
    }

    $whereClause = implode(' AND ', $where);

    $db = Database::getInstance();

    // Get count
    $count = $db->fetchColumn(
        "SELECT COUNT(*) FROM customers c WHERE {$whereClause}",
        $params
    );

    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = min(100, intval($_GET['per_page'] ?? ITEMS_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    // Get customers
    $customers = $db->fetchAll(
        "SELECT c.customer_id, c.customer_code, c.customer_type, 
                c.first_name, c.last_name, c.phone_primary, c.email,
                c.credit_rating, c.is_blacklisted, c.total_rentals, c.total_spent,
                c.last_rental_date, c.created_at
         FROM customers c
         WHERE {$whereClause}
         ORDER BY c.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    $response['success'] = true;
    $response['data'] = $customers;
    $response['pagination'] = [
        'total' => $count,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($count / $perPage)
    ];
    $response['message'] = 'Customers retrieved successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
