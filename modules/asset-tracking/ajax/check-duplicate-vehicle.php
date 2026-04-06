<?php
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

// Only logged in users can access this
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

$field = $_GET['field'] ?? '';
$value = $_GET['value'] ?? '';
$exclude_id = $_GET['exclude_id'] ?? '';

if (empty($field) || empty($value)) {
    echo json_encode(['exists' => false]);
    exit;
}

$allowedFields = ['engine_number', 'chassis_number', 'plate_number'];

if (!in_array($field, $allowedFields)) {
    echo json_encode(['error' => 'Invalid field']);
    exit;
}

$params = [$value];
$sql = "SELECT COUNT(*) as count FROM vehicles WHERE {$field} = ?";

// For plate_number, we also need to account for deleted_at
if ($field === 'plate_number') {
    $sql .= " AND deleted_at IS NULL";
}

if (!empty($exclude_id)) {
    $sql .= " AND vehicle_id != ?";
    $params[] = $exclude_id;
}

try {
    $result = $db->fetchOne($sql, $params);
    $exists = $result && $result['count'] > 0;
    
    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
