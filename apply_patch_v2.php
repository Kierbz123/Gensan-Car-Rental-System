<?php
require_once 'config/config.php';

$db = Database::getInstance();
$patchFile = 'database/patch_procurement_demo_v2.sql';

if (!file_exists($patchFile)) {
    die("Error: $patchFile not found.\n");
}

echo "Applying Procurement Demo Patch V2...\n";

$sql = file_get_contents($patchFile);
// PDO doesn't like multi-queries. Split by semicolon.
$queries = explode(';', $sql);

$success = 0;
$fail = 0;

foreach ($queries as $q) {
    $q = trim($q);
    if (empty($q) || strpos($q, '--') === 0) continue;
    
    try {
        $db->execute($q);
        $success++;
    } catch (Exception $e) {
        echo "Error in query: " . $q . "\n";
        echo "Reason: " . $e->getMessage() . "\n\n";
        $fail++;
    }
}

echo "\nDone. Success: $success, Failed: $fail\n";
if ($fail === 0) {
    echo "The procurement table should now be populated.\n";
}
?>
