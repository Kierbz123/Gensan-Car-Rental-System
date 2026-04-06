require_once 'config/config.php';
// The Database class is loaded in config/config.php (which requires config/database.php)

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die("CRITICAL ERROR: Failed to obtain database instance. " . $e->getMessage() . "\n");
}

echo "Starting Demo Data Setup...\n";

// --- 1. Execute Procurement SQL ---
echo "Seeding Procurement Demo Data...\n";
$sqlFile = 'database/seed_procurement_demo.sql';
if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    // PDO doesn't support multiple queries in one execute unless specifically told
    // We'll split by semicolon for basic seeding
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $db->execute($query);
            } catch (Exception $e) {
                echo "Warning: Error executing procurement query: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "Done.\n";
} else {
    echo "Error: seed_procurement_demo.sql not found.\n";
}

// --- 2. Link Vehicle Documents ---
echo "Linking Vehicle Documents...\n";

$storageDir = rtrim(BASE_PATH, '/') . '/' . DocumentManager::STORAGE_DIR;
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Map of generated images from the brain folder to the project's storage
$assetMap = [
    'vios_lto' => 'C:/Users/User/.gemini/antigravity/brain/49262181-2c14-4311-9ae5-f9ff68058bd1/vios_lto_registration_1774687771543.png',
    'fortuner_lto' => 'C:/Users/User/.gemini/antigravity/brain/49262181-2c14-4311-9ae5-f9ff68058bd1/fortuner_lto_registration_1774687816402.png',
    'vios_comp' => 'C:/Users/User/.gemini/antigravity/brain/49262181-2c14-4311-9ae5-f9ff68058bd1/vios_comprehensive_insurance_2_1774688063287.png'
];

$vios_id = 'GCR-SD-0001';
$fortuner_id = 'GCR-SU-0001';

// Function to safely copy and link
function linkDoc($db, $source, $destDir, $entityType, $entityId, $category, $title, $isPlaceholder = false) {
    if (!file_exists($source)) {
        echo "Error: Source file $source not found.\n";
        return;
    }

    $extension = pathinfo($source, PATHINFO_EXTENSION);
    $safeFilename = uniqid('demo_') . '_' . $entityId . '_' . $category . '.' . $extension;
    $fullDestPath = $destDir . $safeFilename;
    $dbPath = DocumentManager::STORAGE_DIR . $safeFilename;

    if (copy($source, $fullDestPath)) {
        $db->execute(
            "INSERT INTO documents (entity_type, entity_id, document_category, title, file_path, file_type, file_size, uploaded_by, expires_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $entityType,
                $entityId,
                $category,
                $title . ($isPlaceholder ? ' [Demo Placeholder]' : ''),
                $dbPath,
                'image/png',
                filesize($fullDestPath),
                1,
                date('Y-m-d', strtotime('+1 year'))
            ]
        );
        echo "Linked $title to $entityId\n";
    } else {
        echo "Error: Failed to copy $source to $fullDestPath\n";
    }
}

// 1. Vios LTO
linkDoc($db, $assetMap['vios_lto'], $storageDir, 'vehicle', $vios_id, 'registration', 'LTO Registration (OR/CR)');

// 2. Fortuner LTO
linkDoc($db, $assetMap['fortuner_lto'], $storageDir, 'vehicle', $fortuner_id, 'registration', 'LTO Registration (OR/CR)');

// 3. Vios Comprehensive Insurance
linkDoc($db, $assetMap['vios_comp'], $storageDir, 'vehicle', $vios_id, 'insurance', 'Comprehensive Insurance Policy');

// 4. Placeholders for the rest (using LTO image as base)
$placeholders = [
    'insurance_tpl' => 'TPL Insurance Certificate',
    'emission_test' => 'Emission Compliance Certificate',
    'franchise_ltfrb' => 'LTFRB Franchise Clearance',
    'pnp_clearance' => 'PNP Highway Patrol Group Clearance'
];

foreach ($placeholders as $cat => $title) {
    linkDoc($db, $assetMap['vios_lto'], $storageDir, 'vehicle', $vios_id, $cat, $title, true);
    linkDoc($db, $assetMap['fortuner_lto'], $storageDir, 'vehicle', $fortuner_id, $cat, $title, true);
}

// Add Fortuner Comprehensive Insurance Placeholder
linkDoc($db, $assetMap['fortuner_lto'], $storageDir, 'vehicle', $fortuner_id, 'insurance', 'Comprehensive Insurance Policy', true);

echo "\nDemo Data Setup Completed Successfully.\n";
