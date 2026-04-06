<?php
/**
 * GET /modules/ajax/get-notifications.php
 * Returns live, role-aware, priority-sorted system notifications as JSON.
 * Covers: maintenance, compliance, rentals, procurement, inventory.
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
    $role = $_SESSION['role'] ?? 'viewer';

    $items = [];

    /* ─────────────────────────────────────────────────────────────
     * PRIORITY 0 — CRITICAL / DANGER
     * ───────────────────────────────────────────────────────────── */

    /* 0a. Overdue rentals (active but past return date) */
    $overdueRentals = $db->fetchAll(
        "SELECT ra.agreement_id, ra.agreement_number, ra.rental_end_date,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                v.plate_number, v.brand, v.model,
                DATEDIFF(CURDATE(), DATE(ra.rental_end_date)) AS days_late
         FROM rental_agreements ra
         JOIN customers c  ON ra.customer_id = c.customer_id
         JOIN vehicles  v  ON ra.vehicle_id  = v.vehicle_id
         WHERE ra.status = 'active'
           AND DATE(ra.rental_end_date) < CURDATE()
         ORDER BY ra.rental_end_date ASC
         LIMIT 10"
    );
    foreach ($overdueRentals as $r) {
        $days = (int) $r['days_late'];
        $items[] = [
            'id'       => 'rental-late-' . $r['agreement_id'],
            'type'     => 'rental',
            'priority' => 0,
            'severity' => 'danger',
            'icon'     => 'car',
            'title'    => 'Rental Overdue',
            'body'     => $r['agreement_number'] . ' — ' . $r['customer_name']
                        . ' · ' . $r['plate_number'] . ' is overdue by ' . $days . ' day(s).',
            'href'     => BASE_URL . 'modules/rentals/view.php?id=' . $r['agreement_id'],
            'time'     => $r['rental_end_date'],
        ];
    }

    /* 0b. Breached compliance (expired instruments) */
    $breached = $db->fetchAll(
        "SELECT cr.record_id, v.plate_number, v.brand, v.model,
                cr.compliance_type, cr.expiry_date,
                ABS(DATEDIFF(CURDATE(), cr.expiry_date)) AS days_lapsed,
                cr.vehicle_id
         FROM compliance_records cr
         JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
         WHERE cr.expiry_date < CURDATE()
           AND cr.status NOT IN ('renewed','cancelled')
           AND cr.record_id = (
               SELECT MAX(r2.record_id)
               FROM compliance_records r2
               WHERE r2.vehicle_id = cr.vehicle_id
                 AND r2.compliance_type = cr.compliance_type
           )
         ORDER BY cr.expiry_date ASC
         LIMIT 10"
    );
    foreach ($breached as $r) {
        $items[] = [
            'id'       => 'comp-breach-' . $r['record_id'],
            'type'     => 'compliance',
            'priority' => 0,
            'severity' => 'danger',
            'icon'     => 'shield-x',
            'title'    => 'Compliance Breached',
            'body'     => strtoupper(str_replace('_', ' ', $r['compliance_type']))
                        . ' for ' . $r['plate_number'] . ' lapsed '
                        . $r['days_lapsed'] . ' day(s) ago.',
            'href'     => BASE_URL . 'modules/compliance/renew-upload.php?vehicle_id='
                        . urlencode($r['vehicle_id']) . '&type=' . urlencode($r['compliance_type']),
            'time'     => $r['expiry_date'],
        ];
    }

    /* 0c. Overdue maintenance schedules */
    $maintOverdue = $db->fetchAll(
        "SELECT ms.schedule_id, v.plate_number, v.brand, v.model,
                ms.service_type, ms.next_due_date,
                DATEDIFF(CURDATE(), ms.next_due_date) AS days_overdue
         FROM maintenance_schedules ms
         JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
         WHERE ms.status = 'overdue'
           AND ms.next_due_date < CURDATE()
         ORDER BY ms.next_due_date ASC
         LIMIT 10"
    );
    foreach ($maintOverdue as $r) {
        $items[] = [
            'id'       => 'maint-overdue-' . $r['schedule_id'],
            'type'     => 'maintenance',
            'priority' => 0,
            'severity' => 'danger',
            'icon'     => 'wrench',
            'title'    => 'Overdue Maintenance',
            'body'     => trim($r['brand'] . ' ' . $r['model']) . ' (' . $r['plate_number'] . ') — '
                        . str_replace('_', ' ', ucfirst($r['service_type']))
                        . ' is ' . $r['days_overdue'] . ' day(s) overdue.',
            'href'     => BASE_URL . 'modules/maintenance/schedule.php',
            'time'     => $r['next_due_date'],
        ];
    }

    /* ─────────────────────────────────────────────────────────────
     * PRIORITY 1 — WARNING
     * ───────────────────────────────────────────────────────────── */

    /* 1a. Rentals due today */
    $dueToday = $db->fetchAll(
        "SELECT ra.agreement_id, ra.agreement_number, ra.rental_end_date,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                v.plate_number, v.brand, v.model
         FROM rental_agreements ra
         JOIN customers c  ON ra.customer_id = c.customer_id
         JOIN vehicles  v  ON ra.vehicle_id  = v.vehicle_id
         WHERE ra.status = 'active'
           AND DATE(ra.rental_end_date) = CURDATE()
         ORDER BY ra.rental_end_date ASC
         LIMIT 5"
    );
    foreach ($dueToday as $r) {
        $items[] = [
            'id'       => 'rental-today-' . $r['agreement_id'],
            'type'     => 'rental',
            'priority' => 1,
            'severity' => 'warning',
            'icon'     => 'car',
            'title'    => 'Rental Due Today',
            'body'     => $r['agreement_number'] . ' — ' . $r['customer_name']
                        . ' · ' . $r['plate_number'] . ' is due for return today.',
            'href'     => BASE_URL . 'modules/rentals/view.php?id=' . $r['agreement_id'],
            'time'     => $r['rental_end_date'],
        ];
    }

    /* 1b. Compliance expiring within 7 days (not yet breached) */
    $expiring7 = $db->fetchAll(
        "SELECT cr.record_id, v.plate_number, v.brand, v.model,
                cr.compliance_type, cr.expiry_date,
                DATEDIFF(cr.expiry_date, CURDATE()) AS days_left,
                cr.vehicle_id
         FROM compliance_records cr
         JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
         WHERE cr.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
           AND cr.status NOT IN ('renewed','cancelled')
           AND cr.record_id = (
               SELECT MAX(r2.record_id)
               FROM compliance_records r2
               WHERE r2.vehicle_id = cr.vehicle_id
                 AND r2.compliance_type = cr.compliance_type
           )
         ORDER BY cr.expiry_date ASC
         LIMIT 10"
    );
    foreach ($expiring7 as $r) {
        $items[] = [
            'id'       => 'comp-warn-' . $r['record_id'],
            'type'     => 'compliance',
            'priority' => 1,
            'severity' => 'warning',
            'icon'     => 'shield-alert',
            'title'    => 'Compliance Expiring Soon',
            'body'     => strtoupper(str_replace('_', ' ', $r['compliance_type']))
                        . ' for ' . $r['plate_number'] . ' expires in ' . $r['days_left'] . ' day(s).',
            'href'     => BASE_URL . 'modules/compliance/renew-upload.php?vehicle_id='
                        . urlencode($r['vehicle_id']) . '&type=' . urlencode($r['compliance_type']),
            'time'     => $r['expiry_date'],
        ];
    }

    /* 1c. Vehicles stuck in maintenance > 3 days */
    $stuckMaint = $db->fetchAll(
        "SELECT ml.log_id, v.plate_number, v.brand, v.model,
                ml.service_type, ml.service_date,
                DATEDIFF(CURDATE(), ml.service_date) AS days_in
         FROM maintenance_logs ml
         JOIN vehicles v ON ml.vehicle_id = v.vehicle_id
         WHERE ml.status = 'in_progress'
           AND ml.service_date <= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
         ORDER BY ml.service_date ASC
         LIMIT 5"
    );
    foreach ($stuckMaint as $r) {
        $items[] = [
            'id'       => 'mlog-stuck-' . $r['log_id'],
            'type'     => 'maintenance',
            'priority' => 1,
            'severity' => 'warning',
            'icon'     => 'alert-triangle',
            'title'    => 'Vehicle Still in Maintenance',
            'body'     => $r['plate_number'] . ' (' . trim($r['brand'] . ' ' . $r['model']) . ') has been in '
                        . str_replace('_', ' ', $r['service_type']) . ' for ' . $r['days_in'] . ' days.',
            'href'     => BASE_URL . 'modules/maintenance/schedule.php',
            'time'     => $r['service_date'],
        ];
    }

    /* ─────────────────────────────────────────────────────────────
     * PRIORITY 2 — INFO / ACTIONABLE
     * ───────────────────────────────────────────────────────────── */

    /* 2a. Compliance expiring 8–30 days (lower urgency) */
    $expiring30 = $db->fetchAll(
        "SELECT cr.record_id, v.plate_number, v.brand, v.model,
                cr.compliance_type, cr.expiry_date,
                DATEDIFF(cr.expiry_date, CURDATE()) AS days_left,
                cr.vehicle_id
         FROM compliance_records cr
         JOIN vehicles v ON cr.vehicle_id = v.vehicle_id
         WHERE cr.expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 8 DAY)
                                  AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           AND cr.status NOT IN ('renewed','cancelled')
           AND cr.record_id = (
               SELECT MAX(r2.record_id)
               FROM compliance_records r2
               WHERE r2.vehicle_id = cr.vehicle_id
                 AND r2.compliance_type = cr.compliance_type
           )
         ORDER BY cr.expiry_date ASC
         LIMIT 5"
    );
    foreach ($expiring30 as $r) {
        $items[] = [
            'id'       => 'comp-info-' . $r['record_id'],
            'type'     => 'compliance',
            'priority' => 2,
            'severity' => 'info',
            'icon'     => 'shield-alert',
            'title'    => 'Compliance Expiring',
            'body'     => strtoupper(str_replace('_', ' ', $r['compliance_type']))
                        . ' for ' . $r['plate_number'] . ' expires in ' . $r['days_left'] . ' day(s).',
            'href'     => BASE_URL . 'modules/compliance/renew-upload.php?vehicle_id='
                        . urlencode($r['vehicle_id']) . '&type=' . urlencode($r['compliance_type']),
            'time'     => $r['expiry_date'],
        ];
    }

    /* 2b. Scheduled maintenance coming up (not yet overdue) */
    $maintScheduled = $db->fetchAll(
        "SELECT ms.schedule_id, v.plate_number, v.brand, v.model,
                ms.service_type, ms.next_due_date,
                DATEDIFF(ms.next_due_date, CURDATE()) AS days_until
         FROM maintenance_schedules ms
         JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
         WHERE ms.status = 'scheduled'
           AND ms.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY ms.next_due_date ASC
         LIMIT 5"
    );
    foreach ($maintScheduled as $r) {
        $items[] = [
            'id'       => 'maint-sched-' . $r['schedule_id'],
            'type'     => 'maintenance',
            'priority' => 2,
            'severity' => 'info',
            'icon'     => 'calendar-clock',
            'title'    => 'Maintenance Due Soon',
            'body'     => trim($r['brand'] . ' ' . $r['model']) . ' (' . $r['plate_number'] . ') — '
                        . str_replace('_', ' ', ucfirst($r['service_type']))
                        . ' due in ' . $r['days_until'] . ' day(s).',
            'href'     => BASE_URL . 'modules/maintenance/service-view.php?id=' . $r['schedule_id'],
            'time'     => $r['next_due_date'],
        ];
    }

    /* 2c. Pending PRs awaiting approval (procurement roles only) */
    if (in_array($role, ['system_admin', 'fleet_manager', 'procurement_officer'])) {
        $prs = $db->fetchAll(
            "SELECT pr_id, pr_number, purpose_summary, created_at
             FROM procurement_requests
             WHERE status = 'pending_approval'
             ORDER BY created_at ASC
             LIMIT 5"
        );
        foreach ($prs as $p) {
            $items[] = [
                'id'       => 'pr-pending-' . $p['pr_id'],
                'type'     => 'procurement',
                'priority' => 2,
                'severity' => 'info',
                'icon'     => 'clipboard-list',
                'title'    => 'PR Awaiting Approval',
                'body'     => $p['pr_number'] . ' — ' . ($p['purpose_summary'] ?? 'Pending approval.'),
                'href'     => BASE_URL . 'modules/procurement/pr-view.php?id=' . $p['pr_id'],
                'time'     => $p['created_at'],
            ];
        }
    }

    /* 2d. Low stock items */
    try {
        $lowStock = $db->fetchAll(
            "SELECT item_id, item_name, quantity_on_hand, reorder_level
             FROM parts_inventory
             WHERE reorder_level > 0 AND quantity_on_hand <= reorder_level
             ORDER BY quantity_on_hand ASC
             LIMIT 3"
        );
        foreach ($lowStock as $s) {
            $items[] = [
                'id'       => 'stock-' . $s['item_id'],
                'type'     => 'inventory',
                'priority' => 2,
                'severity' => 'info',
                'icon'     => 'package',
                'title'    => 'Low Stock Alert',
                'body'     => htmlspecialchars($s['item_name']) . ' — only '
                            . $s['quantity_on_hand'] . ' left (reorder at ' . $s['reorder_level'] . ').',
                'href'     => BASE_URL . 'modules/inventory/index.php?low_stock=1',
                'time'     => null,
            ];
        }
    } catch (Throwable $ignored) {
        // parts_inventory table may not exist yet
    }

    /* ─────────────────────────────────────────────────────────────
     * Sort: priority ASC (0=critical first), then severity within group
     * ───────────────────────────────────────────────────────────── */
    $sevOrder = ['danger' => 0, 'warning' => 1, 'info' => 2];
    usort($items, function ($a, $b) use ($sevOrder) {
        if ($a['priority'] !== $b['priority']) {
            return $a['priority'] <=> $b['priority'];
        }
        return ($sevOrder[$a['severity']] ?? 9) <=> ($sevOrder[$b['severity']] ?? 9);
    });

    // Remove internal 'priority' key before output
    $output = array_map(function ($item) {
        unset($item['priority']);
        return $item;
    }, $items);

    $total  = count($output);
    $capped = array_slice($output, 0, 25);

    echo json_encode([
        'success'      => true,
        'unread'       => $total,
        'total'        => $total,
        'notifications' => $capped,
        'generated_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'System error',
    ]);
}
