<?php
/**
 * Add Service Record
 * Path: modules/maintenance/service-record-add.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('maintenance.create');
$db = Database::getInstance();
$vehicles = $db->fetchAll("SELECT vehicle_id, plate_number, brand, model FROM vehicles WHERE deleted_at IS NULL ORDER BY brand");
$invItems = $db->fetchAll("SELECT inventory_id, item_code, item_name, unit, quantity_on_hand FROM parts_inventory WHERE quantity_on_hand > 0 ORDER BY item_name");
$errors = [];
$invWarnings = [];

if (!empty($_SESSION['inv_warnings'])) {
    $invWarnings = $_SESSION['inv_warnings'];
    unset($_SESSION['inv_warnings']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        foreach (['vehicle_id', 'service_type', 'service_date', 'cost'] as $f) {
            if (empty($_POST[$f] ?? ''))
                $errors[] = ucfirst(str_replace('_', ' ', $f)) . ' required.';
        }
        if (empty($errors)) {
            try {
                $maintObj = new MaintenanceRecord();
                $logId = $maintObj->create([
                    'vehicle_id'  => $_POST['vehicle_id'],
                    'service_type' => $_POST['service_type'],
                    'service_date' => $_POST['service_date'],
                    'labor_cost'  => 0,
                    'parts_cost'  => (float) $_POST['cost'],
                    'other_costs' => 0,
                    'odometer_reading' => (int) ($_POST['mileage'] ?? 0),
                    'provider_name' => $_POST['technician'] ?? null,
                    'description' => $_POST['description'] ?? 'Manual service entry',
                    'status'      => 'completed'
                ], $_SESSION['user_id']);

                // Consume inventory parts (non-fatal)
                $invWarns = [];
                $invObj = new Inventory();
                $partIds  = $_POST['part_inv_id']  ?? [];
                $partQtys = $_POST['part_qty']     ?? [];
                foreach ($partIds as $k => $inventoryId) {
                    $inventoryId = (int) $inventoryId;
                    $qty = (float) ($partQtys[$k] ?? 0);
                    if ($inventoryId && $qty > 0) {
                        try {
                            $invObj->consume($inventoryId, $qty, $logId, $_SESSION['user_id'], 'Service record #' . $logId);
                        } catch (Exception $ie) {
                            $invWarns[] = $ie->getMessage();
                        }
                    }
                }
                if ($invWarns) {
                    $_SESSION['inv_warnings'] = $invWarns;
                }

                $_SESSION['success_message'] = 'Service record added.';
                header('Location: history.php');
                exit;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Add Service Record';
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest">
        <a href="index.php" class="text-secondary-400 hover:text-primary-600">Maintenance</a><span
            class="text-secondary-200">/</span><span class="text-primary-600">Add Service Record</span>
    </div>
    <h1 class="heading mb-2">Add Service Record</h1>
    <p class="text-secondary-500 font-medium mb-8">Log a completed maintenance or service event.</p>
    <?php if (!empty($invWarnings)): ?>
        <div class="flex gap-3 p-4 mb-5 bg-warning-50 border border-warning-100 rounded-2xl text-warning-700 text-xs font-bold">
            <i data-lucide="alert-triangle" class="w-4 h-4 flex-shrink-0"></i>
            <div>
                <div class="mb-1">Service saved, but some inventory items could not be deducted:</div>
                <ul class="list-disc ml-4"><?php foreach ($invWarnings as $w): ?><li><?= htmlspecialchars($w) ?></li><?php endforeach; ?></ul>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div
            class="flex gap-3 p-4 mb-5 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700 text-xs font-bold">
            <i data-lucide="alert-circle" class="w-4 h-4"></i><?= htmlspecialchars($errors[0]) ?>
        </div><?php endif; ?>
    <form method="POST">
        <?= csrfField() ?>
        <div class="card flex flex-col gap-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Vehicle
                        <span class="text-danger-500">*</span></label>
                    <select name="vehicle_id" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                        required>
                        <option value="">— Select —</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['vehicle_id'] ?>" <?= ($_POST['vehicle_id'] ?? '') === $v['vehicle_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['plate_number'] . ' – ' . $v['brand'] . ' ' . $v['model']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Service
                        Type <span class="text-danger-500">*</span></label>
                    <select name="service_type" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                        required>
                        <?php foreach (['oil_change' => 'Oil Change', 'tire_rotation' => 'Tire Rotation', 'brake_service' => 'Brake Service', 'engine_tune_up' => 'Engine Tune-up', 'transmission_service' => 'Transmission Service', 'battery_replacement' => 'Battery Replacement', 'air_filter' => 'Air Filter', 'general_inspection' => 'General Inspection', 'body_repair' => 'Body Repair', 'other' => 'Other'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($_POST['service_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?>
                            </option><?php endforeach; ?>
                    </select>
                </div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Service
                        Date <span class="text-danger-500">*</span></label><input type="date" name="service_date"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                        max="<?= date('Y-m-d') ?>"
                        value="<?= htmlspecialchars($_POST['service_date'] ?? date('Y-m-d')) ?>" required></div>
                <div><label class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Cost
                        (<?= CURRENCY_SYMBOL ?>) <span class="text-danger-500">*</span></label><input type="number"
                        name="cost" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold" step="0.01"
                        min="0" value="<?= htmlspecialchars($_POST['cost'] ?? '0') ?>" required></div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Mileage
                        at Service (km)</label><input type="number" name="mileage"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold" min="0"
                        value="<?= htmlspecialchars($_POST['mileage'] ?? '') ?>"></div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Technician
                        / Shop</label><input type="text" name="technician"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                        value="<?= htmlspecialchars($_POST['technician'] ?? '') ?>"></div>
                <div class="md:col-span-2"><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Description
                        / Work Done</label><textarea name="description" rows="4"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>

            <?php if (!empty($invItems)): ?>
            <!-- Parts Used from Inventory -->
            <div class="border-t border-secondary-100 pt-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-secondary-400 flex items-center gap-2">
                        <i data-lucide="package" class="w-4 h-4 text-primary-600"></i> Parts Used from Inventory
                        <span class="text-secondary-300 normal-case font-medium">(optional)</span>
                    </h3>
                    <button type="button" id="btn-add-part"
                        class="btn btn-ghost text-[10px] font-black uppercase tracking-widest border border-secondary-100 flex items-center gap-1 py-1 px-3">
                        <i data-lucide="plus" class="w-3 h-3"></i> Add Part
                    </button>
                </div>
                <div id="parts-list" class="flex flex-col gap-2"></div>
                <p id="parts-empty-msg" class="text-xs text-secondary-400 italic">No parts selected. Click "Add Part" to deduct stock from inventory.</p>
            </div>
            <?php endif; ?>

            <div class="flex gap-3">
                <button type="submit"
                    class="btn btn-primary flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2"><i
                        data-lucide="wrench" class="w-4 h-4"></i> Save Record</button>
                <a href="index.php"
                    class="btn btn-ghost flex-1 py-4 text-xs font-bold text-center border border-secondary-100">Cancel</a>
            </div>
        </div>
    </form>
</div>
<script>
lucide.createIcons();
<?php if (!empty($invItems)): ?>
const INV_ITEMS = <?= json_encode(array_map(fn($i) => [
    'id'   => $i['inventory_id'],
    'code' => $i['item_code'],
    'name' => $i['item_name'],
    'unit' => $i['unit'],
    'qty'  => $i['quantity_on_hand'],
], $invItems)) ?>;

const partsList   = document.getElementById('parts-list');
const emptyMsg    = document.getElementById('parts-empty-msg');
const btnAddPart  = document.getElementById('btn-add-part');
let   partIndex   = 0;

function renderParts() {
    emptyMsg.style.display = partsList.children.length ? 'none' : '';
    lucide.createIcons();
}

btnAddPart.addEventListener('click', () => {
    const idx = partIndex++;
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2';
    row.innerHTML = `
        <select name="part_inv_id[${idx}]" class="form-input flex-1 rounded-xl py-2 bg-secondary-50 text-sm font-bold" required>
            <option value="">— Select part —</option>
            ${INV_ITEMS.map(i => `<option value="${i.id}">${i.code} — ${i.name} (${Number(i.qty).toFixed(2)} ${i.unit} avail.)</option>`).join('')}
        </select>
        <input type="number" name="part_qty[${idx}]" step="0.001" min="0.001"
               class="form-input w-24 rounded-xl py-2 bg-secondary-50 text-sm font-bold text-right"
               placeholder="Qty" required />
        <button type="button" class="btn btn-ghost py-2 px-3 border border-secondary-100 text-danger-500 hover:text-danger-700"
                onclick="this.closest('div').remove();renderParts();" title="Remove">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;
    partsList.appendChild(row);
    renderParts();
});
<?php endif; ?>
</script>
<?php require_once '../../includes/footer.php'; ?>
