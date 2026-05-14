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
$items = $db->fetchAll(
    "SELECT pi.*, s.company_name as supplier_name 
     FROM procurement_items pi 
     LEFT JOIN suppliers s ON pi.supplier_id = s.supplier_id 
     WHERE pi.pr_id = ?",
    [$prId]
);

// Determine pre-selected supplier (if all items have the same supplier, use it)
$assignedSuppliers = array_unique(array_filter(array_column($items, 'supplier_id')));
$preSelectedSupplierId = (count($assignedSuppliers) === 1) ? $assignedSuppliers[0] : null;
$suppliers = $db->fetchAll("SELECT supplier_id, company_name FROM suppliers WHERE is_active=1 ORDER BY company_name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } elseif (empty($_POST['delivery_date'])) {
        $errors[] = 'Expected delivery date is required.';
    } else {
        try {
            $poNumber = 'PO-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $grand = 0;
            foreach ($items as $it) {
                $grand += ($it['quantity'] ?? 0) * ($it['estimated_unit_cost'] ?? 0);
            }
            $total = $grand;

            $supplierId = empty($_POST['supplier_id']) ? null : $_POST['supplier_id'];

            $db->beginTransaction();

            $poId = $db->insert(
                "INSERT INTO purchase_orders (po_number,pr_id,supplier_id,delivery_date,total_amount,payment_terms,notes,created_by) VALUES (?,?,?,?,?,?,?,?)",
                [$poNumber, $prId, $supplierId, $_POST['delivery_date'], $total, $_POST['payment_terms'] ?? 'cod', $_POST['notes'] ?? null, $_SESSION['user_id']]
            );

            $db->execute(
                "UPDATE procurement_requests SET status='ordered', po_number=?, po_generated_at=NOW(), po_generated_by=?, updated_at=NOW() WHERE pr_id=?", 
                [$poNumber, $_SESSION['user_id'], $prId]
            );

            $db->execute(
                "UPDATE procurement_items SET status='ordered', quantity_ordered=quantity, supplier_id=? WHERE pr_id=?", 
                [$supplierId, $prId]
            );

            $db->commit();

            $_SESSION['success_message'] = 'Purchase Order ' . $poNumber . ' generated successfully.';
            header('Location: pr-view.php?id=' . $prId);
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = DEBUG_MODE ? $e->getMessage() : 'PO creation failed.';
        }
    }
}

