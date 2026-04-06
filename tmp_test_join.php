<?php
require_once 'config/config.php';
$db = Database::getInstance();
$v = $db->fetchOne("SELECT vehicle_id FROM vehicles WHERE vehicle_id = 'GCR-SU-0001'");
if ($v) {
    echo "GCR-SU-0001 EXISTS IN vehicles\n";
} else {
    echo "GCR-SU-0001 MISSING FROM vehicles\n";
    $first = $db->fetchOne("SELECT vehicle_id FROM vehicles LIMIT 1");
    echo "First Vehicle ID in Table: " . ($first ? $first['vehicle_id'] : 'NONE') . "\n";
}
?>
