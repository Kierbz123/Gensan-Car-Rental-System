<?php
/**
 * GET /modules/ajax/get-notifications.php
 * Returns categorized, aggregated system notifications.
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!$authUser->hasPermission('dashboard.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db   = Database::getInstance();
    $items = [];
    $badgeCount = 0;

    /* =====================================================================
     * CATEGORY: RENTALS
     * ===================================================================== */
    // Overdue rentals
    $overdueRentals = $db->fetchAll(
        "SELECT ra.agreement_id, ra.agreement_number, ra.rental_end_date,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                v.plate_number,
                DATEDIFF(CURDATE(), DATE(ra.rental_end_date)) AS days_late
         FROM rental_agreements ra
         JOIN customers c  ON ra.customer_id = c.customer_id
         JOIN vehicles  v  ON ra.vehicle_id  = v.vehicle_id
         WHERE ra.status = 'active' AND DATE(ra.rental_end_date) < CURDATE()
         ORDER BY ra.rental_end_date ASC"
    );
    foreach ($overdueRentals as $r) {
        $items[] = [
            'id' => 'rental-late-' . $r['agreement_id'],
            'category' => 'rentals',
            'severity' => 'danger',
            'icon' => 'alert-circle',
            'title' => 'Rental Overdue',
            'body' => "{$r['agreement_number']} ({$r['plate_number']}) is {$r['days_late']} day(s) late.",
            'href' => BASE_URL . "modules/rentals/view.php?id={$r['agreement_id']}",
            'time' => $r['rental_end_date'],
        ];
        $badgeCount++;
    }

    // Active/Confirmed rentals (to match sidebar "1")
    $activeRentals = $db->fetchAll(
        "SELECT ra.agreement_id, ra.agreement_number, ra.status,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                v.plate_number
         FROM rental_agreements ra
         JOIN customers c ON ra.customer_id = c.customer_id
         JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
         WHERE ra.status IN ('confirmed', 'active') AND DATE(ra.rental_end_date) >= CURDATE()
         LIMIT 10"
    );
    foreach ($activeRentals as $r) {
        $items[] = [
            'id' => 'rental-active-' . $r['agreement_id'],
            'category' => 'rentals',
            'severity' => 'info',
            'icon' => 'car',
            'title' => 'Ongoing Rental',
            'body' => "{$r['agreement_number']} is currently " . strtoupper($r['status']) . ".",
            'href' => BASE_URL . "modules/rentals/view.php?id={$r['agreement_id']}",
            'time' => date('Y-m-d H:i:s'),
        ];
        // Only increment badge if it's 'confirmed' (needs dispatch) or if user wants all
        $badgeCount++; 
    }

    /* =====================================================================
     * CATEGORY: MAINTENANCE
     * ===================================================================== */
    // Individual Maintenance items (to show all 13)
    $allMaint = $db->fetchAll(
        "SELECT ms.schedule_id, v.plate_number, ms.service_type, ms.status, ms.next_due_date
         FROM maintenance_schedules ms
         JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
         WHERE ms.status != 'completed'
         ORDER BY ms.next_due_date ASC"
    );
    foreach ($allMaint as $m) {
        $severity = $m['status'] === 'overdue' ? 'danger' : 'warning';
        $type = str_replace('_', ' ', ucfirst($m['service_type']));
        $items[] = [
            'id' => 'maint-item-' . $m['schedule_id'],
            'category' => 'maintenance',
            'severity' => $severity,
            'icon' => 'wrench',
            'title' => $severity === 'danger' ? 'Overdue Maintenance' : 'Scheduled Maintenance',
            'body' => "{$m['plate_number']} needs {$type} (" . ucfirst($m['status']) . ").",
            'href' => BASE_URL . "modules/maintenance/index.php",
            'time' => $m['next_due_date'],
        ];
        $badgeCount++;
    }

    /* =====================================================================
     * CATEGORY: PROCUREMENT
     * ===================================================================== */
    // Pending Approval
    $pendingPrs = $db->fetchAll("SELECT pr_id, pr_number FROM procurement_requests WHERE status = 'pending_approval'");
    foreach ($pendingPrs as $pr) {
        $items[] = [
            'id' => 'pr-appr-' . $pr['pr_id'],
            'category' => 'procurement',
            'severity' => 'warning',
            'icon' => 'file-text',
            'title' => 'PR Pending Approval',
            'body' => "Request {$pr['pr_number']} is awaiting authorization.",
            'href' => BASE_URL . "modules/procurement/pr-view.php?id={$pr['pr_id']}",
            'time' => date('Y-m-d H:i:s'),
        ];
        $badgeCount++;
    }

    // Awaiting PO Generation (8)
    $awaitingPo = $db->fetchAll("SELECT pr_id, pr_number FROM procurement_requests WHERE status = 'approved' AND po_number IS NULL");
    foreach ($awaitingPo as $pr) {
        $items[] = [
            'id' => 'pr-po-' . $pr['pr_id'],
            'category' => 'procurement',
            'severity' => 'info',
            'icon' => 'shopping-cart',
            'title' => 'Awaiting PO Generation',
            'body' => "Approved request {$pr['pr_number']} needs a Purchase Order generated.",
            'href' => BASE_URL . "modules/procurement/po-generate.php?pr_id={$pr['pr_id']}",
            'time' => date('Y-m-d H:i:s'),
        ];
        $badgeCount++;
    }

    /* =====================================================================
     * CATEGORY: INVENTORY
     * ===================================================================== */
    // Pending Storage (6)
    $pendingStorage = $db->fetchAll(
        "SELECT pi.item_id, pi.item_description, pr.pr_number 
         FROM procurement_items pi 
         JOIN procurement_requests pr ON pi.pr_id = pr.pr_id
         WHERE pi.inventory_status = 'pending'"
    );
    foreach ($pendingStorage as $ps) {
        $items[] = [
            'id' => 'inv-store-' . $ps['item_id'],
            'category' => 'inventory',
            'severity' => 'warning',
            'icon' => 'inbox',
            'title' => 'Pending Storage',
            'body' => "{$ps['item_description']} (from {$ps['pr_number']}) is waiting to be stored.",
            'href' => BASE_URL . "modules/inventory/index.php",
            'time' => date('Y-m-d H:i:s'),
        ];
        $badgeCount++;
    }

    // Low Stock
    $lowStock = $db->fetchAll("SELECT inventory_id, item_name, quantity_on_hand FROM parts_inventory WHERE reorder_level > 0 AND quantity_on_hand <= reorder_level");
    foreach ($lowStock as $ls) {
        $items[] = [
            'id' => 'inv-low-' . $ls['inventory_id'],
            'category' => 'inventory',
            'severity' => 'danger',
            'icon' => 'package',
            'title' => 'Low Stock Alert',
            'body' => "{$ls['item_name']} is low on stock ({$ls['quantity_on_hand']} left).",
            'href' => BASE_URL . "modules/inventory/index.php?low_stock=1",
            'time' => date('Y-m-d H:i:s'),
        ];
        $badgeCount++;
    }

    /* =====================================================================
     * CATEGORY: COMPLIANCE
     * ===================================================================== */
    $compRecords = $db->fetchAll("
        SELECT c.record_id, v.plate_number, c.compliance_type, c.expiry_date
        FROM compliance_records c 
        JOIN vehicles v ON c.vehicle_id = v.vehicle_id
        WHERE c.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) 
          AND c.expiry_date != '0000-00-00'
          AND c.status NOT IN ('renewed', 'cancelled')
          AND v.deleted_at IS NULL
          AND c.record_id = (SELECT MAX(record_id) FROM compliance_records c2 WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type)
    ");
    foreach ($compRecords as $cr) {
        $items[] = [
            'id' => 'comp-' . $cr['record_id'],
            'category' => 'compliance',
            'severity' => 'danger',
            'icon' => 'shield-alert',
            'title' => 'Compliance Alert',
            'body' => strtoupper(str_replace('_', ' ', $cr['compliance_type'])) . " for {$cr['plate_number']} is expiring soon/expired.",
            'href' => BASE_URL . "modules/compliance/index.php",
            'time' => $cr['expiry_date'],
        ];
        $badgeCount++;
    }

    /* =====================================================================
     * CATEGORY: DATABASE NOTIFICATIONS
     * ===================================================================== */
    $dbNotifs = $db->fetchAll("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10", [$authUser->getId()]);
    foreach ($dbNotifs as $n) {
        $items[] = [
            'id' => 'db-' . $n['notification_id'],
            'category' => $n['related_module'] ?: 'all',
            'severity' => 'info',
            'icon' => 'bell',
            'title' => $n['title'],
            'body' => $n['message'],
            'href' => $n['related_url'] ? BASE_URL . $n['related_url'] : '#',
            'time' => $n['created_at'],
        ];
        $badgeCount++;
    }

    // Sort by severity (danger > warning > info), then by time DESC
    $sevOrder = ['danger' => 0, 'warning' => 1, 'info' => 2];
    usort($items, function ($a, $b) use ($sevOrder) {
        if ($sevOrder[$a['severity']] !== $sevOrder[$b['severity']]) {
            return $sevOrder[$a['severity']] <=> $sevOrder[$b['severity']];
        }
        return strtotime($b['time']) <=> strtotime($a['time']);
    });

    echo json_encode([
        'success'        => true,
        'critical_count' => $badgeCount,
        'total'          => count($items),
        'notifications'  => $items,
        'generated_at'   => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
