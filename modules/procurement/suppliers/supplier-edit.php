<?php
/**
 * Edit Supplier
 * Path: modules/procurement/suppliers/supplier-edit.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
$authUser->requirePermission('procurement.update');
$db = Database::getInstance();
$sid = (int) ($_GET['id'] ?? 0);
if (!$sid) {
    redirect('modules/procurement/suppliers/', 'Missing ID', 'error');
}
$supplier = $db->fetchOne("SELECT * FROM suppliers WHERE supplier_id=?", [$sid]);
if (!$supplier) {
    redirect('modules/procurement/suppliers/', 'Not found', 'error');
}
$errors = [];
$d = !empty($_POST) ? $_POST : $supplier;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } elseif (empty(trim($_POST['company_name'] ?? ''))) {
        $errors[] = 'Company name required.';
    } else {
        try {
            $db->execute(
                "UPDATE suppliers SET company_name=?,contact_person=?,email=?,phone=?,address=?,categories=?,payment_terms=?,notes=?,is_active=?,updated_at=NOW() WHERE supplier_id=?",
                [$_POST['company_name'], $_POST['contact_person'] ?? null, $_POST['email'] ?? null, $_POST['phone'] ?? null, $_POST['address'] ?? null, $_POST['categories'] ?? null, $_POST['payment_terms'] ?? null, $_POST['notes'] ?? null, isset($_POST['is_active']) ? 1 : 0, $sid]
            );
            $_SESSION['success_message'] = 'Supplier updated.';
            header('Location: supplier-view.php?id=' . $sid);
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$pageTitle = 'Edit Supplier — ' . $supplier['company_name'];
require_once '../../../includes/header.php';
?>
<div class="fade-in max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest"><a href="index.php"
            class="text-secondary-400 hover:text-primary-600">Suppliers</a><span class="text-secondary-200">/</span><a
            href="supplier-view.php?id=<?= $sid ?>"
            class="text-secondary-400 hover:text-primary-600"><?= htmlspecialchars($supplier['company_name']) ?></a><span
            class="text-secondary-200">/</span><span class="text-primary-600">Edit</span></div>
    <h1 class="heading mb-2">Edit Supplier</h1>
    <?php if (!empty($errors)): ?>
        <div
            class="flex gap-3 p-4 mb-5 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700 text-xs font-bold">
            <i data-lucide="alert-circle" class="w-4 h-4"></i><?= htmlspecialchars($errors[0]) ?></div><?php endif; ?>
    <form method="POST"><?= csrfField() ?>
        <div class="card flex flex-col gap-5">
            <div class="grid grid-cols-2 gap-5">
                <div class="col-span-2"><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Company
                        Name *</label><input type="text" name="company_name"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                        value="<?= htmlspecialchars($d['company_name'] ?? '') ?>" required></div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Contact
                        Person</label><input type="text" name="contact_person"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                        value="<?= htmlspecialchars($d['contact_person'] ?? '') ?>"></div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Phone</label><input
                        type="text" name="phone" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                        value="<?= htmlspecialchars($d['phone'] ?? '') ?>"></div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Email</label><input
                        type="email" name="email" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                        value="<?= htmlspecialchars($d['email'] ?? '') ?>"></div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Payment
                        Terms</label><select name="payment_terms"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"><?php foreach (['cod' => 'COD', 'net15' => 'Net 15', 'net30' => 'Net 30', 'prepaid' => 'Prepaid'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($d['payment_terms'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-span-2"><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Address</label><textarea
                        name="address" rows="2"
                        class="form-input w-full rounded-2xl py-3 bg-secondary-50 resize-none"><?= htmlspecialchars($d['address'] ?? '') ?></textarea>
                </div>
                <div class="col-span-2"><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Categories</label><input
                        type="text" name="categories" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                        value="<?= htmlspecialchars($d['categories'] ?? '') ?>"></div>
                <div class="col-span-2 flex items-center gap-3"><input type="checkbox" name="is_active" id="is_active"
                        class="w-4 h-4 accent-primary-600" <?= ($d['is_active'] ?? 1) ? 'checked' : '' ?>><label
                        for="is_active" class="text-sm font-bold text-secondary-900 cursor-pointer">Active
                        supplier</label></div>
            </div>
            <div class="flex gap-3"><button type="submit"
                    class="btn btn-primary flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2"><i
                        data-lucide="save" class="w-4 h-4"></i> Save Changes</button><a
                    href="supplier-view.php?id=<?= $sid ?>"
                    class="btn btn-ghost flex-1 py-4 text-xs font-bold text-center border border-secondary-100">Cancel</a>
            </div>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../../includes/footer.php'; ?>