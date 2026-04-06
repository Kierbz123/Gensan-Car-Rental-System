<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$pageTitle = 'Add Inventory Item';
$authUser->requirePermission('inventory.create');

$db = Database::getInstance();
$suppliers = $db->fetchAll("SELECT supplier_id, company_name FROM suppliers WHERE is_active = 1 AND deleted_at IS NULL ORDER BY company_name");

$error = '';
$data = [
    'item_code' => '',
    'item_name' => '',
    'item_category' => 'parts',
    'unit' => 'pcs',
    'quantity_on_hand' => '0',
    'reorder_level' => '0',
    'unit_cost' => '',
    'supplier_id' => '',
    'storage_location' => 'Main Garage',
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $data = array_merge($data, [
            'item_code' => trim($_POST['item_code'] ?? ''),
            'item_name' => trim($_POST['item_name'] ?? ''),
            'item_category' => $_POST['item_category'] ?? 'parts',
            'unit' => trim($_POST['unit'] ?? 'pcs'),
            'quantity_on_hand' => $_POST['quantity_on_hand'] ?? '0',
            'reorder_level' => $_POST['reorder_level'] ?? '0',
            'unit_cost' => $_POST['unit_cost'] ?? '',
            'supplier_id' => $_POST['supplier_id'] ?? '',
            'storage_location' => trim($_POST['storage_location'] ?? 'Main Garage'),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        if (empty($data['item_name']))
            $error = 'Item name is required.';
        elseif (empty($data['unit']))
            $error = 'Unit is required.';

        // Check if item_code is unique (assuming Inventory class has this check, or we do it here)
        if (!$error && !empty($data['item_code'])) {
            $existing = $db->fetchOne("SELECT inventory_id FROM parts_inventory WHERE item_code = ?", [$data['item_code']]);
            if ($existing) {
                $error = 'Item Code / SKU already exists. Please use a different code.';
            }
        }

        if (!$error) {
            try {
                $inv = new Inventory();
                $itemId = $inv->create($data, $authUser->getId());
                $_SESSION['success_message'] = 'Inventory item added successfully.';
                header("Location: item-view.php?id={$itemId}");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="package-plus"
                style="width:22px;height:22px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>Add
            Inventory Item</h1>
        <p>Register a new part or supply in the stock ledger.</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Inventory
        </a>
    </div>
</div>

<form method="POST" style="max-width:720px;">
    <?= csrfField() ?>

    <?php if ($error): ?>
        <div
            style="margin-bottom:1.5rem;padding:1rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-weight:500;display:flex;align-items:center;gap:.5rem;">
            <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="package"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Item
                Details</h2>
        </div>
        <div class="card-body">
            <div class="form-row form-row--two">
                <div class="form-group">
                    <label for="item_code">Item Code / SKU</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="item_code" name="item_code" class="form-control" style="font-family:monospace; text-transform:uppercase;"
                            value="<?= htmlspecialchars($data['item_code']) ?>" placeholder="e.g. INV-1001">
                        <button type="button" class="btn btn-secondary" onclick="generateSKU()" style="white-space:nowrap; padding:0 12px; font-size:0.8125rem;">
                            <i data-lucide="zap" style="width:14px;height:14px;margin-right:4px;"></i> Auto
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="item_name">Item Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="item_name" name="item_name" class="form-control" required
                        value="<?= htmlspecialchars($data['item_name']) ?>" placeholder="e.g. Oil Filter (Honda)">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="item_category">Category</label>
                    <select id="item_category" name="item_category" class="form-control">
                        <?php foreach (['parts' => 'Parts', 'supplies' => 'Supplies', 'fuel' => 'Fuel', 'others' => 'Others'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $data['item_category'] === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="unit">Unit <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="unit" name="unit" class="form-control" required
                        value="<?= htmlspecialchars($data['unit']) ?>" placeholder="pcs, liters, kg…">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="quantity_on_hand">Current Stock (Opening)</label>
                    <input type="number" id="quantity_on_hand" name="quantity_on_hand" class="form-control" min="0"
                        step="0.001" value="<?= htmlspecialchars($data['quantity_on_hand']) ?>">
                </div>
                <div class="form-group">
                    <label for="reorder_level">Reorder Alert Level</label>
                    <input type="number" id="reorder_level" name="reorder_level" class="form-control" min="0"
                        step="0.001" value="<?= htmlspecialchars($data['reorder_level']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="truck"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Supplier
                & Location</h2>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="unit_cost">Unit Cost (₱)</label>
                    <input type="number" id="unit_cost" name="unit_cost" class="form-control" min="0" step="0.01"
                        value="<?= htmlspecialchars($data['unit_cost']) ?>" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label for="supplier_id">Primary Supplier</label>
                    <select id="supplier_id" name="supplier_id" class="form-control">
                        <option value="">— None —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['supplier_id'] ?>" <?= ($data['supplier_id'] == $s['supplier_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="storage_location">Storage Location</label>
                <input type="text" id="storage_location" name="storage_location" class="form-control"
                    value="<?= htmlspecialchars($data['storage_location']) ?>"
                    placeholder="e.g. Storage Room A, Tool Cabinet">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control"
                    rows="2"><?= htmlspecialchars($data['notes']) ?></textarea>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" style="width:16px;height:16px;"></i> Add to Inventory
        </button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script>
    function generateSKU() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let result = 'INV-';
        for (let i = 0; i < 6; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('item_code').value = result;
    }
    lucide.createIcons();
</script>

<?php require_once '../../includes/footer.php'; ?>