$pageTitle = 'Generate PO — ' . $pr['pr_number'];
require_once '../../includes/header.php';
?>
<div class="page-header">
    <div class="page-title">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;font-size:0.85rem;color:var(--text-muted);font-weight:600;">
            <a href="index.php" style="color:inherit;text-decoration:none;">Procurement</a>
            <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
            <a href="pr-view.php?id=<?= $prId ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars($pr['pr_number']) ?></a>
            <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
            <span style="color:var(--primary);">Generate PO</span>
        </div>
        <h1>Generate Purchase Order</h1>
        <p>Create an official Purchase Order for approved request <strong><?= htmlspecialchars($pr['pr_number']) ?></strong>.</p>
    </div>
    <div class="page-actions" style="display:flex;gap:0.5rem;align-items:center;">
        <a href="print-receipt.php?id=<?= $prId ?>" target="_blank" class="btn btn-primary" style="display:flex;align-items:center;gap:4px;">
            <i data-lucide="printer" style="width:16px;height:16px;"></i> Print Shopping List
        </a>
        <a href="pr-view.php?id=<?= $prId ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to PR
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;background:var(--danger-light);color:var(--danger-dark);padding:1rem;border-radius:var(--radius-md);">
        <i data-lucide="alert-circle" style="width:18px;height:18px;"></i>
        <strong>Error:</strong> <?= htmlspecialchars($errors[0]) ?>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start;">
    
    <!-- Items summary -->
    <div class="card">
        <div class="card-header" style="border-bottom:1px solid var(--border-color);padding:1.25rem 1.5rem;">
            <h2 style="font-size:1rem;margin:0;">Requested Items (<?= count($items) ?>)</h2>
        </div>
        <div class="table-container" style="border:none;border-radius:0 0 var(--radius-lg) var(--radius-lg);">
            <table>
                <thead>
                    <tr style="background:var(--bg-muted);">
                        <th>Item Description</th>
                        <th>Preferred Supplier</th>
                        <th style="text-align:right;">Quantity</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $grand = 0; foreach ($items as $it): 
                        $lt = ($it['quantity'] ?? 0) * ($it['estimated_unit_cost'] ?? 0);
                        $grand += $lt; 
                    ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($it['item_description']) ?></td>
                            <td style="font-size: 0.85rem; color: var(--primary-dark); font-weight: 600;">
                                <?= $it['supplier_name'] ? htmlspecialchars($it['supplier_name']) : '<span style="color:var(--text-muted);font-weight:400;">—</span>' ?>
                            </td>
                            <td style="text-align:right;color:var(--text-muted);">
                                <?= number_format($it['quantity'] ?? 0) ?> <?= htmlspecialchars($it['unit'] ?? '') ?>
                            </td>
                            <td style="text-align:right;color:var(--text-muted);">₱<?= number_format($it['estimated_unit_cost'] ?? 0, 2) ?></td>
                            <td style="text-align:right;font-weight:700;color:var(--primary-dark);">₱<?= number_format($lt, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg-muted);border-top:2px solid var(--border-color);">
                        <td colspan="4" style="text-align:right;font-weight:700;text-transform:uppercase;font-size:0.85rem;color:var(--text-muted);padding:1rem;">Grand Total</td>
                        <td style="text-align:right;font-weight:800;font-size:1.1rem;color:var(--primary-dark);padding:1rem;">₱<?= number_format($grand, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Generate Form -->
    <div class="card" style="position:sticky;top:2rem;">
        <div class="card-header" style="background:var(--bg-muted);border-bottom:1px solid var(--border-color);padding:1.25rem 1.5rem;">
            <h2 style="font-size:1rem;margin:0;display:flex;align-items:center;gap:0.5rem;">
                <i data-lucide="truck" style="width:18px;height:18px;color:var(--primary);"></i> 
                Purchase Order Details
            </h2>
        </div>
        <form method="POST" style="padding:1.5rem;">
            <?= csrfField() ?>
            
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label style="display:block;margin-bottom:0.5rem;font-weight:600;font-size:0.85rem;color:var(--text-color);">
                    Select Supplier (Optional for Ad-hoc)
                </label>
                <select name="supplier_id" class="form-control" style="width:100%;">
                    <option value="">— Select supplier —</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['supplier_id'] ?>" <?= ($preSelectedSupplierId == $s['supplier_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
                <label style="display:block;margin-bottom:0.5rem;font-weight:600;font-size:0.85rem;color:var(--text-color);">
                    Expected Delivery Date <span style="color:var(--danger);">*</span>
                </label>
                <input type="date" name="delivery_date" class="form-control" min="<?= date('Y-m-d') ?>" required style="width:100%;">
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
                <label style="display:block;margin-bottom:0.5rem;font-weight:600;font-size:0.85rem;color:var(--text-color);">
                    Payment Terms
                </label>
                <select name="payment_terms" class="form-control" style="width:100%;">
                    <?php foreach (['cod' => 'Cash on Delivery', 'net15' => 'Net 15', 'net30' => 'Net 30', 'prepaid' => 'Prepaid'] as $v => $l): ?>
                        <option value="<?= $v ?>"><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
                <label style="display:block;margin-bottom:0.5rem;font-weight:600;font-size:0.85rem;color:var(--text-color);">
                    PO Instructions / Notes
                </label>
                <textarea name="notes" rows="3" class="form-control" placeholder="Delivery instructions, special requirements…" style="width:100%;resize:vertical;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:0.75rem;font-size:0.95rem;font-weight:700;">
                <i data-lucide="file-check" style="width:18px;height:18px;"></i> Issue Purchase Order
            </button>
        </form>
    </div>

</div>
<?php require_once '../../includes/footer.php'; ?>