<?php
require 'config/config.php';
$db = Database::getInstance();
try {
    $db->execute("ALTER TABLE maintenance_schedules MODIFY COLUMN status ENUM('active','paused','completed','overdue','scheduled','in_progress') DEFAULT 'scheduled';");
    echo "Successfully altered maintenance_schedules status enum.\n";
    $db->execute("UPDATE maintenance_schedules SET status = 'in_progress' WHERE status = '';");
    echo "Fixed empty statuses.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
