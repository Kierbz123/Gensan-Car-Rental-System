<?php
require_once __DIR__ . '/config/config.php';
try {
    $db = Database::getInstance();
    $sql = file_get_contents(__DIR__ . '/database/migrations/005_add_backups_table.sql');
    $db->execute($sql);
    echo "Migration applied successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
