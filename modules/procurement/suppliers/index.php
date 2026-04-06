<?php
/**
 * Supplier Directory
 * Path: modules/procurement/suppliers/index.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';
$authUser->requirePermission('procurement.view');
$db = Database::getInstance();
$search = trim($_GET['q'] ?? '');
$params = $search ? ["%$search%", "%$search%"] : [];
$sql = "SELECT * FROM suppliers WHERE 1=1" . ($search ? " AND (company_name LIKE ? OR contact_person LIKE ?)" : "") . " ORDER BY company_name";
$suppliers = $db->fetchAll($sql, $params);
$pageTitle = 'Supplier Directory';
require_once '../../../includes/header.php';
?>
<div class="fade-in">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="heading">Supplier Directory</h1>
            <p class="text-secondary-500 font-medium"><?= count($suppliers) ?> registered suppliers.</p>
        </div>
        <?php if ($authUser->hasPermission('procurement.create')): ?>
            <a href="supplier-add.php" class="btn btn-primary gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Add
                Supplier</a>
        <?php endif; ?>
    </div>
    <div class="card mb-6">
        <form method="GET" class="flex gap-3">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search suppliers…"
                class="form-input flex-1 rounded-2xl py-3 bg-secondary-50 font-bold">
            <button type="submit" class="btn btn-primary px-6 rounded-2xl font-bold gap-2"><i data-lucide="search"
                    class="w-4 h-4"></i> Search</button>
        </form>
    </div>
    <div class="card p-0 overflow-hidden">
        <div class="table-wrapper border-none">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Categories</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-16 text-secondary-400">No suppliers found.</td>
                        </tr>
                    <?php else:
                        foreach ($suppliers as $s): ?>
                            <tr class="group">
                                <td>
                                    <p class="font-bold text-secondary-900"><?= htmlspecialchars($s['company_name']) ?></p>
                                    <p class="text-xs text-secondary-400"><?= htmlspecialchars($s['email'] ?? '') ?></p>
                                </td>
                                <td>
                                    <p class="font-bold text-sm"><?= htmlspecialchars($s['contact_person'] ?? '—') ?></p>
                                    <p class="text-xs text-secondary-400"><?= htmlspecialchars($s['phone'] ?? '') ?></p>
                                </td>
                                <td class="text-xs"><?= htmlspecialchars($s['categories'] ?? '—') ?></td>
                                <td><span
                                        class="badge badge-<?= ($s['is_active'] ?? 1) ? 'success' : 'secondary' ?> text-[9px]"><?= ($s['is_active'] ?? 1) ? 'ACTIVE' : 'INACTIVE' ?></span>
                                </td>
                                <td class="text-right">
                                    <a href="supplier-view.php?id=<?= $s['supplier_id'] ?>"
                                        class="btn btn-ghost p-2 rounded-xl text-secondary-400 hover:text-primary-600"><i
                                            data-lucide="eye" class="w-4 h-4"></i></a>
                                    <a href="supplier-edit.php?id=<?= $s['supplier_id'] ?>"
                                        class="btn btn-ghost p-2 rounded-xl text-secondary-400 hover:text-primary-600"><i
                                            data-lucide="pencil" class="w-4 h-4"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../../includes/footer.php'; ?>