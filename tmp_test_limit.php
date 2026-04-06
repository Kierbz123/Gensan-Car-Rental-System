<?php
require_once 'config/config.php';
$db = Database::getInstance();
try {
    $res = $db->fetchAll("SELECT 1 LIMIT ? OFFSET ?", [10, 0]);
    echo "SUCCESS: " . count($res);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
