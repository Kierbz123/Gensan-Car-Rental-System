<?php
/**
 * Approve Purchase Request
 * Path: modules/procurement/pr-approve.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$authUser->requirePermission('procurement.approve');
$db = Database::getInstance();
$prId = (int) ($_GET['id'] ?? 0);
if (!$prId) {
    redirect('modules/procurement/', 'PR ID missing', 'error');
}
$pr = $db->fetchOne("SELECT * FROM procurement_requests WHERE pr_id=? AND status=?", [$prId, PR_STATUS_PENDING]);
if (!$pr) {
    redirect('modules/procurement/', 'PR not found or not pending approval', 'error');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    try {
        $procurement = new ProcurementRequest();
        $procurement->processApproval($prId, $_SESSION['user_id'], 'approve', trim($_POST['notes'] ?? ''));

        $_SESSION['success_message'] = 'PR approval processed.';
        header('Location: pr-view.php?id=' . $prId);
        exit;
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}
$pageTitle = 'Approve PR';
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest">
        <a href="index.php" class="text-secondary-400 hover:text-primary-600">Procurement</a><span
            class="text-secondary-200">/</span>
        <a href="pr-view.php?id=<?= $prId ?>"
            class="text-secondary-400 hover:text-primary-600"><?= htmlspecialchars($pr['pr_number']) ?></a><span
            class="text-secondary-200">/</span>
        <span class="text-success-600">Approve</span>
    </div>
    <div class="card border-t-4 border-success-500">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-14 h-14 bg-success-50 rounded-2xl flex items-center justify-center"><i
                        data-lucide="shield-check" class="w-7 h-7 text-success-600"></i></div>
                <div>
                    <h1 class="heading text-xl">Approve Purchase Request</h1>
                    <p class="text-secondary-500 font-medium text-sm"><?= htmlspecialchars($pr['pr_number']) ?> —
                        <?= htmlspecialchars($pr['purpose_summary'] ?? 'Purchase Request') ?>
                    </p>
                </div>
            </div>
            <?php if (isset($err)): ?>
                <div class="text-xs text-danger-600 font-bold p-3 mb-4 bg-danger-50 rounded-xl">
                    <?= htmlspecialchars($err) ?>
                </div><?php endif; ?>
            <form method="POST">
                <?= csrfField() ?>
                <div class="mb-5"><label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Approval
                        Notes (optional)</label><textarea name="notes" rows="4"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none"
                        placeholder="Any conditions or comments for this approval…"></textarea></div>
                <div class="flex gap-3">
                    <button type="submit"
                        class="btn btn-success flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2"><i
                            data-lucide="check" class="w-4 h-4"></i> Confirm Approval</button>
                    <a href="pr-view.php?id=<?= $prId ?>"
                        class="btn btn-ghost flex-1 py-4 font-bold text-xs text-center border border-secondary-100">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>