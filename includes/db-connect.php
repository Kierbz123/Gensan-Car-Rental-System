<?php
// includes/db-connect.php
require_once dirname(__DIR__) . '/config/config.php';

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
