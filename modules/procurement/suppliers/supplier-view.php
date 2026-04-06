<?php
/**
 * Supplier View
 * Path: modules/procurement/suppliers/supplier-view.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
$authUser->requirePermission('procurement.view');
$db = Database::getInstance();
$sid = (int) ($_GET['id'] ?? 0);
if (!$sid) {
    redirect('modules/procurement/suppliers/', 'Missing ID', 'error');
}
$supplier = $db->fetchOne("SELECT * FROM suppliers WHERE supplier_id=?", [$sid]);
if (!$supplier) {
    redirect('modules/procurement/suppliers/', 'Not found', 'error');
}
$orders = $db->fetchAll("SELECT po.*, pr.pr_number, pr.purpose_summary AS title FROM purchase_orders po JOIN procurement_requests pr ON po.pr_id=pr.pr_id WHERE po.supplier_id=? ORDER BY po.created_at DESC LIMIT 10", [$sid]);
$pageTitle = 'Supplier — ' . $supplier['company_name'];
require_once '../../../includes/header.php';
?>
<div class="fade-in max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest"><a href="index.php"
            class="text-secondary-400 hover:text-primary-600">Suppliers</a><span
            class="text-secondary-200">/</span><span
            class="text-primary-600"><?= htmlspecialchars($supplier['company_name']) ?></span></div>
    <div class="card mb-6 bg-gradient-to-r from-secondary-900 to-secondary-800 text-pure-white border-none">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-black mb-1"><?= htmlspecialchars($supplier['company_name']) ?></h1>
                <p class="text-secondary-400"><?= htmlspecialchars($supplier['categories'] ?? '') ?></p>
                <div class="mt-2"><span
                        class="badge badge-<?= ($supplier['is_active'] ?? 1) ? 'success' : 'secondary' ?> text-[9px] font-black"><?= ($supplier['is_active'] ?? 1) ? 'ACTIVE' : 'INACTIVE' ?></span>
                </div>
            </div>
            <?php if ($authUser->hasPermission('procurement.update')): ?><a href="supplier-edit.php?id=<?= $sid ?>"
                    class="btn btn-secondary bg-white/10 border-white/10 text-white text-xs gap-2"><i data-lucide="pencil"
                        class="w-4 h-4"></i> Edit</a><?php endif; ?>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="card">
            <h2 class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-4 flex items-center gap-2"><i
                    data-lucide="contact" class="w-4 h-4 text-primary-600"></i> Contact</h2>
            <?php foreach ([['Contact Person', $supplier['contact_person']], ['Phone', $supplier['phone']], ['Email', $supplier['email']], ['Address', $supplier['address']], ['Payment Terms', strtoupper($supplier['payment_terms'] ?? '')]] as [$l, $v]):
                if (!$v)
                    continue; ?>
                <div class="mb-3">
                    <p class="text-[10px] font-black uppercase tracking-widest text-secondary-400"><?= $l ?></p>
                    <p class="font-bold text-sm text-secondary-900"><?= htmlspecialchars($v) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h2 class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-4 flex items-center gap-2"><i
                    data-lucide="box" class="w-4 h-4 text-success-600"></i> Summary</h2>
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between p-3 bg-secondary-50 rounded-xl"><span
                        class="text-xs font-bold text-secondary-600">Total Orders</span><span
                        class="font-extrabold text-secondary-900"><?= count($orders) ?></span></div>
                <?php if ($supplier['notes']): ?>
                    <div class="p-3 bg-secondary-50 rounded-xl text-xs font-medium text-secondary-700">
                        <?= nl2br(htmlspecialchars($supplier['notes'])) ?>
                    </div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card">
        <h2 class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-5 flex items-center gap-2"><i
                data-lucide="shopping-cart" class="w-4 h-4 text-primary-600"></i> Recent Purchase Orders</h2>
        <?php if (empty($orders)): ?>
            <p class="text-secondary-400 text-sm text-center py-8">No orders yet.</p><?php else: ?>
            <div class="table-wrapper border-none -mx-1">
                <table>
                    <thead>
                        <tr>
                            <th>PO #</th>
                            <th>PR</th>
                            <th>Delivery</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="font-mono text-xs font-bold text-primary-600">
                                    <?= htmlspecialchars($o['po_number'] ?? '') ?>
                                </td>
                                <td class="text-xs"><?= htmlspecialchars($o['pr_number']) ?></td>
                                <td class="text-xs"><?= formatDate($o['delivery_date'] ?? '') ?></td>
                                <td class="font-bold text-xs"><?= formatCurrency($o['total_amount'] ?? 0) ?></td>
                                <td><span
                                        class="badge badge-secondary text-[9px]"><?= strtoupper($o['status'] ?? 'pending') ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../../includes/footer.php'; ?>