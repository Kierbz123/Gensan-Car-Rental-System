<?php
/**
 * Rental Agreement View (existing agreement)
 * Path: modules/customers/rental-agreement-view.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('rentals.view');
$db = Database::getInstance();
$rentalId = (int) ($_GET['id'] ?? 0);
if (!$rentalId) {
    redirect('modules/rentals/', 'Missing ID', 'error');
}
$rental = $db->fetchOne(
    "SELECT ra.*, v.plate_number, v.brand, v.model, v.year_model,
            CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.phone_primary, c.email
     FROM rental_agreements ra
     JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
     JOIN customers c ON ra.customer_id = c.customer_id
     WHERE ra.agreement_id = ?",
    [$rentalId]
);
if (!$rental) {
    redirect('modules/rentals/', 'Agreement not found', 'error');
}
$days = max(1, ceil((strtotime($rental['rental_end_date']) - strtotime($rental['rental_start_date'])) / 86400));
$pageTitle = 'Agreement — ' . $rental['agreement_number'];
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest">
        <a href="../rentals/index.php" class="text-secondary-400 hover:text-primary-600">Rentals</a><span
            class="text-secondary-200">/</span>
        <a href="../rentals/view.php?id=<?= $rentalId ?>"
            class="text-secondary-400 hover:text-primary-600"><?= htmlspecialchars($rental['agreement_number']) ?></a><span
            class="text-secondary-200">/</span>
        <span class="text-primary-600">Agreement</span>
    </div>

    <div class="flex justify-between items-center mb-8">
        <h1 class="heading">Rental Agreement</h1>
        <div class="flex gap-2">
            <a href="rental-agreement-create.php?id=<?= $rentalId ?>" target="_blank"
                class="btn btn-primary gap-2 text-xs font-bold">
                <i data-lucide="printer" class="w-4 h-4"></i> Print / Download
            </a>
        </div>
    </div>

    <!-- Agreement Card -->
    <div class="card border border-primary-100 shadow-lg">
        <div class="flex justify-between items-start mb-8 pb-6 border-b border-secondary-100">
            <div>
                <h2 class="font-black text-secondary-900 text-lg">VEHICLE RENTAL AGREEMENT</h2>
                <p class="text-xs text-secondary-400 font-medium mt-0.5">Gensan Car Rental System · General Santos City
                </p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black uppercase tracking-widest text-secondary-400">Agreement No.</p>
                <p class="font-mono font-black text-primary-600 text-lg">
                    <?= htmlspecialchars($rental['agreement_number']) ?></p>
                <span
                    class="badge badge-<?= $rental['status'] === 'active' ? 'primary' : ($rental['status'] === 'completed' || $rental['status'] === 'returned' ? 'success' : 'secondary') ?> text-[9px] mt-1"><?= strtoupper($rental['status']) ?></span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div>
                <h3 class="text-[10px] font-black uppercase tracking-widest text-primary-600 mb-4">Client / Lessee</h3>
                <div class="flex flex-col gap-2">
                    <p class="font-black text-secondary-900"><?= htmlspecialchars($rental['customer_name']) ?></p>
                    <p class="text-sm text-secondary-600"><?= htmlspecialchars($rental['phone_primary']) ?></p>
                    <p class="text-sm text-secondary-600"><?= htmlspecialchars($rental['email'] ?? '') ?></p>
                </div>
            </div>
            <div>
                <h3 class="text-[10px] font-black uppercase tracking-widest text-primary-600 mb-4">Vehicle</h3>
                <div class="flex flex-col gap-2">
                    <p class="font-black text-secondary-900">
                        <?= htmlspecialchars($rental['brand'] . ' ' . $rental['model'] . ' ' . $rental['year_model']) ?></p>
                    <p class="text-sm font-mono font-bold text-secondary-600">
                        <?= htmlspecialchars($rental['plate_number']) ?></p>
                </div>
            </div>
        </div>

        <div class="bg-secondary-50 rounded-2xl p-5 mb-8">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-4">Rental Period &
                Financials</h3>
            <div class="grid grid-cols-3 gap-4">
                <?php foreach ([['Start Date', date('M d, Y', strtotime($rental['rental_start_date']))], ['End Date', date('M d, Y', strtotime($rental['rental_end_date']))], ['Duration', $days . ' day(s)'], ['Daily Rate', formatCurrency($rental['rental_rate'])], ['Security Deposit', formatCurrency($rental['security_deposit'] ?? 0)], ['Total Amount', formatCurrency($rental['total_amount'] ?? 0)]] as [$l, $v]): ?>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-0.5"><?= $l ?></p>
                        <p class="font-bold text-secondary-900 text-sm"><?= $v ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mt-8 pt-8 border-t border-secondary-100">
            <div class="text-center">
                <div class="h-12 border-b-2 border-secondary-900 mb-2"></div>
                <p class="text-xs font-bold text-secondary-500">Lessee Signature</p>
                <p class="text-xs font-black text-secondary-900"><?= htmlspecialchars($rental['customer_name']) ?></p>
            </div>
            <div class="text-center">
                <div class="h-12 border-b-2 border-secondary-900 mb-2"></div>
                <p class="text-xs font-bold text-secondary-500">Authorized Representative</p>
                <p class="text-xs font-black text-secondary-900">Gensan Car Rental</p>
            </div>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>
