<?php
require_once 'config/config.php';
require_once 'config/database.php';
$db = Database::getInstance();
$tables = ['compliance_records', 'documents'];
foreach ($tables as $table) {
    echo "\n--- Schema for $table ---\n";
    $cols = $db->fetchAll("DESCRIBE $table");
    foreach ($cols as $col) {
        printf("%-20s %-20s %-10s %-5s\n", $col['Field'], $col['Type'], $col['Null'], $col['Key']);
    }
}
