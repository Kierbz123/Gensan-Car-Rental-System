<?php
require_once 'config/config.php';
$db = Database::getInstance();

// 1. Check stats
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_records,
        COALESCE(SUM(CASE WHEN expiry_date < CURRENT_DATE() THEN 1 ELSE 0 END), 0) as expired
    FROM compliance_records c
    WHERE status != 'renewed' AND status != 'cancelled'
      AND record_id = (
          SELECT MAX(record_id)
          FROM compliance_records c2
          WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
      )
");
echo "Stats Expired: " . $stats['expired'] . "\n";

// 2. Check for records without matching vehicles
$orphaned = $db->fetchAll("
    SELECT c.record_id, c.vehicle_id, c.document_number
    FROM compliance_records c
    LEFT JOIN vehicles v ON c.vehicle_id = v.vehicle_id
    WHERE v.vehicle_id IS NULL
");
echo "Orphaned Records: " . count($orphaned) . "\n";
foreach ($orphaned as $o) {
    echo "ID: " . $o['record_id'] . " | Vehicle ID: " . $o['vehicle_id'] . " | Doc: " . $o['document_number'] . "\n";
}

// 3. Check for specific case: Expired but missing from JOINed view
$itemsCount = $db->fetchColumn("
    SELECT COUNT(*)
    FROM compliance_records c
    JOIN vehicles v ON c.vehicle_id = v.vehicle_id
    WHERE c.status NOT IN ('renewed', 'cancelled')
      AND c.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
      AND c.record_id = (
          SELECT MAX(record_id) 
          FROM compliance_records c2 
          WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
      )
");
echo "Dashboard Items Count: " . $itemsCount . "\n";
?>
