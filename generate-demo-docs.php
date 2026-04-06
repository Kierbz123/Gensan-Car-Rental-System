<?php
require_once 'config/config.php';
require_once 'classes/DocumentManager.php';

$db = Database::getInstance();
$vehicles = $db->fetchAll("SELECT vehicle_id, plate_number, brand, model FROM vehicles LIMIT 5");

if (empty($vehicles)) {
    echo "No vehicles found in database to attach documents to.\n";
    exit;
}

$storageDir = rtrim(BASE_PATH, '/') . '/' . DocumentManager::STORAGE_DIR;
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Map real-looking demo files
$regSource = 'C:\Users\User\.gemini\antigravity\brain\93c87e91-5cbc-412c-88cd-0c292a809dba\mock_lto_registration_1774686370011.png';
$insSource = 'C:\Users\User\.gemini\antigravity\brain\93c87e91-5cbc-412c-88cd-0c292a809dba\mock_insurance_policy_1774686397262.png';

// Clear existing demo documents to avoid clutter
$db->execute("DELETE FROM documents WHERE title LIKE 'LTO Registration - %' OR title LIKE 'Insurance Policy - %'");

$counter = 0;
foreach ($vehicles as $vehicle) {
    echo "Attaching real-looking docs to Vehicle: {$vehicle['brand']} {$vehicle['model']} ({$vehicle['plate_number']})\n";
    
    // 1. Registration
    $regFile = 'reg_' . $vehicle['vehicle_id'] . '_' . uniqid() . '.png';
    copy($regSource, $storageDir . $regFile);
    $db->execute(
        "INSERT INTO documents (entity_type, entity_id, document_category, title, file_path, file_type, file_size, uploaded_by, expires_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        ['vehicle', $vehicle['vehicle_id'], 'registration', "LTO Registration - {$vehicle['plate_number']}", DocumentManager::STORAGE_DIR . $regFile, 'image/png', filesize($storageDir . $regFile), 1, date('Y-m-d', strtotime('+1 year'))]
    );

    // 2. Insurance
    $insFile = 'ins_' . $vehicle['vehicle_id'] . '_' . uniqid() . '.png';
    copy($insSource, $storageDir . $insFile);
    $db->execute(
        "INSERT INTO documents (entity_type, entity_id, document_category, title, file_path, file_type, file_size, uploaded_by, expires_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        ['vehicle', $vehicle['vehicle_id'], 'insurance', "Insurance Policy - {$vehicle['brand']}", DocumentManager::STORAGE_DIR . $insFile, 'image/png', filesize($storageDir . $insFile), 1, date('Y-m-d', strtotime('+6 months'))]
    );
    
    $counter += 2;
}

echo "Successfully created {$counter} realistic demo vehicle documents.\n";
