<?php
// modules/suppliers/supplier-view.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('suppliers.view');

$supplierId = (int) ($_GET['id'] ?? 0);
if (!$supplierId) redirect('modules/suppliers/', 'Supplier ID missing', 'error');

$supplierObj = new Supplier();
$s = $supplierObj->getById($supplierId);
if (!$s) redirect('modules/suppliers/', 'Supplier not found', 'error');

$history = $supplierObj->getProcurementHistory($supplierId, 15);

$pageTitle = $s['company_name'] . ' — Supplier Profile';
require_once '../../includes/header.php';

$successMsg = '';
if (!empty($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$CATEGORY_LABELS = [
    'auto_parts' => 'Auto Parts', 'maintenance_supplies' => 'Maintenance',
    'fuel' => 'Fuel', 'tires' => 'Tires', 'carwash_supplies' => 'Carwash',
    'insurance' => 'Insurance', 'registration_services' => 'Registration', 'others' => 'Others',
];
$catLabel  = $CATEGORY_LABELS[$s['category']] ?? $s['category'];
?>

<?php if ($successMsg): ?>
    <div id="toast-sup"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:var(--success);color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;max-width:380px;">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span><?= htmlspecialchars($successMsg) ?></span>
    </div>
    <script>setTimeout(() => { document.getElementById('toast-sup')?.remove(); }, 3500);</script>
<?php endif; ?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="truck"
                style="width:22px;height:22px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>
            <?= htmlspecialchars($s['company_name']) ?>
        </h1>
        <p><?= htmlspecialchars($s['supplier_code']) ?></p>
    </div>
    <div class="page-actions">
        <?php if ($authUser->hasPermission('suppliers.update')): ?>
            <a href="supplier-edit.php?id=<?= $supplierId ?>" class="btn btn-primary">
                <i data-lucide="pencil" style="width:16px;height:16px;"></i> Edit
            </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> All Suppliers
        </a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;align-items:start;">

    <!-- Left Panel: Company Info -->
    <div style="display:flex;flex-direction:column;gap:1.5rem;">
        <div class="card">
            <div class="card-body" style="text-align:center;padding-top:2rem;">
                <div
                    style="width:80px;height:80px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <i data-lucide="building-2" style="width:38px;height:38px;color:var(--primary)"></i>
                </div>
                <h2 style="margin:0 0 .25rem;"><?= htmlspecialchars($s['company_name']) ?></h2>
                <p style="color:var(--text-muted);margin:0 0 .5rem;"><?= htmlspecialchars($s['supplier_code']) ?></p>
                <div style="display:flex;justify-content:center;gap:.5rem;margin-bottom:.5rem;">
                    <span class="badge badge-<?= $s['is_active'] ? 'success' : 'danger' ?>"
                        style="font-size:.875rem;padding:.35rem .8rem;">
                        <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                    <span class="badge badge-secondary" style="font-size:.875rem;padding:.35rem .8rem;">
                        <?= htmlspecialchars($catLabel) ?>
                    </span>
                </div>
            </div>
            <div style="border-top:1px solid var(--border-color);padding:1.25rem;">
                <?php $info = [
                    ['user',       'Contact',   $s['contact_person'] ?? '—'],
                    ['briefcase',  'Position',  $s['position'] ?? '—'],
                    ['phone',      'Phone',     $s['phone_primary'] ?? '—'],
                    ['phone',      'Phone 2',   $s['phone_secondary'] ?: '—'],
                    ['mail',       'Email',     $s['email'] ?? '—'],
                    ['globe',      'Website',   $s['website'] ?? '—'],
                    ['map-pin',    'Address',   implode(', ', array_filter([$s['address'], $s['city'], $s['province']]))],
                ];
                foreach ($info as [$icon, $label, $val]): ?>
                    <div
                        style="display:flex;align-items:center;gap:.65rem;margin-bottom:.85rem;font-size:.875rem;">
                        <i data-lucide="<?= $icon ?>"
                            style="width:16px;height:16px;color:var(--primary);flex-shrink:0;"></i>
                        <span style="color:var(--text-muted);min-width:70px;"><?= $label ?></span>
                        <span style="font-weight:600;word-break:break-all;"><?= htmlspecialchars((string) $val) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Business Details Card -->
        <div class="card">
            <div class="card-body">
                <h3 style="margin:0 0 1rem;font-size:.95rem;display:flex;align-items:center;gap:.5rem;">
                    <i data-lucide="receipt" style="width:16px;height:16px;color:var(--primary)"></i> Business
                    Details
                </h3>
                <table style="width:100%;font-size:.875rem;border-collapse:collapse;">
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0;">Business Type</td>
                        <td style="font-weight:700;"><?= ucwords(str_replace('_', ' ', $s['business_type'] ?? '—')) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0;">TIN</td>
                        <td><?= htmlspecialchars($s['tax_id'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0;">Payment Terms</td>
                        <td><?= htmlspecialchars($s['payment_terms'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0;">Credit Limit</td>
                        <td><?= $s['credit_limit'] ? CURRENCY_SYMBOL . number_format($s['credit_limit'], 2) : '—' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0;">Lead Time</td>
                        <td><?= (int) $s['lead_time_days'] ?> day<?= (int) $s['lead_time_days'] !== 1 ? 's' : '' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0;">Accredited</td>
                        <td><span
                                class="badge badge-<?= $s['is_accredited'] ? 'success' : 'secondary' ?>"><?= $s['is_accredited'] ? 'Yes' : 'No' ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0;">Linked Items</td>
                        <td><strong><?= (int) $s['linked_items'] ?></strong> inventory items</td>
                    </tr>
                    <tr>
                        <td style="color:var(--text-muted);padding:.3rem 0;">Total Orders</td>
                        <td><strong><?= (int) $s['total_orders'] ?></strong> purchase requests</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Panel: Procurement History -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="history"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Procurement
                History</h2>
        </div>
        <div class="table-container" style="border:none;margin-bottom:0;">
            <table>
                <thead>
                    <tr>
                        <th>PR Number</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Est. Cost</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">No
                                procurement history yet.</td>
                        </tr>
                    <?php else:
                        foreach ($history as $h):
                            $STAT_COLORS = [
                                'pending' => 'secondary', 'ordered' => 'primary',
                                'partially_received' => 'warning', 'fully_received' => 'success',
                                'cancelled' => 'danger',
                            ];
                            ?>
                            <tr>
                                <td>
                                    <strong
                                        style="color:var(--primary);"><?= htmlspecialchars($h['pr_number']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($h['item_description']) ?></td>
                                <td><?= number_format($h['quantity'], 2) ?> <?= htmlspecialchars($h['unit']) ?></td>
                                <td><?= CURRENCY_SYMBOL ?><?= number_format($h['estimated_total_cost'], 2) ?></td>
                                <td style="font-size:.8rem;"><?= date('M d, Y', strtotime($h['request_date'])) ?></td>
                                <td><span
                                        class="badge badge-<?= $STAT_COLORS[$h['status']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_', ' ', $h['status'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($s['notes'])): ?>
    <div class="card" style="margin-top:1.5rem;">
        <div class="card-body">
            <strong
                style="font-size:.8rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em;">Notes</strong>
            <p style="margin:.5rem 0 0;"><?= nl2br(htmlspecialchars($s['notes'])) ?></p>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
