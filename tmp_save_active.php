<?php
require_once 'config/config.php';
$all = Database::getInstance()->fetchAll("SELECT record_id, vehicle_id, compliance_type, expiry_date, status FROM compliance_records WHERE status NOT IN ('renewed', 'cancelled')");
file_put_contents('tmp_active_list.txt', var_export($all, true));
echo "DONE";
?>
