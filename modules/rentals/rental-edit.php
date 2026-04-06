<?php
/**
 * Edit Rental
 * Path: modules/rentals/rental-edit.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('rentals.update');

$db = Database::getInstance();
$rentalId = (int) ($_GET['id'] ?? 0);
if (!$rentalId) {
    redirect('modules/rentals/', 'Missing ID', 'error');
}

$rental = $db->fetchOne("SELECT * FROM rental_agreements WHERE agreement_id = ?", [$rentalId]);
if (!$rental || !in_array($rental['status'], ['reserved', 'active'])) {
    redirect('modules/rentals/', 'Rental not found or cannot be edited in this state', 'error');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        foreach (['rental_start_date', 'rental_end_date', 'rental_rate'] as $f) {
            if (empty($_POST[$f]))
                $errors[] = ucfirst(str_replace('_', ' ', $f)) . ' is required.';
        }
        if (empty($errors)) {
            try {
                $days = ceil((strtotime($_POST['rental_end_date']) - strtotime($_POST['rental_start_date'])) / 86400);
                $total = max(1, $days) * (float) $_POST['rental_rate'] + (float) ($rental['security_deposit'] ?? 0);
                $db->execute(
                    "UPDATE rental_agreements SET rental_start_date=?, rental_end_date=?, rental_rate=?,
                     pickup_location=?, return_location=?, total_amount=?, updated_at=NOW() WHERE agreement_id=?",
                    [
                        $_POST['rental_start_date'],
                        $_POST['rental_end_date'],
                        $_POST['rental_rate'],
                        $_POST['pickup_location'] ?? 'main_office',
                        $_POST['return_location'] ?? 'main_office',
                        $total,
                        $rentalId
                    ]
                );
                $_SESSION['success_message'] = 'Rental updated.';
                header('Location: view.php?id=' . $rentalId);
                exit;
            } catch (Exception $e) {
                $errors[] = DEBUG_MODE ? $e->getMessage() : 'Update failed.';
            }
        }
    }
}

$data = !empty($_POST) ? $_POST : $rental;
$pageTitle = 'Edit Rental — ' . $rental['agreement_number'];
require_once '../../includes/header.php';
?>
<div class="fade-in">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest">
        <a href="index.php" class="text-secondary-400 hover:text-primary-600">Rentals</a>
        <span class="text-secondary-200">/</span>
        <a href="view.php?id=<?= $rentalId ?>"
            class="text-secondary-400 hover:text-primary-600"><?= htmlspecialchars($rental['agreement_number']) ?></a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600">Edit</span>
    </div>
    <h1 class="heading mb-2">Modify Rental Parameters</h1>
    <p class="text-secondary-500 font-medium mb-8">Adjust active rental session configuration.</p>

    <?php if (!empty($errors)): ?>
        <div class="flex gap-3 p-5 mb-6 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700">
            <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
            <ul class="text-xs list-disc list-inside"><?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 flex flex-col gap-6">
                <div class="card">
                    <h2
                        class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4 text-primary-600"></i> Rental Period</h2>
                    <div class="grid grid-cols-2 gap-5">
                        <div><label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Start
                                Date *</label><input type="date" name="rental_start_date"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                value="<?= htmlspecialchars(substr($data['rental_start_date'] ?? '', 0, 10)) ?>" required>
                        </div>
                        <div><label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">End
                                Date *</label><input type="date" name="rental_end_date"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                                value="<?= htmlspecialchars(substr($data['rental_end_date'] ?? '', 0, 10)) ?>" required>
                        </div>
                        <div><label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Pickup
                                Location</label>
                            <select name="pickup_location"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"><?php foreach (['main_office' => 'Main Office', 'airport' => 'Airport', 'hotel_delivery' => 'Hotel Delivery', 'other' => 'Other'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($data['pickup_location'] ?? '') === $v ? 'selected' : '' ?>>
                                        <?= $l ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div><label
                                class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Return
                                Location</label>
                            <select name="return_location"
                                class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"><?php foreach (['main_office' => 'Main Office', 'airport' => 'Airport', 'hotel_pickup' => 'Hotel Pickup', 'other' => 'Other'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($data['return_location'] ?? '') === $v ? 'selected' : '' ?>>
                                        <?= $l ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <h2
                        class="text-xs font-black uppercase tracking-widest text-secondary-900 mb-6 flex items-center gap-2">
                        <i data-lucide="banknote" class="w-4 h-4 text-success-600"></i> Pricing</h2>
                    <div class="max-w-xs"><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Daily
                            Rate (<?= CURRENCY_SYMBOL ?>) *</label><input type="number" name="rental_rate"
                            class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold text-lg" step="0.01"
                            min="0" value="<?= htmlspecialchars($data['rental_rate'] ?? '') ?>" required></div>
                </div>
            </div>
            <div>
                <div class="card bg-secondary-900 text-pure-white sticky top-6">
                    <p class="text-xs font-black uppercase tracking-widest mb-5">Save Changes</p>
                    <div class="p-3.5 bg-white/5 rounded-xl mb-5 text-xs">
                        <p class="text-secondary-400 mb-1">Agreement</p>
                        <p class="font-mono font-bold text-primary-400">
                            <?= htmlspecialchars($rental['agreement_number']) ?></p>
                    </div>
                    <button type="submit"
                        class="btn btn-primary w-full py-5 rounded-2xl font-black text-[11px] uppercase tracking-widest flex items-center justify-center gap-2"><i
                            data-lucide="save" class="w-4 h-4"></i> Save Changes</button>
                    <a href="view.php?id=<?= $rentalId ?>"
                        class="block text-center mt-4 text-[10px] text-secondary-500 hover:text-secondary-300 font-bold uppercase tracking-widest transition-colors">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>
