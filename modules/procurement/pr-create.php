<?php
/**
 * Create Purchase Request
 * Path: modules/procurement/pr-create.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/Supplier.php';

$authUser->requirePermission('procurement.create');

$db = Database::getInstance();
$errors = [];

$supplierObj = new Supplier();
$activeSuppliers = $supplierObj->getActiveList();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        foreach (['title', 'category', 'date_needed'] as $f) {
            if (empty(trim($_POST[$f] ?? '')))
                $errors[] = ucfirst(str_replace('_', ' ', $f)) . ' is required.';
        }

        // Server-side enum whitelist — HTML select is bypassable via curl
        $allowedCategories = ['vehicle_parts', 'office_supplies', 'tools_equipment', 'safety_gear', 'maintenance_materials', 'other'];
        if (!empty($_POST['category']) && !in_array($_POST['category'], $allowedCategories, true))
            $errors[] = 'Invalid category selected.';

        $allowedPriorities = ['low', 'normal', 'high', 'urgent'];
        if (!empty($_POST['priority']) && !in_array($_POST['priority'], $allowedPriorities, true))
            $errors[] = 'Invalid priority selected.';

        // Server-side date guard — HTML min= is bypassable via curl
        if (!empty($_POST['date_needed']) && strtotime($_POST['date_needed']) < strtotime('today'))
            $errors[] = 'Date needed must not be in the past.';
        // Validate items
        $items = [];
        foreach ($_POST['items'] ?? [] as $i => $item) {
            if (empty(trim($item['item_name'] ?? '')))
                continue;
            $items[] = [
                'item_name' => trim($item['item_name']),
                'specification' => trim($item['specification'] ?? ''),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit' => trim($item['unit'] ?? 'piece'),
                'estimated_unit_price' => max(0, (float) ($item['estimated_unit_price'] ?? 0)),
            ];
        }
        if (empty($items))
            $errors[] = 'At least one item is required.';

        if (empty($errors)) {
            try {
                $procurement = new ProcurementRequest();

                // Map form data to class expectations
                $prData = [
                    'purpose_summary' => $_POST['title'],
                    'department' => $authUser->getData()['department'] ?? 'operations',
                    'required_date' => $_POST['date_needed'],
                    'urgency' => $_POST['priority'] ?? 'medium',
                    'items' => array_map(function ($item) {
                        return [
                            'description' => $item['item_name'],
                            'specification' => $item['specification'] ?? null,
                            'quantity' => $item['quantity'],
                            'unit' => $item['unit'],
                            'estimated_unit_cost' => $item['estimated_unit_price']
                        ];
                    }, $items)
                ];

                $result = $procurement->create($prData, $authUser->getId());
                $prId = $result['pr_id'];
                $prNumber = $result['pr_number'];

                // Automatically submit for approval
                $procurement->submitForApproval($prId, $authUser->getId());

                $_SESSION['success_message'] = 'Purchase Request ' . $prNumber . ' submitted successfully.';
                header('Location: pr-view.php?id=' . $prId);
                exit;
            } catch (Exception $e) {
                error_log("PR Creation failed: " . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Create Purchase Request';
require_once '../../includes/header.php';
?>
<div>
    <div class="page-header">
        <div class="page-title">
            <h1>Create Purchase Request</h1>
            <p>Submit a new procurement request for review and approval.</p>
        </div>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Procurement
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div
            style="margin-bottom: 2rem; padding: 1rem; background: var(--danger-light); color: var(--danger); border-radius: var(--radius-md); font-weight: 500; display: flex; align-items: flex-start; gap: 0.5rem;">
            <i data-lucide="alert-circle" style="width:18px;height:18px; flex-shrink: 0; margin-top: 2px;"></i>
            <ul style="margin: 0; padding-left: 1.5rem;">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="prForm" class="needs-validation" novalidate>
        <?= csrfField() ?>
        <div class="max-w-5xl mx-auto">
            <div class="flex flex-col gap-6">

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="file-plus"
                                style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>
                            Request Details</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label>Title <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="title" class="form-control"
                                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-row" style="margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label>Category <span style="color:var(--danger)">*</span></label>
                                <select name="category" class="form-control" required>
                                    <?php foreach (['vehicle_parts' => 'Vehicle Parts', 'office_supplies' => 'Office Supplies', 'tools_equipment' => 'Tools & Equipment', 'safety_gear' => 'Safety Gear', 'maintenance_materials' => 'Maintenance Materials', 'other' => 'Other'] as $v => $l): ?>
                                        <option value="<?= $v ?>" <?= ($_POST['category'] ?? '') === $v ? 'selected' : '' ?>>
                                            <?= $l ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Priority</label>
                                <select name="priority" class="form-control">
                                    <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $v => $l): ?>
                                        <option value="<?= $v ?>" <?= ($_POST['priority'] ?? 'normal') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date Needed <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="date_needed" class="form-control" min="<?= date('Y-m-d') ?>"
                                    value="<?= htmlspecialchars($_POST['date_needed'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description / Justification</label>
                            <textarea name="description" rows="3" class="form-control"
                                style="resize:none;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="card" id="supplier-selection-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i data-lucide="truck" style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>
                            Select Products from Suppliers
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label>Browse Supplier Catalog (Optional)</label>
                            <select id="supplier_select" class="form-control" onchange="fetchSupplierProducts(this.value)">
                                <option value="">-- Choose a Supplier to view their products --</option>
                                <?php foreach ($activeSuppliers as $sup): ?>
                                    <option value="<?= $sup['supplier_id'] ?>"><?= htmlspecialchars($sup['company_name'] . ' (' . $sup['supplier_code'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="supplier-products-container" style="display:none;">
                            <h3 style="font-size: .875rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-color);">Available Products</h3>
                            <div class="table-container" style="margin-bottom:0; max-height: 400px; overflow-y: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Unit Cost</th>
                                            <th>Available Stock</th>
                                            <th style="width: 120px;">Quantity</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="supplier-products-tbody">
                                        <!-- Populated via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-6">
                    <div class="card-header">
                        <h2 class="card-title"><i data-lucide="list"
                                style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;color:var(--primary)"></i>
                            Requested Items</h2>
                        <button type="button" onclick="addItem()" class="btn btn-secondary btn-sm"
                            style="display:flex;align-items:center;">
                            <i data-lucide="plus" style="width:16px;height:16px;"></i> Add Item
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="items-container" style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <div class="item-row"
                                style="display: grid; grid-template-columns: 2fr 2fr 1fr 1fr 1.5fr auto; gap: 1rem; align-items: end; padding: 1.5rem; background: var(--bg-muted); border-radius: var(--radius-md);">
                                <div class="form-group">
                                    <label>Item Name *</label>
                                    <input type="text" name="items[0][item_name]" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Specification</label>
                                    <input type="text" name="items[0][specification]" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Qty</label>
                                    <input type="number" name="items[0][quantity]" class="form-control" value="1"
                                        min="1" style="text-align: center;">
                                </div>
                                <div class="form-group">
                                    <label>Unit</label>
                                    <select name="items[0][unit]" class="form-control">
                                        <option value="pc">pc — Piece</option>
                                        <option value="set">set — Set</option>
                                        <option value="box">box — Box</option>
                                        <option value="pair">pair — Pair</option>
                                        <option value="roll">roll — Roll</option>
                                        <option value="bag">bag — Bag</option>
                                        <option value="liter">liter — Liter</option>
                                        <option value="gallon">gallon — Gallon</option>
                                        <option value="kg">kg — Kilogram</option>
                                        <option value="gram">gram — Gram</option>
                                        <option value="meter">meter — Meter</option>
                                        <option value="hour">hour — Hour</option>
                                        <option value="lot">lot — Lot</option>
                                        <option value="other">other — Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Est. Price</label>
                                    <input type="number" name="items[0][estimated_unit_price]" class="form-control"
                                        step="0.01" min="0" value="0">
                                </div>
                                <div style="display: flex; justify-content: flex-end; padding-bottom: 0.5rem;">
                                    <button type="button" onclick="this.closest('.item-row').remove()"
                                        style="background: none; border: none; cursor: pointer; color: var(--danger); padding: 0.5rem;">
                                        <i data-lucide="trash-2" style="width:18px;height:18px;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Submit Request -->
                <div class="card bg-secondary-900 text-pure-white border-transparent">
                    <div class="card-body">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-white/10 rounded-xl"><i data-lucide="send"
                                        class="w-6 h-6 text-primary-400"></i></div>
                                <div>
                                    <p class="text-xs font-black uppercase tracking-widest text-pure-white mb-1">Submit
                                        Request</p>
                                    <p class="text-[10px] font-medium text-secondary-400 leading-relaxed">The PR will be
                                        sent to the approver queue.<br class="hidden md:block"> You will be notified
                                        once a decision is made.</p>
                                </div>
                            </div>
                            <div class="flex flex-col md:items-end gap-3 w-full md:w-auto">
                                <button type="submit"
                                    class="btn btn-primary w-full md:w-auto px-8 py-3.5 rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-2 group">
                                    <i data-lucide="send" class="w-5 h-5"></i>
                                    Submit PR
                                    <i data-lucide="arrow-right"
                                        class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                                </button>
                                <a href="index.php"
                                    class="text-center text-[10px] text-secondary-500 hover:text-secondary-300 font-bold uppercase tracking-widest transition-colors">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>
<script>
    let itemIdx = 1;
    function addItem() {
        const c = document.getElementById('items-container');
        const i = itemIdx++;
        c.insertAdjacentHTML('beforeend', `
        <div class="item-row" style="display: grid; grid-template-columns: 2fr 2fr 1fr 1fr 1.5fr auto; gap: 1rem; align-items: end; padding: 1.5rem; background: var(--bg-muted); border-radius: var(--radius-md);">
            <div class="form-group"><label>Item Name *</label><input type="text" name="items[${i}][item_name]" class="form-control" required></div>
            <div class="form-group"><label>Specification</label><input type="text" name="items[${i}][specification]" class="form-control"></div>
            <div class="form-group"><label>Qty</label><input type="number" name="items[${i}][quantity]" class="form-control" value="1" min="1" style="text-align: center;"></div>
            <div class="form-group"><label>Unit</label><select name="items[${i}][unit]" class="form-control"><option value="pc">pc — Piece</option><option value="set">set — Set</option><option value="box">box — Box</option><option value="pair">pair — Pair</option><option value="roll">roll — Roll</option><option value="bag">bag — Bag</option><option value="liter">liter — Liter</option><option value="gallon">gallon — Gallon</option><option value="kg">kg — Kilogram</option><option value="gram">gram — Gram</option><option value="meter">meter — Meter</option><option value="hour">hour — Hour</option><option value="lot">lot — Lot</option><option value="other">other — Other</option></select></div>
            <div class="form-group"><label>Est. Price</label><input type="number" name="items[${i}][estimated_unit_price]" class="form-control" step="0.01" min="0" value="0"></div>
            <div style="display: flex; justify-content: flex-end; padding-bottom: 0.5rem;"><button type="button" onclick="this.closest('.item-row').remove()" style="background: none; border: none; cursor: pointer; color: var(--danger); padding: 0.5rem;"><i data-lucide="trash-2" style="width:18px;height:18px;"></i></button></div>
        </div>`);
        lucide.createIcons();
    }
    lucide.createIcons();

    // Supplier Integration JS
    function fetchSupplierProducts(supplierId) {
        const container = document.getElementById('supplier-products-container');
        const tbody = document.getElementById('supplier-products-tbody');
        
        if (!supplierId) {
            container.style.display = 'none';
            tbody.innerHTML = '';
            return;
        }
        
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;"><div class="spinner" style="width:24px;height:24px;border:3px solid var(--border-color);border-top-color:var(--primary);border-radius:50%;animation:spin 1s linear infinite;margin:0 auto;"></div><p style="margin-top:10px;color:var(--text-muted);font-size:.875rem;">Loading products...</p></td></tr>';
        container.style.display = 'block';
        
        fetch(`../../api/v1/procurement/get-supplier-items.php?supplier_id=${supplierId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderSupplierProducts(data.data);
                } else {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--danger);">${data.error || 'Failed to load products.'}</td></tr>`;
                }
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--danger);">Error connecting to server.</td></tr>`;
                console.error(err);
            });
    }
    
    function renderSupplierProducts(products) {
        const tbody = document.getElementById('supplier-products-tbody');
        if (!products || products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">This supplier has no mapped products in inventory.</td></tr>';
            return;
        }
        
        let html = '';
        products.forEach(p => {
            const stockColor = p.quantity_on_hand <= p.reorder_level ? 'var(--danger)' : 'var(--text-success)';
            html += `
                <tr>
                    <td style="font-weight:600;">${p.item_name}</td>
                    <td><span class="badge badge-secondary" style="text-transform:capitalize;">${p.item_category.replace(/_/g, ' ')}</span></td>
                    <td style="font-weight:500;">₱${Number(p.unit_cost || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                    <td style="color:${stockColor}; font-weight:600;">${p.quantity_on_hand} ${p.unit}</td>
                    <td>
                        <input type="number" id="sup-qty-${p.inventory_id}" class="form-control form-control-sm" value="1" min="1" style="width: 80px; text-align:center;">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" onclick='addSupplierProductToRequest(${JSON.stringify(p).replace(/'/g, "&#39;")})' style="display:flex;align-items:center;gap:4px;">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        lucide.createIcons();
    }
    
    function addSupplierProductToRequest(product) {
        const qtyInput = document.getElementById(`sup-qty-${product.inventory_id}`);
        const qty = qtyInput ? qtyInput.value : 1;
        
        const c = document.getElementById('items-container');
        const i = itemIdx++;
        
        const spec = `Supplier Item Code: ${product.item_code} | Cat: ${product.item_category}`;
        
        const newRowHTML = `
        <div class="item-row" style="display: grid; grid-template-columns: 2fr 2fr 1fr 1fr 1.5fr auto; gap: 1rem; align-items: end; padding: 1.5rem; background: var(--primary-50); border-radius: var(--radius-md); border: 1px solid var(--primary-100); transition: all 0.3s; animation: highlightRow 1.5s ease;">
            <div class="form-group"><label>Item Name *</label><input type="text" name="items[${i}][item_name]" class="form-control" value="${product.item_name.replace(/"/g, '&quot;')}" required readonly style="background:var(--bg-muted);"></div>
            <div class="form-group"><label>Specification</label><input type="text" name="items[${i}][specification]" class="form-control" value="${spec.replace(/"/g, '&quot;')}"></div>
            <div class="form-group"><label>Qty</label><input type="number" name="items[${i}][quantity]" class="form-control" value="${qty}" min="1" style="text-align: center;"></div>
            <div class="form-group"><label>Unit</label>
                <select name="items[${i}][unit]" class="form-control">
                    <option value="${product.unit}" selected>${product.unit}</option>
                    <option value="pc">pc — Piece</option>
                    <option value="set">set — Set</option>
                    <option value="box">box — Box</option>
                </select>
            </div>
            <div class="form-group"><label>Est. Price</label><input type="number" name="items[${i}][estimated_unit_price]" class="form-control" step="0.01" min="0" value="${product.unit_cost || 0}"></div>
            <div style="display: flex; justify-content: flex-end; padding-bottom: 0.5rem;"><button type="button" onclick="this.closest('.item-row').remove()" style="background: none; border: none; cursor: pointer; color: var(--danger); padding: 0.5rem;"><i data-lucide="trash-2" style="width:18px;height:18px;"></i></button></div>
        </div>
        `;
        
        c.insertAdjacentHTML('beforeend', newRowHTML);
        lucide.createIcons();
        
        showToast(`Added ${qty} ${product.unit} of ${product.item_name} to request.`);
        
        if(qtyInput) qtyInput.value = 1;
    }
    
    function showToast(msg) {
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:2rem;right:2rem;z-index:9999;display:flex;align-items:center;gap:.75rem;background:var(--success);color:#fff;padding:.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-size:.9rem;font-weight:600;min-width:280px;animation:slideUp 0.3s ease;';
        toast.innerHTML = `<i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i><span>${msg}</span>`;
        document.body.appendChild(toast);
        lucide.createIcons();
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>
<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
@keyframes highlightRow { 0% { box-shadow: 0 0 0 2px var(--primary); } 100% { box-shadow: none; } }
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
<?php require_once '../../includes/footer.php'; ?>