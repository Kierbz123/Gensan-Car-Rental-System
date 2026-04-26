<?php
// modules/suppliers/supplier-edit.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('suppliers.update');

$db = Database::getInstance();
$supplierId = (int) ($_GET['id'] ?? 0);
if (!$supplierId) redirect('modules/suppliers/', 'Supplier ID missing.', 'error');

$supplierObj = new Supplier();
$item = $supplierObj->getById($supplierId);
if (!$item) redirect('modules/suppliers/', 'Supplier not found.', 'error');

$error = '';
$data = [
    'company_name'     => $item['company_name'],
    'business_type'    => $item['business_type'],
    'tax_id'           => $item['tax_id'] ?? '',
    'category'         => $item['category'],
    'address'          => $item['address'] ?? '',
    'city'             => $item['city'] ?? 'General Santos City',
    'province'         => $item['province'] ?? 'South Cotabato',
    'zip_code'         => $item['zip_code'] ?? '',
    'contact_person'   => $item['contact_person'] ?? '',
    'position'         => $item['position'] ?? '',
    'phone_primary'    => $item['phone_primary'] ?? '',
    'phone_secondary'  => $item['phone_secondary'] ?? '',
    'email'            => $item['email'] ?? '',
    'website'          => $item['website'] ?? '',
    'payment_terms'    => $item['payment_terms'] ?? '',
    'credit_limit'     => $item['credit_limit'] ?? '',
    'lead_time_days'   => $item['lead_time_days'] ?? '1',
    'is_accredited'    => $item['is_accredited'] ?? 0,
    'accreditation_date' => $item['accreditation_date'] ?? '',
    'is_active'        => $item['is_active'] ?? 1,
    'notes'            => $item['notes'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            // Delete Action Handler
            if (!$authUser->hasPermission('suppliers.delete')) {
                $error = 'You do not have permission to delete this supplier.';
            } else {
                try {
                    $supplierObj->delete($supplierId, $authUser->getData()['user_id']);
                    redirect('modules/suppliers/', 'Supplier deleted successfully.', 'success');
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        } else {
            // Update Action Handler
            $data = array_merge($data, array_map(function ($v) { return is_string($v) ? trim($v) : $v; }, $_POST));
            $data['is_accredited'] = isset($_POST['is_accredited']) ? 1 : 0;
            $data['is_active']     = isset($_POST['is_active']) ? 1 : 0;

        if (empty($data['company_name'])) $error = 'Company name is required.';
        elseif (empty($data['phone_primary'])) $error = 'Primary phone is required.';

        if (!$error) {
            try {
                $supplierObj->update($supplierId, $data, $authUser->getData()['user_id']);
                $_SESSION['success_message'] = 'Supplier updated successfully.';
                header("Location: supplier-view.php?id={$supplierId}");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        }
    }
}

$pageTitle = 'Edit — ' . $item['company_name'];
require_once '../../includes/header.php';

$CATEGORIES = [
    'auto_parts' => 'Auto Parts', 'maintenance_supplies' => 'Maintenance Supplies',
    'fuel' => 'Fuel', 'tires' => 'Tires', 'carwash_supplies' => 'Carwash Supplies',
    'insurance' => 'Insurance', 'registration_services' => 'Registration Services', 'others' => 'Others',
];
$BIZ_TYPES = [
    'corporation' => 'Corporation', 'partnership' => 'Partnership',
    'sole_proprietor' => 'Sole Proprietor', 'cooperative' => 'Cooperative',
];
?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="truck"
                style="width:22px;height:22px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>Edit
            Supplier</h1>
        <p>Update details for <strong><?= htmlspecialchars($item['company_name']) ?></strong>
            (<?= htmlspecialchars($item['supplier_code']) ?>).</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back
        </a>
    </div>
</div>

<form method="POST">
    <?= csrfField() ?>

    <?php if ($error): ?>
        <div
            style="margin-bottom:1.5rem;padding:1rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-weight:500;display:flex;align-items:center;gap:.5rem;">
            <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
        
        <!-- Column 1: Company Details -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <div class="card" style="height: 100%;">
                <div class="card-header">
                    <h2 class="card-title"><i data-lucide="building-2"
                            style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Company
                        Details</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="company_name">Company Name <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="company_name" name="company_name" class="form-control" required
                            value="<?= htmlspecialchars($data['company_name']) ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="business_type">Business Type</label>
                            <select id="business_type" name="business_type" class="form-control">
                                <?php foreach ($BIZ_TYPES as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $data['business_type'] === $v ? 'selected' : '' ?>><?= $l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <?php foreach ($CATEGORIES as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $data['category'] === $v ? 'selected' : '' ?>><?= $l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="tax_id">TIN / Tax ID</label>
                        <input type="text" id="tax_id" name="tax_id" class="form-control"
                            value="<?= htmlspecialchars($data['tax_id']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-control"
                            value="<?= htmlspecialchars($data['address']) ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" class="form-control"
                                value="<?= htmlspecialchars($data['city']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="province">Province</label>
                            <input type="text" id="province" name="province" class="form-control"
                                value="<?= htmlspecialchars($data['province']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Column 2: Contact & Terms -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <div class="card" style="height: 100%;">
                <div class="card-header">
                    <h2 class="card-title"><i data-lucide="contact"
                            style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Contact
                        & Terms</h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" class="form-control"
                                value="<?= htmlspecialchars($data['contact_person']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" id="position" name="position" class="form-control"
                                value="<?= htmlspecialchars($data['position']) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone_primary">Primary Phone <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="phone_primary" name="phone_primary" class="form-control" required
                                value="<?= htmlspecialchars($data['phone_primary']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone_secondary">Secondary Phone</label>
                            <input type="text" id="phone_secondary" name="phone_secondary" class="form-control"
                                value="<?= htmlspecialchars($data['phone_secondary']) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control"
                                value="<?= htmlspecialchars($data['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" class="form-control"
                                value="<?= htmlspecialchars($data['website']) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="payment_terms">Payment Terms</label>
                            <input type="text" id="payment_terms" name="payment_terms" class="form-control"
                                value="<?= htmlspecialchars($data['payment_terms']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="credit_limit">Credit Limit (₱)</label>
                            <input type="number" id="credit_limit" name="credit_limit" class="form-control" min="0"
                                step="0.01" value="<?= htmlspecialchars($data['credit_limit']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="lead_time_days">Avg. Lead Time (days)</label>
                        <input type="number" id="lead_time_days" name="lead_time_days" class="form-control" min="0"
                            value="<?= htmlspecialchars($data['lead_time_days']) ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                                <input type="checkbox" name="is_accredited" value="1"
                                    <?= $data['is_accredited'] ? 'checked' : '' ?> style="width:16px;height:16px;">
                                Accredited Vendor
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1"
                                    <?= $data['is_active'] ? 'checked' : '' ?> style="width:16px;height:16px;">
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control"
                            rows="2"><?= htmlspecialchars($data['notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" style="width:16px;height:16px;"></i> Save Changes
            </button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
        <?php if ($authUser->hasPermission('suppliers.delete')): ?>
        <button type="button" class="btn btn-danger" onclick="var f=this.closest('form'); openGcrModal('Delete Supplier', 'Are you sure you want to delete this supplier? This action will mark the supplier as inactive.', function() { var inp=document.createElement('input'); inp.type='hidden'; inp.name='action'; inp.value='delete'; f.appendChild(inp); f.submit(); }, { variant: 'danger', icon: 'trash-2', confirmLabel: 'Yes, Delete' });">
            <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Delete Supplier
        </button>
        <?php endif; ?>
    </div>
</form>

<!-- ══════════════════════════════════════════════════════════════════════
     PRODUCT CATALOG SECTION
═══════════════════════════════════════════════════════════════════════ -->
<div class="card" style="margin-top: 2rem;" id="products-card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2 class="card-title">
            <i data-lucide="package" style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>
            Product Catalog
        </h2>
        <button type="button" class="btn btn-primary btn-sm" onclick="openProductModal()">
            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Product
        </button>
    </div>
    <div class="table-container" style="border:none;">
        <table id="products-table">
            <thead>
                <tr>
                    <th>Item Code</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit Cost</th>
                    <th>Stock</th>
                    <th>Reorder Level</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody id="products-tbody">
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">Loading products...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Product Add/Edit Modal ─────────────────────────────────────────── -->
<div id="product-modal" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card, #ffffff); border:1px solid var(--border-color, #e2e8f0); border-radius:12px; padding:2rem; width:600px; max-width:95vw; max-height:90vh; overflow-y:auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--border-color, #f1f5f9);">
            <h3 id="product-modal-title" style="font-size:1.25rem; font-weight:700; color:var(--text-main, #0f172a); margin:0;">Add Product</h3>
            <button type="button" onclick="closeProductModal()" style="background:var(--bg-body, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:6px; cursor:pointer; padding:6px; display:flex; align-items:center; justify-content:center; transition: all 0.2s;">
                <i data-lucide="x" style="width:18px;height:18px;color:var(--text-muted, #64748b);"></i>
            </button>
        </div>
        
        <div id="product-modal-error" style="display:none; margin-bottom:1.5rem; padding:1rem; background:#fef2f2; border-left:4px solid #ef4444; color:#991b1b; border-radius:6px; font-size:0.875rem; font-weight:500;"></div>
        
        <input type="hidden" id="pm-inventory-id" value="">
        
        <div style="display:flex; flex-direction:column; gap:1.25rem;">
            <div>
                <label for="pm-item-name" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.875rem; color:var(--text-secondary, #475569);">Product Name <span style="color:#ef4444">*</span></label>
                <input type="text" id="pm-item-name" class="form-control" placeholder="e.g. Brake Pad Set (Toyota Vios)" style="width:100%; background:var(--bg-body, #fff);">
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
                <div>
                    <label for="pm-category" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.875rem; color:var(--text-secondary, #475569);">Category</label>
                    <select id="pm-category" class="form-control" style="width:100%; background:var(--bg-body, #fff);">
                        <option value="parts">Parts</option>
                        <option value="supplies">Supplies</option>
                        <option value="fuel">Fuel</option>
                        <option value="others">Others</option>
                    </select>
                </div>
                <div>
                    <label for="pm-unit" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.875rem; color:var(--text-secondary, #475569);">Unit</label>
                    <input type="text" id="pm-unit" class="form-control" placeholder="pcs, liters, set..." style="width:100%; background:var(--bg-body, #fff);">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
                <div>
                    <label for="pm-unit-cost" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.875rem; color:var(--text-secondary, #475569);">Unit Cost (₱) <span style="color:#ef4444">*</span></label>
                    <input type="number" id="pm-unit-cost" class="form-control" min="0" step="0.01" placeholder="0.00" style="width:100%; background:var(--bg-body, #fff);">
                </div>
                <div>
                    <label for="pm-qty" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.875rem; color:var(--text-secondary, #475569);">Current Stock</label>
                    <input type="number" id="pm-qty" class="form-control" min="0" step="0.001" placeholder="0" style="width:100%; background:var(--bg-body, #fff);">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
                <div>
                    <label for="pm-reorder" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.875rem; color:var(--text-secondary, #475569);">Reorder Level</label>
                    <input type="number" id="pm-reorder" class="form-control" min="0" step="0.001" placeholder="0" style="width:100%; background:var(--bg-body, #fff);">
                </div>
                <div>
                    <label for="pm-location" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.875rem; color:var(--text-secondary, #475569);">Storage Location</label>
                    <input type="text" id="pm-location" class="form-control" placeholder="Main Garage" style="width:100%; background:var(--bg-body, #fff);">
                </div>
            </div>
            
            <div>
                <label for="pm-notes" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.875rem; color:var(--text-secondary, #475569);">Notes</label>
                <textarea id="pm-notes" class="form-control" rows="2" placeholder="Optional notes about this product..." style="width:100%; background:var(--bg-body, #fff); resize:vertical;"></textarea>
            </div>
        </div>
        
        <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border-color, #f1f5f9);">
            <button type="button" onclick="closeProductModal()" class="btn btn-secondary" style="padding:0.6rem 1.25rem; font-weight:600;">Cancel</button>
            <button type="button" onclick="saveProduct()" class="btn btn-primary" id="pm-save-btn" style="padding:0.6rem 1.25rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                <i data-lucide="save" style="width:16px;height:16px;"></i> <span>Save Product</span>
            </button>
        </div>
    </div>
</div>

<script>
const SUPPLIER_ID = <?= $supplierId ?>;
const CSRF_TOKEN  = '<?= getCsrfToken() ?>';
const AJAX_URL    = 'ajax/supplier-products.php';

// ── Load products on page load ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadProducts);

function loadProducts() {
    fetch(`${AJAX_URL}?action=list&supplier_id=${SUPPLIER_ID}`)
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('products-tbody');
            if (!res.success || !res.data.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">No products listed for this supplier yet.</td></tr>';
                return;
            }
            tbody.innerHTML = res.data.map(p => {
                const isLow = parseFloat(p.quantity_on_hand) <= parseFloat(p.reorder_level);
                const stockColor = isLow ? 'color:var(--danger);' : 'color:var(--success, #10b981);';
                return `<tr>
                    <td style="font-family:monospace;font-size:0.8rem;color:var(--text-muted);">${esc(p.item_code)}</td>
                    <td style="font-weight:600;">${esc(p.item_name)}</td>
                    <td><span style="font-size:0.7rem;font-weight:800;color:var(--text-secondary);background:var(--bg-body);padding:2px 8px;border-radius:4px;border:1px solid var(--border-color);text-transform:uppercase;letter-spacing:0.05em;">${esc(p.item_category)}</span></td>
                    <td style="font-weight:500;">₱${parseFloat(p.unit_cost).toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
                    <td style="${stockColor}font-weight:600;">${parseFloat(p.quantity_on_hand).toFixed(3)} ${esc(p.unit)}</td>
                    <td style="color:var(--text-muted);">${parseFloat(p.reorder_level).toFixed(3)} ${esc(p.unit)}</td>
                    <td style="text-align:center;">
                        <button class="btn btn-ghost btn-sm" onclick="editProduct(${p.inventory_id})" title="Edit">
                            <i data-lucide="pencil" style="width:14px;height:14px;color:var(--primary);"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="deleteProduct(${p.inventory_id}, '${esc(p.item_name)}')" title="Delete">
                            <i data-lucide="trash-2" style="width:14px;height:14px;color:var(--danger);"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
}

// ── Open modal for Add ────────────────────────────────────────────────
function openProductModal(data = null) {
    document.getElementById('pm-inventory-id').value = data ? data.inventory_id : '';
    document.getElementById('pm-item-name').value    = data ? data.item_name : '';
    document.getElementById('pm-category').value     = data ? data.item_category : 'parts';
    document.getElementById('pm-unit').value         = data ? data.unit : 'pcs';
    document.getElementById('pm-unit-cost').value    = data ? data.unit_cost : '';
    document.getElementById('pm-qty').value          = data ? data.quantity_on_hand : '0';
    document.getElementById('pm-reorder').value      = data ? data.reorder_level : '0';
    document.getElementById('pm-location').value     = data ? data.storage_location : 'Main Garage';
    document.getElementById('pm-notes').value        = data ? (data.notes || '') : '';
    document.getElementById('product-modal-title').textContent = data ? 'Edit Product' : 'Add Product';
    document.getElementById('product-modal-error').style.display = 'none';
    document.getElementById('product-modal').style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeProductModal() {
    document.getElementById('product-modal').style.display = 'none';
}

// ── Edit: fetch product then open modal ───────────────────────────────
function editProduct(inventoryId) {
    fetch(`${AJAX_URL}?action=get&inventory_id=${inventoryId}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return alert(res.message);
            openProductModal(res.data);
        });
}

// ── Save (Add or Update) ──────────────────────────────────────────────
function saveProduct() {
    const inventoryId = document.getElementById('pm-inventory-id').value;
    const formData = new FormData();
    formData.append('action', inventoryId ? 'update' : 'add');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('supplier_id', SUPPLIER_ID);
    if (inventoryId) formData.append('inventory_id', inventoryId);
    formData.append('item_name', document.getElementById('pm-item-name').value);
    formData.append('item_category', document.getElementById('pm-category').value);
    formData.append('unit', document.getElementById('pm-unit').value);
    formData.append('unit_cost', document.getElementById('pm-unit-cost').value);
    formData.append('quantity_on_hand', document.getElementById('pm-qty').value);
    formData.append('reorder_level', document.getElementById('pm-reorder').value);
    formData.append('storage_location', document.getElementById('pm-location').value);
    formData.append('notes', document.getElementById('pm-notes').value);

    const btn = document.getElementById('pm-save-btn');
    btn.disabled = true; btn.textContent = 'Saving...';

    fetch(AJAX_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                const err = document.getElementById('product-modal-error');
                err.textContent = res.message;
                err.style.display = 'block';
            } else {
                closeProductModal();
                loadProducts();
            }
        })
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i data-lucide="save" style="width:14px;height:14px;"></i> Save Product'; if (typeof lucide !== 'undefined') lucide.createIcons(); });
}

// ── Delete ────────────────────────────────────────────────────────────
function deleteProduct(inventoryId, itemName) {
    if (!confirm(`Delete "${itemName}"?\nThis cannot be undone if it is not linked to any procurement requests.`)) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('inventory_id', inventoryId);
    fetch(AJAX_URL, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (!res.success) return alert(res.message);
            loadProducts();
        });
}

// ── Helper ────────────────────────────────────────────────────────────
function esc(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

// Close modal on outside click
document.getElementById('product-modal').addEventListener('click', function(e) {
    if (e.target === this) closeProductModal();
});
</script>

<?php require_once '../../includes/footer.php'; ?>

