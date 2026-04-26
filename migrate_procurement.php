<?php
/**
 * One-time migration: adds missing approval/rejection columns to procurement_requests.
 * Run once via browser: http://localhost/IATPS/gensan-car-rental-system/migrate_procurement.php
 * Delete this file after running.
 */
require_once 'config/config.php';

$pdo = Database::getInstance()->getConnection();

$migrations = [
    "approved_at"         => "ALTER TABLE procurement_requests ADD COLUMN approved_at DATETIME NULL DEFAULT NULL",
    "approved_at_level1"  => "ALTER TABLE procurement_requests ADD COLUMN approved_at_level1 DATETIME NULL DEFAULT NULL",
    "approved_at_level2"  => "ALTER TABLE procurement_requests ADD COLUMN approved_at_level2 DATETIME NULL DEFAULT NULL",
    "approved_at_level3"  => "ALTER TABLE procurement_requests ADD COLUMN approved_at_level3 DATETIME NULL DEFAULT NULL",
    "approved_by_level1"  => "ALTER TABLE procurement_requests ADD COLUMN approved_by_level1 INT NULL DEFAULT NULL",
    "approved_by_level2"  => "ALTER TABLE procurement_requests ADD COLUMN approved_by_level2 INT NULL DEFAULT NULL",
    "approved_by_level3"  => "ALTER TABLE procurement_requests ADD COLUMN approved_by_level3 INT NULL DEFAULT NULL",
    "rejected_by"         => "ALTER TABLE procurement_requests ADD COLUMN rejected_by INT NULL DEFAULT NULL",
    "rejected_at"         => "ALTER TABLE procurement_requests ADD COLUMN rejected_at DATETIME NULL DEFAULT NULL",
    "rejection_reason"    => "ALTER TABLE procurement_requests ADD COLUMN rejection_reason TEXT NULL DEFAULT NULL",
];

// Fetch existing columns
$existingCols = [];
$stmt = $pdo->query("SHOW COLUMNS FROM procurement_requests");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $existingCols[] = $col['Field'];
}

echo "<h2 style='font-family:monospace'>Procurement Migration</h2><pre style='font-family:monospace'>";
$added = 0;
$skipped = 0;

foreach ($migrations as $colName => $sql) {
    if (in_array($colName, $existingCols)) {
        echo "⏭  SKIP   {$colName} (already exists)\n";
        $skipped++;
        continue;
    }
    try {
        $pdo->exec($sql);
        echo "✅ ADDED  {$colName}\n";
        $added++;
    } catch (Exception $e) {
        echo "❌ ERROR  {$colName}: " . $e->getMessage() . "\n";
    }
}

echo "\n--- Done: {$added} added, {$skipped} skipped ---\n";
echo "\nFinal columns in procurement_requests:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM procurement_requests");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}
echo "</pre>";
