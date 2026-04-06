<?php
/**
 * Add Supplier
 * Path: modules/procurement/suppliers/supplier-add.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
$authUser->requirePermission('procurement.create');
$db = Database::getInstance();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } elseif (empty(trim($_POST['company_name'] ?? ''))) {
        $errors[] = 'Company name required.';
    } else {
        try {
            $db->insert(
                "INSERT INTO suppliers (company_name,contact_person,email,phone,address,categories,payment_terms,notes,is_active) VALUES (?,?,?,?,?,?,?,?,1)",
                [$_POST['company_name'], $_POST['contact_person'] ?? null, $_POST['email'] ?? null, $_POST['phone'] ?? null, $_POST['address'] ?? null, $_POST['categories'] ?? null, $_POST['payment_terms'] ?? 'cod', $_POST['notes'] ?? null]
            );
            $_SESSION['success_message'] = 'Supplier added.';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$pageTitle = 'Add Supplier';
require_once '../../../includes/header.php';
?>
<div class="fade-in max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest"><a href="index.php"
            class="text-secondary-400 hover:text-primary-600">Suppliers</a><span
            class="text-secondary-200">/</span><span class="text-primary-600">Add</span></div>
    <h1 class="heading mb-2">Add Supplier</h1>
    <p class="text-secondary-500 font-medium mb-8">Register a new vendor or supplier.</p>
    <?php if (!empty($errors)): ?>
        <div
            class="flex gap-3 p-4 mb-5 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700 text-xs font-bold">
            <i data-lucide="alert-circle" class="w-4 h-4"></i><?= htmlspecialchars($errors[0]) ?></div><?php endif; ?>
    <form method="POST"><<?= csrfField() ?>
            <div class="card flex flex-col gap-5">
                <div class="grid grid-cols-2 gap-5">
                    <div class="col-span-2"><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Company
                            Name <span class="text-danger-500">*</span></label><input type="text" name="company_name"
                            class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                            value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required></div>
                    <div><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Contact
                            Person</label><input type="text" name="contact_person"
                            class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                            value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>"></div>
                    <div><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Phone</label><input
                            type="text" name="phone" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
                    <div><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Email</label><input
                            type="email" name="email" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
                    <div><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Payment
                            Terms</label><select name="payment_terms"
                            class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"><?php foreach (['cod' => 'COD', 'net15' => 'Net 15', 'net30' => 'Net 30', 'prepaid' => 'Prepaid'] as $v => $l): ?>
                                <option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-span-2"><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Address</label><textarea
                            name="address" rows="2"
                            class="form-input w-full rounded-2xl py-3 bg-secondary-50 resize-none"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-span-2"><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Categories
                            Supplied</label><input type="text" name="categories"
                            class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                            placeholder="e.g. Vehicle Parts, Lubricants"
                            value="<?= htmlspecialchars($_POST['categories'] ?? '') ?>"></div>
                    <div class="col-span-2"><label
                            class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Notes</label><textarea
                            name="notes" rows="2"
                            class="form-input w-full rounded-2xl py-3 bg-secondary-50 resize-none"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="flex gap-3"><button type="submit"
                        class="btn btn-primary flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2"><i
                            data-lucide="save" class="w-4 h-4"></i> Add Supplier</button><a href="index.php"
                        class="btn btn-ghost flex-1 py-4 text-xs font-bold text-center border border-secondary-100">Cancel</a>
                </div>
            </div>
    </form>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../../includes/footer.php'; ?>