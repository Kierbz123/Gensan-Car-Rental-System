<?php
/**
 * Dashboard AJAX Handler
 * Serves dynamic data for all enhanced dashboard widgets.
 * Path: modules/dashboard/dashboard-ajax.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!$authUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db       = Database::getInstance();
$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$userId   = $authUser->getData()['user_id'];
$userRole = $authUser->getData()['role'];

try {
    switch ($action) {

        // ── 1. Fleet Status Distribution for Pie Chart ────────────────
        case 'fleet_pie':
            $rows = $db->fetchAll(
                "SELECT current_status, COUNT(*) AS cnt
                 FROM vehicles
                 WHERE deleted_at IS NULL
                 GROUP BY current_status"
            );
            $labels = [];
            $data   = [];
            $colors = [
                'available'     => '#059669',
                'rented'        => '#2563eb',
                'maintenance'   => '#d97706',
                'reserved'      => '#7c3aed',
                'cleaning'      => '#0284c7',
                'out_of_service'=> '#dc2626',
                'retired'       => '#94a3b8',
            ];
            $bgColors = [];
            foreach ($rows as $r) {
                $labels[]   = ucfirst(str_replace('_', ' ', $r['current_status']));
                $data[]     = (int) $r['cnt'];
                $bgColors[] = $colors[$r['current_status']] ?? '#94a3b8';
            }
            echo json_encode([
                'labels'   => $labels,
                'data'     => $data,
                'colors'   => $bgColors,
            ]);
            break;

        // ── 2. Revenue Trend (Last 6 Months) for Line Chart ──────────
        case 'revenue_trend':
            $rows = $db->fetchAll(
                "SELECT
                    DATE_FORMAT(created_at, '%Y-%m') AS month_key,
                    DATE_FORMAT(created_at, '%b %Y')  AS month_label,
                    COALESCE(SUM(total_amount), 0)    AS revenue
                 FROM rental_agreements
                 WHERE status NOT IN ('cancelled', 'draft', 'no_show')
                   AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                 GROUP BY month_key, month_label
                 ORDER BY month_key ASC"
            );

            // Fill any missing months
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $months[date('Y-m', strtotime("-{$i} months"))] = [
                    'label'   => date('M Y', strtotime("-{$i} months")),
                    'revenue' => 0,
                ];
            }
            foreach ($rows as $r) {
                if (isset($months[$r['month_key']])) {
                    $months[$r['month_key']]['revenue'] = (float) $r['revenue'];
                }
            }

            $labels   = array_column($months, 'label');
            $revenues = array_column($months, 'revenue');

            // MoM comparison: current vs previous month
            $thisMonth = (float) ($months[date('Y-m')]['revenue'] ?? 0);
            $prevKey   = date('Y-m', strtotime('-1 month'));
            $prevMonth = (float) ($months[$prevKey]['revenue'] ?? 0);
            $momPct    = $prevMonth > 0 ? round((($thisMonth - $prevMonth) / $prevMonth) * 100, 1) : null;

            echo json_encode([
                'labels'    => $labels,
                'revenues'  => $revenues,
                'this_month'=> $thisMonth,
                'prev_month'=> $prevMonth,
                'mom_pct'   => $momPct,
            ]);
            break;

        // ── 3. Today's Gantt / Itinerary ───────────────────────────────
        case 'todays_itinerary':
            $today = date('Y-m-d');
            $rows  = $db->fetchAll(
                "SELECT
                    ra.agreement_id, ra.agreement_number,
                    ra.rental_start_date, ra.rental_end_date,
                    ra.status,
                    CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                    v.plate_number, v.brand, v.model, v.current_status AS vehicle_status
                 FROM rental_agreements ra
                 JOIN customers c ON ra.customer_id = c.customer_id
                 JOIN vehicles  v ON ra.vehicle_id  = v.vehicle_id
                 WHERE ra.status IN ('active','reserved','confirmed')
                   AND (
                       DATE(ra.rental_start_date) = ?
                    OR DATE(ra.rental_end_date)   = ?
                    OR (DATE(ra.rental_start_date) < ? AND DATE(ra.rental_end_date) > ?)
                   )
                 ORDER BY ra.rental_start_date ASC",
                [$today, $today, $today, $today]
            );
            echo json_encode(['itinerary' => $rows ?: []]);
            break;

        // ── 4. Role-Based Task Queue (Personalised Inbox) ─────────────
        case 'task_queue':
            $tasks = [];

            // Universal: overdue returns
            $overdueCount = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM rental_agreements
                 WHERE status = 'active'
                   AND rental_end_date < DATE_SUB(NOW(), INTERVAL 2 HOUR)"
            );
            if ($overdueCount > 0) {
                $tasks[] = [
                    'priority' => 'critical',
                    'icon'     => 'alert-octagon',
                    'count'    => $overdueCount,
                    'label'    => 'Overdue vehicle return' . ($overdueCount > 1 ? 's' : '') . ' need immediate action',
                    'link'     => BASE_URL . 'modules/rentals/index.php?status=active',
                ];
            }

            // Role-specific tasks
            switch ($userRole) {
                case 'system_admin':
                case 'manager':
                    $pendingPR = (int) $db->fetchColumn(
                        "SELECT COUNT(*) FROM procurement_requests WHERE status = 'pending_approval'"
                    );
                    if ($pendingPR > 0) {
                        $tasks[] = [
                            'priority' => 'warning',
                            'icon'     => 'shopping-cart',
                            'count'    => $pendingPR,
                            'label'    => 'Purchase request' . ($pendingPR > 1 ? 's' : '') . ' awaiting your approval',
                            'link'     => BASE_URL . 'modules/procurement/index.php?status=pending_approval',
                        ];
                    }
                    $expCompliance = (int) $db->fetchColumn(
                        "SELECT COUNT(*) FROM compliance_records WHERE status = 'expired'"
                    );
                    if ($expCompliance > 0) {
                        $tasks[] = [
                            'priority' => 'danger',
                            'icon'     => 'shield-x',
                            'count'    => $expCompliance,
                            'label'    => 'Compliance document' . ($expCompliance > 1 ? 's' : '') . ' expired — action required',
                            'link'     => BASE_URL . 'modules/compliance/index.php?status=expired',
                        ];
                    }
                    break;

                case 'mechanic':
                case 'maintenance_staff':
                    $myWorkOrders = (int) $db->fetchColumn(
                        "SELECT COUNT(*) FROM maintenance_schedules
                         WHERE status IN ('pending','active')
                           AND DATE(next_service_date) <= CURDATE()"
                    );
                    if ($myWorkOrders > 0) {
                        $tasks[] = [
                            'priority' => 'warning',
                            'icon'     => 'wrench',
                            'count'    => $myWorkOrders,
                            'label'    => 'Maintenance schedule' . ($myWorkOrders > 1 ? 's' : '') . ' due for service today',
                            'link'     => BASE_URL . 'modules/maintenance/index.php',
                        ];
                    }
                    break;

                case 'procurement_officer':
                    $draftPR = (int) $db->fetchColumn(
                        "SELECT COUNT(*) FROM procurement_requests WHERE status = 'draft'"
                    );
                    if ($draftPR > 0) {
                        $tasks[] = [
                            'priority' => 'info',
                            'icon'     => 'file-text',
                            'count'    => $draftPR,
                            'label'    => 'Draft purchase request' . ($draftPR > 1 ? 's' : '') . ' ready to submit',
                            'link'     => BASE_URL . 'modules/procurement/index.php?status=draft',
                        ];
                    }
                    break;

                case 'rental_agent':
                case 'staff':
                default:
                    $todayCheckouts = (int) $db->fetchColumn(
                        "SELECT COUNT(*) FROM rental_agreements
                         WHERE status IN ('reserved','confirmed')
                           AND DATE(rental_start_date) = CURDATE()"
                    );
                    if ($todayCheckouts > 0) {
                        $tasks[] = [
                            'priority' => 'info',
                            'icon'     => 'key',
                            'count'    => $todayCheckouts,
                            'label'    => 'Vehicle checkout' . ($todayCheckouts > 1 ? 's' : '') . ' scheduled for today',
                            'link'     => BASE_URL . 'modules/rentals/index.php',
                        ];
                    }
                    break;
            }

            // Universal: low stock
            try {
                $lowStock = (int) $db->fetchColumn(
                    "SELECT COUNT(*) FROM parts_inventory
                     WHERE reorder_level > 0 AND quantity_on_hand <= reorder_level"
                );
                if ($lowStock > 0) {
                    $tasks[] = [
                        'priority' => 'warning',
                        'icon'     => 'package',
                        'count'    => $lowStock,
                        'label'    => 'Inventory item' . ($lowStock > 1 ? 's' : '') . ' below reorder level',
                        'link'     => BASE_URL . 'modules/inventory/index.php?low_stock=1',
                    ];
                }
            } catch (Exception $e) {
                // Table may not exist yet
            }

            echo json_encode(['tasks' => $tasks]);
            break;

        // ── 5. Save Widget Layout Preference ─────────────────────────
        case 'save_layout':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            $input  = json_decode(file_get_contents('php://input'), true);
            $layout = json_encode($input['layout'] ?? []);

            // Persist in user_preferences table if exists, else log only
            try {
                $exists = $db->fetchColumn(
                    "SELECT COUNT(*) FROM user_preferences WHERE user_id = ? AND pref_key = 'dashboard_layout'",
                    [$userId]
                );
                if ($exists) {
                    $db->execute(
                        "UPDATE user_preferences SET pref_value = ?, updated_at = NOW()
                         WHERE user_id = ? AND pref_key = 'dashboard_layout'",
                        [$layout, $userId]
                    );
                } else {
                    $db->execute(
                        "INSERT INTO user_preferences (user_id, pref_key, pref_value, created_at, updated_at)
                         VALUES (?, 'dashboard_layout', ?, NOW(), NOW())",
                        [$userId, $layout]
                    );
                }
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                // Graceful fallback: persist only in session
                $_SESSION['dashboard_layout'] = $layout;
                echo json_encode(['success' => true, 'note' => 'Saved to session']);
            }
            break;

        // ── 6. Load Widget Layout Preference ─────────────────────────
        case 'load_layout':
            $layout = null;
            try {
                $row = $db->fetchOne(
                    "SELECT pref_value FROM user_preferences
                     WHERE user_id = ? AND pref_key = 'dashboard_layout'",
                    [$userId]
                );
                $layout = $row ? json_decode($row['pref_value'], true) : null;
            } catch (Exception $e) {
                $layout = isset($_SESSION['dashboard_layout'])
                    ? json_decode($_SESSION['dashboard_layout'], true)
                    : null;
            }
            echo json_encode(['layout' => $layout]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
