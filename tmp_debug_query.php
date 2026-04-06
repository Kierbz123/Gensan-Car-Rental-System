<?php
require_once 'config/config.php';
$db = Database::getInstance();

$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$viewMode = $_GET['view_mode'] ?? 'critical';
$sort = $_GET['sort'] ?? 'urgency';
$page = 1; $perPage = 25; $offset = 0;

$where = ["c.status NOT IN ('renewed', 'cancelled')"];
$params = [];

if ($viewMode === 'critical') {
    $where[] = "c.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)";
}

if (!empty($type)) {
    $where[] = "c.compliance_type = ?";
    $params[] = $type;
}

if (!empty($search)) {
    $where[] = "(v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR c.document_number LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$where[] = "c.record_id = (
    SELECT MAX(record_id) 
    FROM compliance_records c2 
    WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
)";

$whereClause = implode(' AND ', $where);

$orderClause = "ORDER BY CASE WHEN c.expiry_date < CURRENT_DATE() THEN 1 ELSE 2 END ASC, c.expiry_date ASC";

$sql = "SELECT c.*, v.plate_number, v.brand, v.model
        FROM compliance_records c
        JOIN vehicles v ON c.vehicle_id = v.vehicle_id
        WHERE $whereClause
        $orderClause
        LIMIT ? OFFSET ?";

$items = $db->fetchAll($sql, array_merge($params, [$perPage, $offset]));

echo "SQL: " . $sql . "\n";
echo "PARAMS: " . json_encode(array_merge($params, [$perPage, $offset])) . "\n";
echo "COUNT: " . count($items) . "\n";
foreach ($items as $i) {
    echo "- " . $i['record_id'] . ": " . $i['expiry_date'] . "\n";
}
?>
