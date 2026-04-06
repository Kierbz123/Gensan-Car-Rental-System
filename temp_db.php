<?php
require 'classes/Database.php';
$db = Database::getInstance();
$result = $db->fetchAll("DESCRIBE users");
print_r($result);
