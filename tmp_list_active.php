<?php
require_once 'config/config.php';
$db = Database::getInstance();
$all = $db->fetchAll("SELECT record_id, vehicle_id, compliance_type, expiry_date, status FROM compliance_records WHERE status NOT IN ('renewed', 'cancelled')");
echo json_encode($all, JSON_PRETTY_PRINT);
?>
