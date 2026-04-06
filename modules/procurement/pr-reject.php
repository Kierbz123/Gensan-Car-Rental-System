<?php
/**
 * Reject Purchase Request
 * Path: modules/procurement/pr-reject.php
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
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } elseif (empty(trim($_POST['rejection_reason'] ?? ''))) {
        $errors[] = 'Rejection reason is required.';
    } else {
        try {
            $procurement = new ProcurementRequest();
            $procurement->processApproval($prId, $_SESSION['user_id'], 'reject', trim($_POST['rejection_reason']));

            $_SESSION['success_message'] = 'PR rejected.';
            header('Location: pr-view.php?id=' . $prId);
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
$pageTitle = 'Reject PR';
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php" class="text-secondary-400 hover:text-primary-600 transition-colors whitespace-nowrap">Procurement</a>
        <span class="text-secondary-200">/</span>
        <a href="pr-view.php?id=<?= $prId ?>"
            class="text-secondary-400 hover:text-primary-600 transition-colors break-words"><?= htmlspecialchars($pr['pr_number']) ?></a>
        <span class="text-secondary-200">/</span>
        <span class="text-danger-600 break-words">Reject</span>
    </div>

    <div class="card border-t-4 border-danger-500">
        <div class="card-body">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-14 h-14 bg-danger-50 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="shield-x" aria-hidden="true" class="lucide lucide-shield-x w-7 h-7 text-danger-600"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m14.5 9.5-5 5"></path><path d="m9.5 9.5 5 5"></path></svg>
                </div>
                <div>
                    <h1 class="heading text-xl">Reject Purchase Request</h1>
                    <p class="text-secondary-500 font-medium text-sm"><?= htmlspecialchars($pr['pr_number']) ?> —
                        <?= htmlspecialchars($pr['purpose_summary'] ?? 'Purchase Request') ?>
                    </p>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="text-xs text-danger-600 font-bold p-3 mb-5 bg-danger-50 rounded-xl">
                    <?= htmlspecialchars($errors[0]) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>
                <div class="mb-5">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-2">Rejection Reason <span class="text-danger-500">*</span></label>
                    <textarea name="rejection_reason" rows="4" class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none" placeholder="Explain why this request is being rejected…" required></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="btn btn-danger flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-lucide="x" aria-hidden="true" class="lucide lucide-x w-4 h-4"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg> Confirm Rejection
                    </button>
                    <a href="pr-view.php?id=<?= $prId ?>" class="btn btn-ghost flex-1 py-4 font-bold text-xs text-center border border-secondary-100">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>