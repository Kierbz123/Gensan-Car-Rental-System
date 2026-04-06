<?php
/**
 * Generate Purchase Order from PR
 * Path: modules/procurement/po-generate.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('procurement.create');
$db = Database::getInstance();
$prId = (int) ($_GET['pr_id'] ?? 0);
if (!$prId) {
    redirect('modules/procurement/', 'PR ID missing', 'error');
}
$pr = $db->fetchOne("SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) AS requester FROM procurement_requests pr LEFT JOIN users u ON pr.requestor_id=u.user_id WHERE pr.pr_id=? AND pr.status='approved'", [$prId]);
if (!$pr) {
    redirect('modules/procurement/', 'PR not found or not approved', 'error');
}
$items = $db->fetchAll("SELECT * FROM procurement_items WHERE pr_id=?", [$prId]);
$suppliers = $db->fetchAll("SELECT supplier_id, company_name FROM suppliers WHERE is_active=1 ORDER BY company_name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } elseif (empty($_POST['supplier_id'])) {
        $errors[] = 'Supplier is required.';
    } elseif (empty($_POST['delivery_date'])) {
        $errors[] = 'Expected delivery date is required.';
    } else {
        try {
            $poNumber = 'PO-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $total = array_sum(array_column($items, 'estimated_unit_price')) * 1; // simplified
            $poId = $db->insert(
                "INSERT INTO purchase_orders (po_number,pr_id,supplier_id,delivery_date,total_amount,payment_terms,notes,created_by) VALUES (?,?,?,?,?,?,?,?)",
                [$poNumber, $prId, $_POST['supplier_id'], $_POST['delivery_date'], $total, $_POST['payment_terms'] ?? 'cod', $_POST['notes'] ?? null, $_SESSION['user_id']]
            );
            $db->execute("UPDATE procurement_requests SET status='ordered', updated_at=NOW() WHERE pr_id=?", [$prId]);
            $_SESSION['success_message'] = 'Purchase Order ' . $poNumber . ' created.';
            header('Location: pr-view.php?id=' . $prId);
            exit;
        } catch (Exception $e) {
            $errors[] = DEBUG_MODE ? $e->getMessage() : 'PO creation failed.';
        }
    }
}

$pageTitle = 'Generate PO — ' . $pr['pr_number'];
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest">
        <a href="index.php" class="text-secondary-400 hover:text-primary-600">Procurement</a><span
            class="text-secondary-200">/</span>
        <a href="pr-view.php?id=<?= $prId ?>"
            class="text-secondary-400 hover:text-primary-600"><?= htmlspecialchars($pr['pr_number']) ?></a><span
            class="text-secondary-200">/</span>
        <span class="text-primary-600">Generate PO</span>
    </div>
    <h1 class="heading mb-2">Generate Purchase Order</h1>
    <p class="text-secondary-500 font-medium mb-8">Create a PO for the approved request
        <strong><?= htmlspecialchars($pr['pr_number']) ?></strong>.
    </p>

    <?php if (!empty($errors)): ?>
        <div
            class="flex gap-3 p-4 mb-5 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700 text-xs font-bold">
            <i data-lucide="alert-circle" class="w-4 h-4"></i><?= htmlspecialchars($errors[0]) ?>
        </div><?php endif; ?>

    <!-- Items summary -->
    <div class="card mb-6">
        <h2 class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-4">Requested Items
            (<?= count($items) ?>)</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $grand = 0;
                    foreach ($items as $it):
                        $lt = ($it['quantity'] ?? 0) * ($it['estimated_unit_price'] ?? 0);
                        $grand += $lt; ?>
                        <tr>
                            <td class="font-bold text-sm"><?= htmlspecialchars($it['item_description']) ?></td>
                            <td class="text-right"><?= number_format($it['quantity'] ?? 0) ?>
                                <?= htmlspecialchars($it['unit'] ?? '') ?>
                            </td>
                            <td class="text-right"><?= formatCurrency($it['estimated_unit_price'] ?? 0) ?></td>
                            <td class="text-right font-bold text-primary-600"><?= formatCurrency($lt) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-secondary-50">
                        <td colspan="3" class="text-right font-black text-xs uppercase tracking-wide">Total</td>
                        <td class="text-right font-black text-primary-600"><?= formatCurrency($grand) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <form method="POST">
        <?= csrfField() ?>
        <div class="card flex flex-col gap-5">
            <h2 class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-1 flex items-center gap-2"><i
                    data-lucide="truck" class="w-4 h-4 text-primary-600"></i> Purchase Order Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Supplier
                        <span class="text-danger-500">*</span></label>
                    <select name="supplier_id" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                        required>
                        <option value="">— Select supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['supplier_id'] ?>"><?= htmlspecialchars($s['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Expected
                        Delivery <span class="text-danger-500">*</span></label><input type="date" name="delivery_date"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                        min="<?= date('Y-m-d') ?>" required></div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Payment
                        Terms</label>
                    <select name="payment_terms"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"><?php foreach (['cod' => 'Cash on Delivery', 'net15' => 'Net 15', 'net30' => 'Net 30', 'prepaid' => 'Prepaid'] as $v => $l): ?>
                            <option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2"><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">PO
                        Notes</label><textarea name="notes" rows="3"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none"
                        placeholder="Delivery instructions, special requirements…"></textarea></div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="btn btn-primary flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2"><i
                        data-lucide="file-check" class="w-4 h-4"></i> Issue Purchase Order</button>
                <a href="pr-view.php?id=<?= $prId ?>"
                    class="btn btn-ghost flex-1 py-4 text-xs font-bold text-center border border-secondary-100">Cancel</a>
            </div>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>