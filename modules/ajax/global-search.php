<?php
/**
 * Global Search AJAX Endpoint
 * GET /modules/ajax/global-search.php?q=term
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Ensure basic operational access
if (!$authUser->hasPermission('view_dashboard') && !$authUser->hasPermission('dashboard.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => [], 'query' => $q]);
    exit;
}

$like = '%' . $q . '%';
$results = [];

try {
    $db = Database::getInstance();

    /* ── Vehicles ── */
    $vehicles = $db->fetchAll(
        "SELECT vehicle_id, plate_number, brand, model, year_model, current_status
         FROM vehicles
         WHERE deleted_at IS NULL
           AND (plate_number LIKE ? OR brand LIKE ? OR model LIKE ? OR vehicle_id LIKE ?)
         LIMIT 5",
        [$like, $like, $like, $like]
    );
    foreach ($vehicles as $v) {
        $statusColor = match ($v['current_status']) {
            'available' => 'success', 'rented' => 'danger',
            'maintenance' => 'warning', 'reserved' => 'info', default => 'secondary'
        };
        $results[] = [
            'type' => 'vehicle',
            'icon' => 'car',
            'label' => $v['brand'] . ' ' . $v['model'] . ' ' . $v['year_model'],
            'sub' => $v['plate_number'],
            'badge' => ucfirst(str_replace('_', ' ', $v['current_status'])),
            'badgeColor' => $statusColor,
            'href' => BASE_URL . 'modules/asset-tracking/vehicle-view.php?id=' . urlencode($v['vehicle_id']),
        ];
    }

    /* ── Customers ── */
    $customers = $db->fetchAll(
        "SELECT customer_id, customer_code, first_name, last_name, phone_primary, credit_rating
         FROM customers
         WHERE deleted_at IS NULL
           AND (first_name LIKE ? OR last_name LIKE ? OR customer_code LIKE ? OR phone_primary LIKE ?)
         LIMIT 5",
        [$like, $like, $like, $like]
    );
    foreach ($customers as $c) {
        $results[] = [
            'type' => 'customer',
            'icon' => 'user',
            'label' => $c['first_name'] . ' ' . $c['last_name'],
            'sub' => $c['customer_code'] . ' · ' . $c['phone_primary'],
            'badge' => ucfirst($c['credit_rating']),
            'badgeColor' => $c['credit_rating'] === 'blacklisted' ? 'danger' : ($c['credit_rating'] === 'excellent' || $c['credit_rating'] === 'good' ? 'success' : 'warning'),
            'href' => BASE_URL . 'modules/customers/customer-view.php?id=' . $c['customer_id'],
        ];
    }

    /* ── Rental Agreements ── */
    $rentals = $db->fetchAll(
        "SELECT ra.agreement_id, ra.agreement_number, ra.status, ra.rental_start_date,
                CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                v.plate_number
         FROM rental_agreements ra
         JOIN customers c ON ra.customer_id = c.customer_id
         JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
         WHERE ra.agreement_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?
         LIMIT 4",
        [$like, $like, $like]
    );
    foreach ($rentals as $r) {
        $statusColor = match ($r['status']) {
            'active' => 'primary', 'completed', 'returned' => 'success',
            'cancelled', 'no_show' => 'danger', default => 'warning'
        };
        $results[] = [
            'type' => 'rental',
            'icon' => 'calendar-range',
            'label' => $r['agreement_number'],
            'sub' => $r['customer_name'] . ' · ' . $r['plate_number'],
            'badge' => ucfirst($r['status']),
            'badgeColor' => $statusColor,
            'href' => BASE_URL . 'modules/rentals/view.php?id=' . $r['agreement_id'],
        ];
    }

    /* ── Purchase Requests ── */
    $prs = $db->fetchAll(
        "SELECT pr_id, pr_number, purpose_summary, status, urgency
         FROM procurement_requests
         WHERE pr_number LIKE ? OR purpose_summary LIKE ?
         LIMIT 3",
        [$like, $like]
    );
    foreach ($prs as $p) {
        $results[] = [
            'type' => 'pr',
            'icon' => 'clipboard-list',
            'label' => $p['pr_number'],
            'sub' => $p['purpose_summary'] ? substr($p['purpose_summary'], 0, 55) . '…' : 'Purchase Request',
            'badge' => ucfirst(str_replace('_', ' ', $p['status'])),
            'badgeColor' => in_array($p['status'], ['approved', 'fully_received']) ? 'success'
                : (in_array($p['status'], ['rejected', 'cancelled']) ? 'danger' : 'warning'),
            'href' => BASE_URL . 'modules/procurement/pr-view.php?id=' . $p['pr_id'],
        ];
    }

    echo json_encode(['results' => $results, 'query' => $q, 'count' => count($results)], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => DEBUG_MODE ? $e->getMessage() : 'Search failed', 'results' => []]);
}

