<?php
/**
 * API Endpoint: List Suppliers
 * Method: GET
 * Query Params: category, is_active, search, page, per_page
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

    // Build query
    $where = ["deleted_at IS NULL"];
    $params = [];

    if (!empty($_GET['category'])) {
        $where[] = "category = ?";
        $params[] = $_GET['category'];
    }

    if (isset($_GET['is_active'])) {
        $where[] = "is_active = ?";
        $params[] = filter_var($_GET['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    if (!empty($_GET['search'])) {
        $where[] = "(company_name LIKE ? OR contact_person LIKE ? OR phone_primary LIKE ?)";
        $search = "%{$_GET['search']}%";
        $params = array_merge($params, [$search, $search, $search]);
    }

    $whereClause = implode(' AND ', $where);

    $db = Database::getInstance();

    // Get count
    $count = $db->fetchColumn("SELECT COUNT(*) FROM suppliers WHERE {$whereClause}", $params);

    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = min(100, intval($_GET['per_page'] ?? ITEMS_PER_PAGE));
    $offset = ($page - 1) * $perPage;

    // Get suppliers
    $suppliers = $db->fetchAll(
        "SELECT supplier_id, supplier_code, company_name, category, contact_person,
                phone_primary, email, city, is_active, performance_rating
         FROM suppliers
         WHERE {$whereClause}
         ORDER BY company_name ASC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    $response['success'] = true;
    $response['data'] = $suppliers;
    $response['pagination'] = [
        'total' => $count,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($count / $perPage)
    ];
    $response['message'] = 'Suppliers retrieved successfully';
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
