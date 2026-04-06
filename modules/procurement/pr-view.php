<?php
/**
 * PR View — Purchase Request Detail
 * Path: modules/procurement/pr-view.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('procurement.view');

$db = Database::getInstance();
$prId = (int) ($_GET['id'] ?? 0);
if (!$prId) {
    redirect('../../modules/procurement/index.php', 'PR ID missing.', 'error');
}

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

try {
    $pr = $db->fetchOne(
        "SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) AS requester_name
         FROM procurement_requests pr
         LEFT JOIN users u ON pr.requestor_id = u.user_id
         WHERE pr.pr_id = ?",
        [$prId]
    );

    if (!$pr) {
        redirect('../../modules/procurement/index.php', 'Purchase Request not found.', 'error');
    }

    $items = $db->fetchAll(
        "SELECT * FROM procurement_items WHERE pr_id = ? ORDER BY line_number",
        [$prId]
    );

    // Inventory items for the delivery dropdown
    $invItems = $db->fetchAll(
        "SELECT inventory_id, item_code, item_name, unit FROM parts_inventory ORDER BY item_name"
    );

} catch (Exception $e) {
    error_log($e->getMessage());
    redirect('../../modules/procurement/index.php', 'Error loading PR.', 'error');
}

$pageTitle = 'PR Detail — ' . $pr['pr_number'];
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Purchase Request Detail</h1>
        <p>Viewing requisition <?= htmlspecialchars($pr['pr_number']) ?> — itemized breakdown.</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to PR Hub
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Request Summary</h2>
        <span
            class="badge badge-<?= $pr['status'] === 'approved' ? 'success' : ($pr['status'] === 'rejected' ? 'danger' : ($pr['status'] === 'pending_approval' ? 'warning' : 'secondary')) ?>"><?= strtoupper(str_replace('_', ' ', $pr['status'])) ?></span>
    </div>
    <div class="card-body">
        <table class="detail-table">
            <tr>
                <th>PR Number</th>
                <td><?= htmlspecialchars($pr['pr_number']) ?></td>
            </tr>
            <tr>
                <th>Requester</th>
                <td><?= htmlspecialchars($pr['requester_name']) ?> (<?= ucfirst($pr['department']) ?>)</td>
            </tr>
            <tr>
                <th>Request Date</th>
                <td><?= date('M d, Y', strtotime($pr['request_date'])) ?></td>
            </tr>
            <tr>
                <th>Required Date</th>
                <td><?= date('M d, Y', strtotime($pr['required_date'])) ?></td>
            </tr>
            <tr>
                <th>Urgency</th>
                <td><?= ucfirst($pr['urgency']) ?></td>
            </tr>
            <tr>
                <th>Purpose</th>
                <td><?= htmlspecialchars($pr['purpose_summary'] ?? 'N/A') ?></td>
            </tr>
    </div>
</div>

<?php
$hasRejection = $pr['status'] === 'rejected' && !empty($pr['rejection_reason']);
$hasApprNotes = !empty($pr['approval_notes_level1']) || !empty($pr['approval_notes_level2']) || !empty($pr['approval_notes_level3']);
if ($hasRejection || $hasApprNotes):
?>
<div class="card" style="border-top: 3px solid <?= $hasRejection ? 'var(--danger)' : 'var(--success)' ?>;">
    <div class="card-header">
        <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <i data-lucide="<?= $hasRejection ? 'x-circle' : 'shield-check' ?>"
               style="width: 16px; height: 16px; color: <?= $hasRejection ? 'var(--danger)' : 'var(--success)' ?>;"></i>
            Approval &amp; Decision Trail
        </h2>
    </div>
    <div class="card-body" style="display: flex; flex-direction: column; gap: var(--space-4);">

        <?php if ($hasRejection): ?>
            <div style="padding: var(--space-4); background: var(--danger-light); border: 1px solid var(--danger); border-radius: var(--radius-md);">
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--danger); margin-bottom: var(--space-2); display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i> Rejection Reason
                </div>
                <p style="margin: 0; color: var(--danger); font-weight: 600; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($pr['rejection_reason'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <?php
        $approvalLevels = [
            1 => ['notes' => $pr['approval_notes_level1'] ?? null, 'label' => 'Level 1 — Supervisor'],
            2 => ['notes' => $pr['approval_notes_level2'] ?? null, 'label' => 'Level 2 — Manager'],
            3 => ['notes' => $pr['approval_notes_level3'] ?? null, 'label' => 'Level 3 — Owner'],
        ];
        foreach ($approvalLevels as $level => $data):
            if (empty($data['notes'])) continue;
        ?>
            <div style="padding: var(--space-4); background: var(--bg-muted); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: var(--space-2); display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="message-square" style="width: 14px; height: 14px;"></i>
                    Approval Note — <?= htmlspecialchars($data['label']) ?>
                </div>
                <p style="margin: 0; color: var(--text-secondary); line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($data['notes'])) ?>
                </p>
            </div>
        <?php endforeach; ?>

    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Line Items</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Vehicle</th>
                    <th>Qty</th>
                    <th>Est. Unit Cost</th>
                    <th>Est. Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $grandTotal = 0;
                if (empty($items)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:var(--space-8);color:var(--text-muted);">No items
                            found for this request.</td>
                    </tr>
                <?php else:
                    foreach ($items as $item):
                        $grandTotal += $item['estimated_total_cost']; ?>
                        <tr>
                            <td><?= $item['line_number'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($item['item_description']) ?></strong>
                                <?php if ($item['specification']): ?><br><small
                                        style="color:var(--text-muted);"><?= htmlspecialchars($item['specification']) ?></small><?php endif; ?>
                            </td>
                            <td><?= ucfirst($item['item_category']) ?></td>
                            <td><?= $item['vehicle_id'] ?: '—' ?></td>
                            <td><?= number_format($item['quantity'], 2) ?>         <?= htmlspecialchars($item['unit']) ?></td>
                            <td>₱<?= number_format($item['estimated_unit_cost'], 2) ?></td>
                            <td>₱<?= number_format($item['estimated_total_cost'], 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--bg-muted); font-weight:700;">
                    <td colspan="6"
                        style="text-align:right; padding:var(--space-4) var(--space-6); border-top:1px solid var(--border-color);">
                        GRAND TOTAL:</td>
                    <td style="padding:var(--space-4) var(--space-6); border-top:1px solid var(--border-color);">
                        ₱<?= number_format($grandTotal, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if ($pr['notes']): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Internal Notes</h2>
        </div>
        <div class="card-body">
            <?= nl2br(htmlspecialchars($pr['notes'])) ?>
        </div>
    </div>
<?php endif; ?>

<?php
$canReceive = in_array($pr['status'], ['ordered', 'partially_received'])
    && isset($authUser)
    && $authUser->hasPermission('procurement.receive');
if ($canReceive):
    ?>
    <div class="card" id="delivery-card" style="margin-top:1.5rem;">
        <div class="card-header" style="display:flex;align-items:center;gap:.5rem;">
            <h2 class="card-title" style="flex:1;">
                <i data-lucide="package-check"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>
                Record Delivery
            </h2>
            <span class="badge badge-warning"><?= strtoupper(str_replace('_', ' ', $pr['status'])) ?></span>
        </div>
        <div class="card-body">
            <p style="color:var(--text-muted);font-size:.875rem;margin-bottom:1rem;">Enter quantities received for each item
                and optionally link to an inventory record to update stock levels automatically.</p>
            <div id="delivery-warnings" style="display:none;margin-bottom:1rem;"></div>
            <div class="table-container" style="margin-bottom:1rem;">
                <table id="delivery-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th style="text-align:right;">Ordered</th>
                            <th style="text-align:right;">Received</th>
                            <th style="text-align:right;">Receiving Now</th>
                            <th>Inventory Item</th>
                            <th>Actual Unit Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it):
                            $remaining = max(0, ($it['quantity'] ?? 0) - ($it['quantity_received'] ?? 0));
                            if (($it['status'] ?? '') === 'fully_received')
                                continue;
                            ?>
                            <tr data-item-id="<?= $it['item_id'] ?>">
                                <td><?= $it['line_number'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($it['item_description']) ?></strong>
                                    <?php if ($it['specification']): ?><br><small
                                            style="color:var(--text-muted);"><?= htmlspecialchars($it['specification']) ?></small><?php endif; ?>
                                </td>
                                <td style="text-align:right;"><?= number_format($it['quantity'], 2) ?>
                                    <?= htmlspecialchars($it['unit'] ?? '') ?></td>
                                <td style="text-align:right;"><?= number_format($it['quantity_received'] ?? 0, 2) ?>
                                    <?= htmlspecialchars($it['unit'] ?? '') ?></td>
                                <td style="text-align:right;width:110px;">
                                    <input type="number" class="form-control delivery-qty" step="0.001" min="0"
                                        max="<?= $remaining ?>" value="<?= $remaining ?>"
                                        style="text-align:right;padding:.35rem .5rem;" />
                                </td>
                                <td style="min-width:200px;">
                                    <select class="form-control delivery-inv-id" style="padding:.35rem .5rem;">
                                        <option value="">— No inventory link —</option>
                                        <?php foreach ($invItems as $inv):
                                            // Auto-suggest: check if names loosely match
                                            $autoMatch = (stripos($inv['item_name'], $it['item_description']) !== false)
                                                || (stripos($it['item_description'], $inv['item_name']) !== false);
                                            ?>
                                            <option value="<?= $inv['inventory_id'] ?>" <?= $autoMatch ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($inv['item_code'] . ' — ' . $inv['item_name'] . ' (' . $inv['unit'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td style="width:120px;">
                                    <input type="number" class="form-control delivery-unit-cost" step="0.01" min="0"
                                        placeholder="<?= number_format($it['estimated_unit_cost'] ?? 0, 2) ?>"
                                        style="padding:.35rem .5rem;" />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <button id="btn-record-delivery" class="btn btn-primary" style="display:flex;align-items:center;gap:.5rem;">
                    <i data-lucide="save" style="width:15px;height:15px;"></i> Submit Delivery
                </button>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('btn-record-delivery').addEventListener('click', async function () {
            const btn = this;
            const rows = document.querySelectorAll('#delivery-table tbody tr[data-item-id]');
            const deliveries = [];
            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('.delivery-qty').value) || 0;
                if (qty <= 0) return;
                deliveries.push({
                    item_id: parseInt(row.dataset.itemId),
                    qty_received: qty,
                    inventory_id: row.querySelector('.delivery-inv-id').value || null,
                    unit_cost: row.querySelector('.delivery-unit-cost').value || null
                });
            });
            if (!deliveries.length) {
                alert('Please enter at least one quantity > 0.');
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Saving…';
            try {
                const resp = await fetch('ajax/receive-delivery.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrf_token: <?= json_encode(getCsrfToken()) ?>, pr_id: <?= $prId ?>, deliveries })
                });
                const data = await resp.json();
                const warnBox = document.getElementById('delivery-warnings');
                const warns = (data.results || []).filter(r => r.warning).map(r => r.warning);
                if (warns.length) {
                    warnBox.style.display = '';
                    warnBox.innerHTML = '<div style="padding:.75rem 1rem;background:var(--warning-light,#fef9c3);color:#854d0e;border-radius:8px;font-size:.875rem;"><strong>Warnings:</strong><ul style="margin:.5rem 0 0 1rem;">' + warns.map(w => `<li>${w}</li>`).join('') + '</ul></div>';
                } else {
                    warnBox.style.display = 'none';
                }
                if (data.success) {
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert(data.message || 'Delivery failed.');
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="save" style="width:15px;height:15px;"></i> Submit Delivery';
                    if (window.lucide) lucide.createIcons();
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Submit Delivery';
            }
        });
    </script>
<?php endif; ?>

<div class="page-actions" style="margin-top: var(--space-8);">
    <?php if ($pr['status'] === 'draft' && $pr['requestor_id'] == $_SESSION['user_id']): ?>
        <a href="pr-submit.php?id=<?= $prId ?>" class="btn btn-primary">Submit for Approval</a>
        <a href="pr-edit.php?id=<?= $prId ?>" class="btn btn-secondary">Edit Draft</a>
    <?php endif; ?>

    <?php
    $procurement = new ProcurementRequest();
    if ($pr['status'] === 'pending_approval' && $authUser->hasPermission('procurement.approve') && $procurement->canUserApprove($prId, $_SESSION['user_id'])):
        ?>
        <a href="pr-approve.php?id=<?= $prId ?>" class="btn btn-primary">Approve PR</a>
        <a href="pr-reject.php?id=<?= $prId ?>" class="btn btn-danger">Reject PR</a>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?>
    <div id="pr-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($successMsg) ?></span>
        <button onclick="document.getElementById('pr-toast').remove()"
            style="background:none;border:none;cursor:pointer;color:#fff;padding:0;margin:0;display:flex;align-items:center;opacity:0.8;"
            aria-label="Dismiss">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    </div>
    <style>
        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(60px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }
    </style>
    <script>
        setTimeout(function () {
            var t = document.getElementById('pr-toast');
            if (t) {
                t.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                setTimeout(function () { if (t) t.remove(); }, 400);
            }
        }, 3500);
    </script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>