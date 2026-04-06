<?php
/**
 * Customer Analytics Report
 * Path: modules/reports/customer-analytics.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('reports.view');
$db = Database::getInstance();

$year = (int) ($_GET['year'] ?? date('Y'));
try {
    $newCustomers = $db->fetchAll("SELECT MONTH(created_at) AS m, MONTHNAME(created_at) AS month_name, COUNT(*) AS count FROM customers WHERE YEAR(created_at)=? AND deleted_at IS NULL GROUP BY m ORDER BY m", [$year]);
    $topCustomers = $db->fetchAll("SELECT c.customer_id, CONCAT(c.first_name,' ',c.last_name) AS name, c.customer_code, COUNT(ra.agreement_id) AS rentals, COALESCE(SUM(ra.total_amount),0) AS spent FROM customers c LEFT JOIN rental_agreements ra ON ra.customer_id=c.customer_id AND ra.status IN ('completed','returned') WHERE c.deleted_at IS NULL GROUP BY c.customer_id ORDER BY spent DESC LIMIT 10");
    $byType = $db->fetchAll("SELECT customer_type, COUNT(*) AS total FROM customers WHERE deleted_at IS NULL GROUP BY customer_type ORDER BY total DESC");
    $totalCustomers = $db->fetchColumn("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL");
} catch (Exception $e) {
    $newCustomers = [];
    $topCustomers = [];
    $byType = [];
    $totalCustomers = 0;
}

$pageTitle = 'Customer Analytics';
require_once '../../includes/header.php';
?>
<div class="fade-in">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="heading">Customer Analytics</h1>
            <p class="text-secondary-500 font-medium">Acquisition trends, top clients, and segmentation.</p>
        </div>
        <form method="GET" class="flex items-center gap-3">
            <select name="year" class="form-input rounded-2xl py-2.5 bg-white border-secondary-200 font-bold text-sm"
                onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?>
            </select>
        </form>
    </div>

    <div class="stats-grid">
        <?php foreach ([['Total Clients', $totalCustomers, 'users', 'primary'], ['New This Year', array_sum(array_column($newCustomers, 'count')), 'user-plus', 'success'], ['Customer Types', count($byType), 'layers', 'warning']] as [$l, $v, $ic, $cl]): ?>
            <div class="stat-card">
                <div class="stat-card-icon <?= $cl ?>"><i data-lucide="<?= $ic ?>"></i></div>
                <div class="stat-value"><?= $v ?></div>
                <div class="stat-label"><?= $l ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 card p-0 overflow-hidden">
            <div class="p-5 bg-secondary-900">
                <h2 class="text-xs font-black uppercase tracking-widest text-pure-white">Top 10 Clients by Revenue</h2>
            </div>
            <div class="table-wrapper border-none">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th class="text-center">Rentals</th>
                            <th class="text-right">Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topCustomers)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-16 text-secondary-400">No data.</td>
                            </tr>
                        <?php else:
                            foreach ($topCustomers as $i => $c): ?>
                                <tr>
                                    <td class="font-black text-secondary-400 text-xs">#<?= $i + 1 ?></td>
                                    <td>
                                        <p class="font-bold"><?= htmlspecialchars($c['name']) ?></p>
                                        <p class="text-[10px] font-bold text-primary-500 uppercase tracking-widest">
                                            <?= htmlspecialchars($c['customer_code']) ?>
                                        </p>
                                    </td>
                                    <td class="text-center font-bold"><?= $c['rentals'] ?></td>
                                    <td class="text-right font-black text-success-600"><?= formatCurrency($c['spent']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2 class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-5">Customer Segments</h2>
            <div class="flex flex-col gap-3">
                <?php $grandTotal = array_sum(array_column($byType, 'total'));
                foreach ($byType as $t):
                    $pct = $grandTotal > 0 ? round($t['total'] / $grandTotal * 100) : 0;
                    ?>
                    <div>
                        <div class="flex justify-between mb-1"><span
                                class="text-xs font-bold text-secondary-700"><?= str_replace('_', ' ', ucfirst($t['customer_type'])) ?></span><span
                                class="text-xs font-black text-secondary-900"><?= $t['total'] ?> (<?= $pct ?>%)</span></div>
                        <div class="h-2 bg-secondary-100 rounded-full overflow-hidden">
                            <div class="h-full bg-primary-500 rounded-full" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>