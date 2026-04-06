<?php
/**
 * Edit Purchase Request
 * Path: modules/procurement/pr-edit.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('procurement.update');
$db = Database::getInstance();
$prId = (int) ($_GET['id'] ?? 0);
if (!$prId) {
    redirect('modules/procurement/', 'Missing ID', 'error');
}
$pr = $db->fetchOne("SELECT * FROM procurement_requests WHERE pr_id=? AND status='pending_approval'", [$prId]);
if (!$pr) {
    redirect('modules/procurement/', 'PR not found or cannot be edited', 'error');
}
$items = $db->fetchAll("SELECT * FROM procurement_items WHERE pr_id=? ORDER BY item_description", [$prId]);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } else {
        foreach (['title', 'category', 'date_needed'] as $f) {
            if (empty(trim($_POST[$f] ?? '')))
                $errors[] = ucfirst(str_replace('_', ' ', $f)) . ' required.';
        }
        if (empty($errors)) {
            try {
                $db->execute(
                    "UPDATE procurement_requests SET purpose_summary=?,department=?,required_date=?,urgency=?,updated_at=NOW() WHERE pr_id=?",
                    [$_POST['title'], $pr['department'], $_POST['date_needed'], $_POST['priority'] ?? 'medium', $prId]
                );
                $_SESSION['success_message'] = 'PR updated.';
                header('Location: pr-view.php?id=' . $prId);
                exit;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}
$d = !empty($_POST) ? $_POST : [
    'title' => $pr['purpose_summary'],
    'category' => $pr['category'] ?? 'vehicle_parts', // Fallback if column name differs
    'priority' => $pr['urgency'],
    'date_needed' => $pr['required_date'],
    'description' => $pr['description'] ?? ''
];
$pageTitle = 'Edit PR — ' . $pr['pr_number'];
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest">
        <a href="index.php" class="text-secondary-400 hover:text-primary-600">Procurement</a><span
            class="text-secondary-200">/</span>
        <a href="pr-view.php?id=<?= $prId ?>"
            class="text-secondary-400 hover:text-primary-600"><?= htmlspecialchars($pr['pr_number']) ?></a><span
            class="text-secondary-200">/</span>
        <span class="text-primary-600">Edit</span>
    </div>
    <h1 class="heading mb-2">Edit Purchase Request</h1>
    <p class="text-secondary-500 font-medium mb-8">Modify <?= htmlspecialchars($pr['pr_number']) ?> while it is still
        pending.</p>
    <?php if (!empty($errors)): ?>
        <div
            class="flex gap-3 p-4 mb-5 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700 text-xs font-bold">
            <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i><?= htmlspecialchars($errors[0]) ?>
        </div>
    <?php endif; ?>
    <form method="POST">
        <?= csrfField() ?>
        <div class="card flex flex-col gap-5">
            <div><label class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Title
                    *</label><input type="text" name="title"
                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 font-bold"
                    value="<?= htmlspecialchars($d['title'] ?? '') ?>" required></div>
            <div class="grid grid-cols-3 gap-4">
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Category
                        *</label><select name="category"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"><?php foreach (['vehicle_parts' => 'Vehicle Parts', 'office_supplies' => 'Office Supplies', 'tools_equipment' => 'Tools & Equipment', 'safety_gear' => 'Safety Gear', 'maintenance_materials' => 'Maintenance', 'other' => 'Other'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($d['category'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Priority</label><select
                        name="priority"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($d['priority'] ?? 'normal') === $v ? 'selected' : '' ?>><?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Date
                        Needed *</label><input type="date" name="date_needed"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50"
                        value="<?= htmlspecialchars(substr($d['date_needed'] ?? '', 0, 10)) ?>" required></div>
            </div>
            <div><label
                    class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Description</label><textarea
                    name="description" rows="4"
                    class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none"><?= htmlspecialchars($d['description'] ?? '') ?></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="btn btn-primary flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2"><i
                        data-lucide="save" class="w-4 h-4"></i> Save Changes</button>
                <a href="pr-view.php?id=<?= $prId ?>"
                    class="btn btn-ghost flex-1 py-4 text-xs font-bold text-center border border-secondary-100">Cancel</a>
            </div>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>