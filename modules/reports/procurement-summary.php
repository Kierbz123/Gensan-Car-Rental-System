<?php
/**
 * Procurement Summary Report
 * Path: modules/reports/procurement-summary.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('reports.view');
$db = Database::getInstance();

$year = (int) ($_GET['year'] ?? date('Y'));
try {
    $summary = $db->fetchAll("SELECT status, COUNT(*) AS count, COALESCE(SUM(total_estimated_cost),0) AS total FROM procurement_requests WHERE YEAR(created_at)=? GROUP BY status", [$year]);
    $byCategory = $db->fetchAll("SELECT pi.item_category AS category, COUNT(*) AS count FROM procurement_items pi JOIN procurement_requests pr ON pi.pr_id=pr.pr_id WHERE YEAR(pr.created_at)=? GROUP BY pi.item_category ORDER BY count DESC", [$year]);
    $topSuppliers = $db->fetchAll("SELECT s.company_name, COUNT(po.po_id) AS orders, COALESCE(SUM(po.total_amount),0) AS total FROM suppliers s LEFT JOIN purchase_orders po ON po.supplier_id=s.supplier_id AND YEAR(po.created_at)=? GROUP BY s.supplier_id ORDER BY total DESC LIMIT 5", [$year]);
} catch (Exception $e) {
    $summary = [];
    $byCategory = [];
    $topSuppliers = [];
}

$pageTitle = 'Procurement Summary';
require_once '../../includes/header.php';
?>
<div class="fade-in">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="heading">Procurement Summary</h1>
            <p class="text-secondary-500 font-medium">Purchase request and order analytics.</p>
        </div>
        <form method="GET"><select name="year"
                class="form-input rounded-2xl py-2.5 bg-white border-secondary-200 font-bold text-sm"
                onchange="this.form.submit()"><?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?>
            </select></form>
    </div>

    <div class="stats-grid">
        <?php $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'ordered' => 'primary', 'received' => 'info'];
        $statusIcons = ['pending' => 'clock', 'approved' => 'check-circle', 'rejected' => 'x-circle', 'ordered' => 'shopping-cart', 'received' => 'package'];
        foreach ($summary as $s):
            $cl = $statusColors[$s['status']] ?? 'secondary';
            $ic = $statusIcons[$s['status']] ?? 'file'; ?>
            <div class="stat-card">
                <div class="stat-card-icon <?= $cl ?>"><i data-lucide="<?= $ic ?>"></i></div>
                <div class="stat-value"><?= $s['count'] ?></div>
                <div class="stat-label"><?= strtoupper($s['status']) ?> (<?= formatCurrency($s['total']) ?>)</div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="card">
            <h2 class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-5">By Category</h2>
            <?php $grandTotal = max(1, array_sum(array_column($byCategory, 'count')));
            foreach ($byCategory as $c):
                $pct = round($c['count'] / $grandTotal * 100); ?>
                <div class="mb-3">
                    <div class="flex justify-between mb-1"><span
                            class="text-xs font-bold text-secondary-700"><?= str_replace('_', ' ', ucwords(str_replace('_', ' ', $c['category']))) ?></span><span
                            class="text-xs font-black"><?= $c['count'] ?> (<?= $pct ?>%)</span></div>
                    <div class="h-2 bg-secondary-100 rounded-full overflow-hidden">
                        <div class="h-full bg-primary-500 rounded-full" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="p-5 bg-secondary-900">
                <h2 class="text-xs font-black uppercase tracking-widest text-pure-white">Top Suppliers by PO Value</h2>
            </div>
            <div class="table-wrapper border-none">
                <table>
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th class="text-center">Orders</th>
                            <th class="text-right">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topSuppliers)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-12 text-secondary-400">No data.</td>
                            </tr>
                        <?php else:
                            foreach ($topSuppliers as $s): ?>
                                <tr>
                                    <td class="font-bold text-sm"><?= htmlspecialchars($s['company_name']) ?></td>
                                    <td class="text-center font-bold"><?= $s['orders'] ?></td>
                                    <td class="text-right font-black text-success-600"><?= formatCurrency($s['total']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>