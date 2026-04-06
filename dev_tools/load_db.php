<?php
$host = 'localhost';
$user = 'gcr_user';
$pass = 'StrongPass123!';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$conn->query("DROP DATABASE IF EXISTS gensan_car_rental_db");
$conn->query("CREATE DATABASE gensan_car_rental_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db('gensan_car_rental_db');

$files = ['database/schema.sql', 'database/demo_accounts.sql'];
foreach ($files as $file) {
    echo "Importing $file... ";
    $conn->multi_query(file_get_contents($file));
    while ($conn->next_result()) {
        ;
    }
    echo "Done.\n";
}

$conn->query("SET FOREIGN_KEY_CHECKS=0;");
echo "Importing database/demo_data.sql... ";
$conn->multi_query(file_get_contents('database/demo_data.sql'));
while ($conn->next_result()) {
    ;
}
echo "Done.\n";

$conn->query("SET FOREIGN_KEY_CHECKS=1;");
$conn->close();
echo "Rebuilt database successfully.\n";
