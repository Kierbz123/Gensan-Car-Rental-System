<?php
/**
 * API Endpoint: Approve or Reject Purchase Request
 * Method: POST
 * Request: {action: 'approve'|'reject', notes?, reason?}
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

    if (!hasPermission($user, 'procurement.approve')) {
        throw new Exception('Forbidden', 403);
    }

    // Get PR ID from URL
    $prId = $_GET['id'] ?? null;

    if (empty($prId)) {
        throw new Exception('PR ID is required', 400);
    }

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception("Action must be 'approve' or 'reject'", 400);
    }

    // Process approval/rejection
    $pr = new ProcurementRequest();

    if ($action === 'approve') {
        $pr->processApproval($prId, $user['user_id'], 'approve', $input['notes'] ?? null);
        $response['message'] = 'Purchase request approved successfully';
    } else {
        if (empty($input['reason'])) {
            throw new Exception('Reason is required for rejection', 400);
        }
        $pr->processApproval($prId, $user['user_id'], 'reject', $input['reason']);
        $response['message'] = 'Purchase request rejected';
    }

    $response['success'] = true;
    http_response_code(200);

} catch (Exception $e) {
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
