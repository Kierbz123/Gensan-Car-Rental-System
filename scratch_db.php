<?php
require_once 'config/config.php';
$db = Database::getInstance();
$db->execute("UPDATE maintenance_logs ml JOIN maintenance_schedules ms ON ml.schedule_id = ms.schedule_id SET ml.status = 'completed', ml.completion_date = ms.last_service_date WHERE ms.status = 'completed' AND ml.status = 'in_progress'");
echo "Cleaned up stuck maintenance logs.\n";
