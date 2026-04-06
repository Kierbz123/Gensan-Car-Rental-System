<?php
/**
 * Maintenance Costs Report
 * Path: modules/reports/maintenance-costs.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('reports.view');
$db = Database::getInstance();

$year = (int) ($_GET['year'] ?? date('Y'));
try {
    $monthly = $db->fetchAll(
        "SELECT MONTH(service_date) AS month_num, MONTHNAME(service_date) AS month_name,
                COUNT(*) AS service_count, SUM(cost) AS total_cost
         FROM service_records WHERE YEAR(service_date) = ? GROUP BY MONTH(service_date) ORDER BY month_num",
        [$year]
    );
    $byType = $db->fetchAll(
        "SELECT service_type, COUNT(*) AS count, SUM(cost) AS total
         FROM service_records WHERE YEAR(service_date) = ? GROUP BY service_type ORDER BY total DESC",
        [$year]
    );
    $totalCost = array_sum(array_column($monthly, 'total_cost'));
} catch (Exception $e) {
    $monthly = [];
    $byType = [];
    $totalCost = 0;
}

$pageTitle = 'Maintenance Costs Report';
require_once '../../includes/header.php';
?>
<div class="fade-in">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="heading">Maintenance Cost Analysis</h1>
            <p class="text-secondary-500 font-medium">Service expenditure breakdown by month and type.</p>
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
        <?php foreach ([['Annual Expenditure', formatCurrency($totalCost), 'banknote', 'danger'], ['Total Services', array_sum(array_column($monthly, 'service_count')), 'wrench', 'warning'], ['Avg Monthly', count($monthly) > 0 ? formatCurrency($totalCost / count($monthly)) : '₱0.00', 'bar-chart-2', 'primary']] as [$l, $v, $ic, $cl]): ?>
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
                <h2 class="text-xs font-black uppercase tracking-widest text-pure-white">Monthly Breakdown —
                    <?= $year ?>
                </h2>
            </div>
            <div class="table-wrapper border-none">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-center">Services</th>
                            <th class="text-right">Total Cost</th>
                            <th>Bar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthly)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-16 text-secondary-400">No data.</td>
                            </tr>
                        <?php else:
                            $maxCost = max(array_column($monthly, 'total_cost'));
                            foreach ($monthly as $m):
                                $pct = $maxCost > 0 ? round($m['total_cost'] / $maxCost * 100) : 0;
                                ?>
                                <tr>
                                    <td class="font-bold"><?= $m['month_name'] ?></td>
                                    <td class="text-center font-bold"><?= $m['service_count'] ?></td>
                                    <td class="text-right font-bold text-danger-600"><?= formatCurrency($m['total_cost']) ?>
                                    </td>
                                    <td>
                                        <div class="h-2 bg-secondary-100 rounded-full overflow-hidden w-full">
                                            <div class="h-full bg-danger-400 rounded-full" style="width:<?= $pct ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2 class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-5">By Service Type</h2>
            <div class="flex flex-col gap-3">
                <?php foreach ($byType as $t): ?>
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold text-secondary-700 truncate"><?= str_replace('_', ' ', ucfirst($t['service_type'])) ?></span>
                        <span class="font-black text-xs text-danger-600 ml-2"><?= formatCurrency($t['total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($byType)): ?>
                    <p class="text-secondary-400 text-sm">No data.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>