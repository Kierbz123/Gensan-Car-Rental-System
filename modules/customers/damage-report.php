<?php
/**
 * Damage Report — Customer Module
 * Path: modules/customers/damage-report.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('customers.update');
$db = Database::getInstance();
$rentalId = (int) ($_GET['rental_id'] ?? 0);
if (!$rentalId) {
    redirect('modules/customers/', 'Rental ID missing', 'error');
}
$rental = $db->fetchOne(
    "SELECT ra.*, v.plate_number, v.brand, v.model, CONCAT(c.first_name,' ',c.last_name) AS customer_name
     FROM rental_agreements ra JOIN vehicles v ON ra.vehicle_id=v.vehicle_id JOIN customers c ON ra.customer_id=c.customer_id
     WHERE ra.agreement_id=?",
    [$rentalId]
);
if (!$rental) {
    redirect('modules/customers/', 'Rental not found', 'error');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } elseif (empty(trim($_POST['description'] ?? ''))) {
        $errors[] = 'Damage description is required.';
    } else {
        try {
            $db->insert(
                "INSERT INTO damage_reports (rental_id,vehicle_id,customer_id,description,repair_cost,reported_by) VALUES (?,?,?,?,?,?)",
                [$rentalId, $rental['vehicle_id'], $rental['customer_id'], $_POST['description'], (float) ($_POST['repair_cost'] ?? 0), $_SESSION['user_id']]
            );
            if (!empty($_POST['repair_cost'])) {
                $db->execute("UPDATE rental_agreements SET damage_fee=? WHERE agreement_id=?", [(float) $_POST['repair_cost'], $rentalId]);
            }
            $_SESSION['success_message'] = 'Damage report submitted.';
            header('Location: ../rentals/view.php?id=' . $rentalId);
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$pageTitle = 'Damage Report';
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest">
        <a href="../rentals/index.php" class="text-secondary-400 hover:text-primary-600">Rentals</a><span
            class="text-secondary-200">/</span>
        <a href="../rentals/view.php?id=<?= $rentalId ?>"
            class="text-secondary-400 hover:text-primary-600"><?= htmlspecialchars($rental['agreement_number']) ?></a><span
            class="text-secondary-200">/</span>
        <span class="text-danger-600">Damage Report</span>
    </div>
    <div class="card mb-6 border-t-4 border-danger-500 bg-danger-50">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-danger-100 rounded-xl"><i data-lucide="car-crash" class="w-6 h-6 text-danger-600"></i>
            </div>
            <div>
                <h1 class="text-xl font-black text-secondary-900">Damage Report</h1>
                <p class="text-sm text-secondary-500">
                    <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model'] . ' (' . $rental['plate_number'] . ')') ?> ·
                    <?= htmlspecialchars($rental['customer_name']) ?></p>
            </div>
        </div>
    </div>
    <?php if (!empty($errors)): ?>
        <div
            class="flex gap-3 p-4 mb-5 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700 text-xs font-bold">
            <i data-lucide="alert-circle" class="w-4 h-4"></i><?= htmlspecialchars($errors[0]) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data"><?= csrfField() ?>
        <div class="card flex flex-col gap-5">
            <div><label class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Damage
                    Description <span class="text-danger-500">*</span></label><textarea name="description" rows="5"
                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none"
                    placeholder="Describe the damage in detail: location, severity, type…"
                    required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
            <div class="grid grid-cols-2 gap-5">
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Estimated
                        Repair Cost (<?= CURRENCY_SYMBOL ?>)</label><input type="number" name="repair_cost"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold" step="0.01" min="0"
                        value="<?= htmlspecialchars($_POST['repair_cost'] ?? '0') ?>"></div>
                <div><label class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Photo
                        Evidence</label><input type="file" name="photo" accept="image/*"
                        class="form-input w-full rounded-2xl py-3 bg-secondary-50 text-sm"></div>
            </div>
            <div class="flex gap-3">
                <button type="submit"
                    class="btn btn-danger flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2"><i
                        data-lucide="file-warning" class="w-4 h-4"></i> Submit Damage Report</button>
                <a href="../rentals/view.php?id=<?= $rentalId ?>"
                    class="btn btn-ghost flex-1 py-4 text-xs font-bold text-center border border-secondary-100">Cancel</a>
            </div>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>
