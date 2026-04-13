<?php
/**
 * Diagnostic + Fix for bad compliance stub rows.
 * Run: http://localhost/IATPS/gensan-car-rental-system/fix-pending-stubs.php
 */
require_once __DIR__ . '/config/config.php';

$db  = Database::getInstance();
$pdo = $db->getConnection();

echo "<pre>";

// ── Step 1: Show what's actually in the table ──────────────────────────────
$rows = $pdo->query(
    "SELECT record_id, vehicle_id, compliance_type, status,
            expiry_date, QUOTE(expiry_date) AS expiry_raw, created_at
     FROM compliance_records
     WHERE vehicle_id = 'GCR-HB-0006'
     ORDER BY record_id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

echo "=== ROWS for GCR-HB-0006 ===\n";
foreach ($rows as $r) {
    printf(
        "record_id=%-5s  type=%-28s  status=%-18s  expiry_date=%s (raw: %s)\n",
        $r['record_id'], $r['compliance_type'], $r['status'],
        $r['expiry_date'], $r['expiry_raw']
    );
}

// ── Step 2: Show status column definition ─────────────────────────────────
$col = $pdo->query(
    "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'compliance_records'
       AND COLUMN_NAME  = 'status'"
)->fetch(PDO::FETCH_ASSOC);
echo "\n=== status COLUMN DEFINITION ===\n" . print_r($col, true);

// ── Step 3: Show expiry_date column definition ────────────────────────────
$col2 = $pdo->query(
    "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'compliance_records'
       AND COLUMN_NAME  = 'expiry_date'"
)->fetch(PDO::FETCH_ASSOC);
echo "=== expiry_date COLUMN DEFINITION ===\n" . print_r($col2, true);

// ── Step 4: Fix ALL stub rows with bad/zero expiry_date ───────────────────
// We fix regardless of status value, targeting the zero-date sentinel
$stmt = $pdo->prepare(
    "UPDATE compliance_records
     SET expiry_date = NULL
     WHERE vehicle_id = 'GCR-HB-0006'
       AND (expiry_date = '0000-00-00' OR expiry_date = '0000-00-00 00:00:00')"
);
$stmt->execute();
$fixed = $stmt->rowCount();
echo "\n=== FIX RESULT ===\nRows updated to NULL expiry_date: $fixed\n";

// Also fix system-wide (all vehicles, any status, zero-date)
$stmt2 = $pdo->prepare(
    "UPDATE compliance_records
     SET expiry_date = NULL
     WHERE expiry_date = '0000-00-00' OR expiry_date = '0000-00-00 00:00:00'"
);
$stmt2->execute();
$fixedAll = $stmt2->rowCount();
echo "System-wide rows fixed: $fixedAll\n";

echo "\nDone. You can now delete this file.\n</pre>";
