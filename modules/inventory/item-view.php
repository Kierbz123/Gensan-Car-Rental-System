<?php
// modules/inventory/item-view.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('inventory.view');

$itemId = (int) ($_GET['id'] ?? 0);
if (!$itemId)
    redirect('modules/inventory/', 'Item ID missing', 'error');

$inv = new Inventory();
$item = $inv->getById($itemId);
if (!$item)
    redirect('modules/inventory/', 'Item not found', 'error');

$txns = $inv->getTransactions($itemId, 30);

$pageTitle = $item['item_name'] . ' — Inventory';
$error = '';
$successMsg = '';

if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle manual adjustment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_qty'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $delta = (float) ($_POST['delta'] ?? 0);
        $notes = trim($_POST['adj_notes'] ?? '');
        if ($delta == 0) {
            $error = 'Adjustment quantity cannot be zero.';
        } else {
            try {
                $inv->adjust($itemId, $delta, $authUser->getId(), $notes ?: 'Manual adjustment');
                $_SESSION['success_message'] = 'Stock adjusted successfully.';
                header("Location: item-view.php?id={$itemId}");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

require_once '../../includes/header.php';

$isLow = $item['reorder_level'] > 0 && $item['quantity_on_hand'] <= $item['reorder_level'];
$onOrder = $inv->getOnOrderQuantity($itemId);
$CAT_COLORS = ['parts' => 'primary', 'supplies' => 'info', 'fuel' => 'warning', 'others' => 'secondary'];
$catColor = $CAT_COLORS[$item['item_category']] ?? 'secondary';
$TXN_ICONS = ['receipt' => 'arrow-down-circle', 'consumption' => 'arrow-up-circle', 'adjustment' => 'sliders', 'write_off' => 'trash-2'];
$TXN_COLORS = ['receipt' => 'success', 'consumption' => 'danger', 'adjustment' => 'warning', 'write_off' => 'secondary'];
$TXN_LABELS = ['receipt' => 'Receipt', 'consumption' => 'Consumption', 'adjustment' => 'Adjustment', 'write_off' => 'Write-off'];
?>

<?php if ($successMsg) echo renderToast($successMsg, 'success', 'toast-inv'); ?>
<?php if ($error): ?>
    <div
        style="margin-bottom:1.5rem;padding:1rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-weight:500;display:flex;align-items:center;gap:.5rem;">
        <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="package"
                style="width:22px;height:22px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>
            <?= htmlspecialchars($item['item_name']) ?>
        </h1>
        <p>
            <span class="badge badge-<?= $catColor ?>"><?= htmlspecialchars(ucfirst($item['item_category'])) ?></span>
            &nbsp;<?= htmlspecialchars($item['item_code']) ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back
        </a>
        <?php if ($authUser->hasPermission('inventory.update')): ?>
            <a href="item-edit.php?id=<?= $itemId ?>" class="btn btn-primary">
                <i data-lucide="pencil" style="width:16px;height:16px;"></i> Edit Item
            </a>
        <?php endif; ?>
        <?php if ($authUser->hasPermission('procurement.create')): ?>
            <a href="../procurement/pr-create.php?item_id=<?= $itemId ?>" class="btn btn-warning" style="background-color: var(--warning-light); color: var(--warning-dark); border-color: var(--warning);">
                <i data-lucide="shopping-cart" style="width:16px;height:16px;"></i> Order Stock
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid-layout grid-layout-320">

    <!-- Left: info + adjust -->
    <div style="display:flex;flex-direction:column;gap:1.5rem;">
        <div class="card" style="<?= $isLow ? 'border-top:4px solid var(--warning);' : '' ?>">
            <div class="card-body">
                <div style="text-align:center;padding:1.5rem 0;">
                    <div
                        style="font-size:2.5rem;font-weight:900;color:<?= $isLow ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= number_format($item['quantity_on_hand'], 3) ?>
                    </div>
                    <div style="color:var(--text-muted);font-size:.875rem;font-weight:600;">
                        <?= htmlspecialchars($item['unit']) ?> on hand
                    </div>
                    <?php if ($onOrder > 0): ?>
                        <div style="margin-top:0.4rem;color:var(--warning-dark);font-size:.85rem;font-weight:700;">
                            <i data-lucide="truck" style="width:14px;height:14px;vertical-align:-2px;"></i> 
                            +<?= number_format($onOrder, 3) ?> <?= htmlspecialchars($item['unit']) ?> on order
                        </div>
                    <?php endif; ?>
                    <?php if ($isLow): ?>
                        <div style="margin-top:.5rem;">
                            <span class="badge badge-warning"><i data-lucide="alert-triangle"
                                    style="width:12px;height:12px;"></i> Low Stock</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php 
                $suppHtml = '—';
                if (!empty($item['supplier_id'])) {
                    $suppHtml = '<a href="../suppliers/supplier-view.php?id=' . $item['supplier_id'] . '" style="color:var(--primary);text-decoration:none;font-weight:700;">' . htmlspecialchars($item['supplier_name']) . '</a>';
                    if (!empty($item['supplier_code'])) {
                        $suppHtml .= ' <span style="font-size:0.75rem;color:var(--text-muted);font-weight:normal;">(' . htmlspecialchars($item['supplier_code']) . ')</span>';
                    }
                }
                
                $meta = [
                    ['layers', 'Category', htmlspecialchars(ucfirst($item['item_category'])), false],
                    ['refresh-cw', 'Reorder At', htmlspecialchars(number_format($item['reorder_level'], 3) . ' ' . $item['unit']), false],
                    ['tag', 'Unit Cost', $authUser->hasPermission('procurement.view') ? htmlspecialchars($item['unit_cost'] ? CURRENCY_SYMBOL . number_format($item['unit_cost'], 2) : '—') : '<span style="color:var(--text-muted);font-size:0.8rem;">Restricted</span>', true],
                    ['truck', 'Supplier', $suppHtml, true],
                    ['map-pin', 'Location', htmlspecialchars($item['storage_location'] ?? '—'), false],
                ];
                foreach ($meta as [$icon, $lbl, $val, $isRaw]): ?>
                    <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:.8rem;font-size:.875rem;">
                        <i data-lucide="<?= $icon ?>"
                            style="width:15px;height:15px;color:var(--primary);flex-shrink:0;"></i>
                        <span style="color:var(--text-muted);min-width:80px;">
                            <?= $lbl ?>
                        </span>
                        <span style="font-weight:600;">
                            <?= $isRaw ? $val : $val ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Manual Adjustment Form -->
        <?php if ($authUser->hasPermission('inventory.update')): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title" style="font-size:.95rem;"><i data-lucide="sliders"
                            style="width:15px;height:15px;margin-right:5px;vertical-align:-2px;color:var(--primary)"></i>Manual
                        Adjustment</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="adjust_qty" value="1">
                        <div class="form-group">
                            <label for="delta">Quantity (+add / -remove)</label>
                            <input type="number" id="delta" name="delta" class="form-control" step="0.001" required
                                placeholder="+5 or -2">
                        </div>
                        <div class="form-group" style="margin-bottom:1rem;">
                            <label for="adj_notes">Reason</label>
                            <input type="text" id="adj_notes" name="adj_notes" class="form-control"
                                placeholder="Stock count correction, etc.">
                        </div>
                        <button type="submit" class="btn btn-secondary" style="width:100%;">
                            <i data-lucide="save" style="width:15px;height:15px;"></i> Apply Adjustment
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Transaction ledger -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="list"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Transaction
                History (last 30)</h2>
        </div>
        <div class="table-container" style="border:none;margin-bottom:0;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Balance After</th>
                        <th>Reference</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($txns)): ?>
                        <?= renderEmptyState('No transactions yet.', 'list') ?>
                    <?php else:
                        foreach ($txns as $t): ?>
                            <tr>
                                <td style="font-size:.82rem;color:var(--text-muted);">
                                    <?= date('M d, Y H:i', strtotime($t['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $TXN_COLORS[$t['txn_type']] ?? 'secondary' ?>">
                                        <i data-lucide="<?= $TXN_ICONS[$t['txn_type']] ?? 'circle' ?>"
                                            style="width:11px;height:11px;margin-right:3px;"></i>
                                        <?= htmlspecialchars($TXN_LABELS[$t['txn_type']] ?? ucfirst(str_replace('_', '-', $t['txn_type']))) ?>
                                    </span>
                                </td>
                                <td
                                    style="font-weight:700;color:<?= $t['quantity'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                    <?= $t['quantity'] > 0 ? '+' : '' ?>
                                    <?= number_format($t['quantity'], 3) ?>
                                    <?= htmlspecialchars($item['unit']) ?>
                                </td>
                                <td style="font-weight:600;">
                                    <?= number_format($t['balance_after'], 3) ?>
                                    <?= htmlspecialchars($item['unit']) ?>
                                </td>
                                <td style="font-size:.8rem;color:var(--text-muted);">
                                    <?php if ($t['reference_type'] && $t['reference_id']): ?>
                                        <span class="badge badge-secondary">
                                            <?= ucfirst($t['reference_type']) ?> #
                                            <?= $t['reference_id'] ?>
                                        </span>
                                    <?php else: ?>—
                                    <?php endif; ?>
                                    <?php if ($t['notes']): ?><br><em style="font-size:.78rem;">
                                            <?= htmlspecialchars($t['notes']) ?>
                                        </em>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.82rem;">
                                    <?= htmlspecialchars($t['recorded_by'] ?? '—') ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>