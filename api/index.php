<?php
header("Content-Type: application/json");
echo json_encode([
    'system' => 'Gensan Car Rental Services API',
    'version' => '1.0.0',
    'status' => 'online',
    'documentation' => '/docs'
]);
