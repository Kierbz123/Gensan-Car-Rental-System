<?php
// modules/inventory/item-edit.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('inventory.update');

$db = Database::getInstance();
$itemId = (int) ($_GET['id'] ?? 0);
if (!$itemId)
    redirect('modules/inventory/', 'Item ID missing.', 'error');

$inv = new Inventory();
$item = $inv->getById($itemId);
if (!$item)
    redirect('modules/inventory/', 'Item not found.', 'error');

$suppliers = $db->fetchAll("SELECT supplier_id, company_name FROM suppliers WHERE is_active = 1 AND deleted_at IS NULL ORDER BY company_name");
$locations = $db->fetchAll("SELECT DISTINCT storage_location FROM parts_inventory WHERE storage_location IS NOT NULL AND storage_location != '' ORDER BY storage_location");

$pageTitle = 'Edit — ' . $item['item_name'];
$error = '';

// Pre-fill from DB (or from re-submitted POST on validation error)
$data = [
    'item_name' => $item['item_name'],
    'item_category' => $item['item_category'],
    'unit' => $item['unit'],
    'reorder_level' => $item['reorder_level'],
    'unit_cost' => $item['unit_cost'] ?? '',
    'supplier_id' => $item['supplier_id'] ?? '',
    'storage_location' => $item['storage_location'] ?? 'Main Garage',
    'notes' => $item['notes'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $data = array_merge($data, [
            'item_name' => trim($_POST['item_name'] ?? ''),
            'item_category' => $_POST['item_category'] ?? 'parts',
            'unit' => trim($_POST['unit'] ?? 'pcs'),
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

        if (!$error) {
            try {
                $db->execute(
                    "UPDATE parts_inventory
                     SET item_name        = ?,
                         item_category    = ?,
                         unit             = ?,
                         reorder_level    = ?,
                         unit_cost        = ?,
                         supplier_id      = ?,
                         storage_location = ?,
                         notes            = ?,
                         updated_at       = NOW()
                     WHERE inventory_id   = ?",
                    [
                        $data['item_name'],
                        $data['item_category'],
                        $data['unit'],
                        (float) $data['reorder_level'],
                        $data['unit_cost'] !== '' ? (float) $data['unit_cost'] : null,
                        $data['supplier_id'] !== '' ? (int) $data['supplier_id'] : null,
                        $data['storage_location'],
                        $data['notes'] ?: null,
                        $itemId,
                    ]
                );
                $_SESSION['success_message'] = 'Item updated successfully.';
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
        <h1><i data-lucide="package"
                style="width:22px;height:22px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>Edit
            Inventory Item</h1>
        <p>Update details for <strong>
                <?= htmlspecialchars($item['item_name']) ?>
            </strong> (
            <?= htmlspecialchars($item['item_code']) ?>).
        </p>
    </div>
    <div class="page-actions">
        <a href="item-view.php?id=<?= $itemId ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back
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
            <div class="form-group">
                <label for="item_name">Item Name <span style="color:var(--danger)">*</span></label>
                <input type="text" id="item_name" name="item_name" class="form-control" required
                    value="<?= htmlspecialchars($data['item_name']) ?>" placeholder="e.g. Oil Filter (Honda)">
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
            <div class="form-group">
                <label for="reorder_level">Reorder Alert Level</label>
                <input type="number" id="reorder_level" name="reorder_level" class="form-control" min="0" step="0.001"
                    value="<?= htmlspecialchars((string) $data['reorder_level']) ?>">
                <small style="color:var(--text-muted);">Alert fires when stock falls to or below this quantity.</small>
            </div>
            <div class="alert alert-info" style="margin-top: 1rem; display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; background-color: var(--primary-50, #eff6ff); border: 1px solid var(--primary-200, #bfdbfe); border-radius: var(--radius-md); color: var(--primary-800, #1e40af); font-size: 0.875rem; line-height: 1.5;">
                <i data-lucide="info" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; color: var(--primary-600, #2563eb);"></i>
                <div>
                    <strong>Stock modification disabled:</strong> Current stock (<strong><?= number_format($item['quantity_on_hand'], 3) ?> <?= htmlspecialchars($item['unit']) ?></strong>) cannot be edited directly from this form. <br>
                    To update stock levels, please use the <a href="item-view.php?id=<?= $itemId ?>" style="color: var(--primary-700, #1d4ed8); font-weight: 600; text-decoration: underline; text-underline-offset: 2px;">Manual Adjustment</a> panel on the item's details page.
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="truck"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Supplier
                &amp; Location</h2>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="unit_cost">Unit Cost (₱)</label>
                    <input type="number" id="unit_cost" name="unit_cost" class="form-control" min="0" step="0.01"
                        value="<?= htmlspecialchars((string) $data['unit_cost']) ?>" placeholder="0.00">
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
                    placeholder="e.g. Storage Room A, Tool Cabinet" list="location_list">
                <datalist id="location_list">
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc['storage_location']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control"
                    rows="2"><?= htmlspecialchars((string) $data['notes']) ?></textarea>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" style="width:16px;height:16px;"></i> Save Changes
        </button>
        <a href="item-view.php?id=<?= $itemId ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php require_once '../../includes/footer.php'; ?>