<?php
// modules/dashboard/dashboard-snapshot.php
// Generates a printable HTML snapshot of the current dashboard KPIs
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('reports.view');

$db = Database::getInstance();

// Gather all KPI data
try {
    $totalVehicles = (int) ($db->fetchColumn("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL") ?? 0);
    $activeRentals = (int) ($db->fetchColumn("SELECT COUNT(*) FROM rental_agreements WHERE status = ?", [RENTAL_STATUS_ACTIVE]) ?? 0);
    $pendingMaintenance = (int) ($db->fetchColumn("SELECT COUNT(*) FROM maintenance_schedules WHERE status IN ('active','overdue') AND next_due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)") ?? 0);
    $revenueThisMonth = (float) ($db->fetchColumn("SELECT COALESCE(SUM(total_amount),0) FROM rental_agreements WHERE status = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())", [RENTAL_STATUS_RETURNED]) ?? 0);
    $totalCustomers = (int) ($db->fetchColumn("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL") ?? 0);
    $pendingPR = (int) ($db->fetchColumn("SELECT COUNT(*) FROM procurement_requests WHERE status = ?", [PR_STATUS_PENDING]) ?? 0);
    $expiredCompliance = (int) ($db->fetchColumn("SELECT COUNT(*) FROM compliance_records WHERE status = 'expired'") ?? 0);

    $statusDist = $db->fetchAll("SELECT current_status, COUNT(*) as count FROM vehicles WHERE deleted_at IS NULL GROUP BY current_status ORDER BY count DESC");

    $recentRentals = $db->fetchAll("
        SELECT r.agreement_number, r.status, r.total_amount, r.rental_start_date, r.rental_end_date,
               CONCAT(c.first_name,' ',c.last_name) as customer_name,
               v.plate_number, v.brand, v.model
        FROM rental_agreements r
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN vehicles v ON r.vehicle_id = v.vehicle_id
        ORDER BY r.created_at DESC LIMIT 10
    ");

    $upcomingMaint = $db->fetchAll("
        SELECT ms.service_type, ms.next_due_date, ms.status,
               v.plate_number, v.brand, v.model
        FROM maintenance_schedules ms
        JOIN vehicles v ON ms.vehicle_id = v.vehicle_id
        WHERE ms.status != 'completed'
        ORDER BY ms.next_due_date ASC LIMIT 10
    ");
} catch (Exception $e) {
    $totalVehicles = $activeRentals = $pendingMaintenance = $revenueThisMonth = $totalCustomers = $pendingPR = $expiredCompliance = 0;
    $statusDist = $recentRentals = $upcomingMaint = [];
}

$generatedAt = date('F d, Y \a\t H:i A');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Snapshot —
        <?php echo $generatedAt; ?>
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #f8fafc;
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 32px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #0f172a;
            padding-bottom: 20px;
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .header .meta {
            text-align: right;
            color: #64748b;
            font-size: 11px;
        }

        .header .meta strong {
            display: block;
            color: #0f172a;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .kpi {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            border-top: 3px solid #2563eb;
        }

        .kpi.danger {
            border-top-color: #dc2626;
        }

        .kpi.success {
            border-top-color: #16a34a;
        }

        .kpi.warning {
            border-top-color: #ca8a04;
        }

        .kpi-label {
            font-size: 9px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
        }

        .kpi-value {
            font-size: 28px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        section {
            margin-bottom: 32px;
        }

        section h2 {
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }

        th {
            background: #0f172a;
            color: #fff;
            text-align: left;
            padding: 10px 14px;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        td {
            padding: 9px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 11px;
            color: #334155;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:nth-child(even) td {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-pending {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-default {
            background: #f1f5f9;
            color: #475569;
        }

        .fleet-dist {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .fleet-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            flex: 1;
            min-width: 120px;
        }

        .fleet-item .count {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
        }

        .fleet-item .label {
            font-size: 9px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            color: #94a3b8;
            font-size: 10px;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm;
            }

            .no-print {
                display: none !important;
            }

            body {
                background: #fff;
            }

            html, body, .page {
                height: 100%;
                max-height: 100vh;
                page-break-inside: avoid;
                page-break-after: avoid;
                page-break-before: avoid;
                overflow: hidden;
            }

            .page {
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="page">

        <!-- Header -->
        <div class="header">
            <div>
                <h1>Gensan Car Rental — Operational Snapshot</h1>
                <p style="color:#64748b;font-size:11px;margin-top:4px;">Integrated Asset Tracking & Procurement
                    Management System</p>
            </div>
            <div class="meta">
                <strong>Generated By:
                    <?php echo htmlspecialchars($authUser->getData()['first_name'] . ' ' . $authUser->getData()['last_name']); ?>
                </strong>
                <?php echo $generatedAt; ?><br>
                <button onclick="window.print()" class="no-print"
                    style="margin-top:8px;padding:6px 16px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:10px;font-weight:800;cursor:pointer;text-transform:uppercase;letter-spacing:1px;">🖨
                    Print / Save PDF</button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi-label">Total Fleet</div>
                <div class="kpi-value">
                    <?php echo $totalVehicles; ?>
                </div>
            </div>
            <div class="kpi success">
                <div class="kpi-label">Active Rentals</div>
                <div class="kpi-value">
                    <?php echo $activeRentals; ?>
                </div>
            </div>
            <div class="kpi warning">
                <div class="kpi-label">Maintenance Due</div>
                <div class="kpi-value">
                    <?php echo $pendingMaintenance; ?>
                </div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Revenue (Month)</div>
                <div class="kpi-value" style="font-size:20px;">₱
                    <?php echo number_format($revenueThisMonth, 2); ?>
                </div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Total Clients</div>
                <div class="kpi-value">
                    <?php echo $totalCustomers; ?>
                </div>
            </div>
            <div class="kpi warning">
                <div class="kpi-label">Pending PRs</div>
                <div class="kpi-value">
                    <?php echo $pendingPR; ?>
                </div>
            </div>
            <div class="kpi <?php echo $expiredCompliance > 0 ? 'danger' : 'success'; ?>">
                <div class="kpi-label">Expired Compliance</div>
                <div class="kpi-value">
                    <?php echo $expiredCompliance; ?>
                </div>
            </div>
        </div>

        <!-- Fleet Status Distribution -->
        <?php if (!empty($statusDist)): ?>
            <section>
                <h2>Fleet Status Distribution</h2>
                <div class="fleet-dist">
                    <?php foreach ($statusDist as $s): ?>
                        <div class="fleet-item">
                            <div class="count">
                                <?php echo $s['count']; ?>
                            </div>
                            <div class="label">
                                <?php echo ucfirst(str_replace('_', ' ', $s['current_status'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Recent Rentals -->
        <section>
            <h2>Recent Rental Agreements (Last 10)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Agreement #</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRentals)): ?>
                        <tr>
                            <td colspan="7" style="color:#94a3b8;text-align:center;padding:20px;">No rental records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($recentRentals as $r): ?>
                        <tr>
                            <td style="font-family:monospace;font-weight:700;">
                                <?php echo $r['agreement_number']; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($r['customer_name']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars("{$r['brand']} {$r['model']} · {$r['plate_number']}"); ?>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($r['rental_start_date'])); ?>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($r['rental_end_date'])); ?>
                            </td>
                            <td>₱
                                <?php echo number_format($r['total_amount'] ?? 0, 2); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo match ($r['status']) {
                                    'active' => 'badge-active', 'returned', 'completed' => 'badge-default',
                                    'cancelled' => 'badge-expired', default => 'badge-pending'
                                }; ?>">
                                    <?php echo $r['status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- Upcoming Maintenance -->
        <section>
            <h2>Upcoming / Overdue Maintenance (Next 10)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>Service Type</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingMaint)): ?>
                        <tr>
                            <td colspan="5" style="color:#94a3b8;text-align:center;padding:20px;">No maintenance schedules
                                found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($upcomingMaint as $m): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars("{$m['brand']} {$m['model']}"); ?>
                            </td>
                            <td style="font-family:monospace;font-weight:700;">
                                <?php echo $m['plate_number']; ?>
                            </td>
                            <td>
                                <?php echo ucfirst(str_replace('_', ' ', $m['service_type'])); ?>
                            </td>
                            <td>
                                <?php
                                $due = strtotime($m['next_due_date']);
                                $color = $due < time() ? '#dc2626' : '#334155';
                                echo "<span style='color:{$color};font-weight:700;'>" . date('M d, Y', $due) . "</span>";
                                ?>
                            </td>
                            <td>
                                <span class="badge <?php echo match ($m['status']) {
                                    'active' => 'badge-active', 'overdue' => 'badge-expired', default => 'badge-pending'
                                }; ?>">
                                    <?php echo $m['status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <div class="footer">
            <span>Gensan Car Rental Services — IATPS v3.1</span>
            <span>Snapshot generated:
                <?php echo $generatedAt; ?>
            </span>
        </div>

    </div>
</body>

</html>