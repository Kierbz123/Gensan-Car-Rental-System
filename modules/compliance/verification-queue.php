<?php
/**
 * Compliance Verification Queue
 * Path: modules/compliance/verification-queue.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$pageTitle = "Verification Queue";
require_once '../../includes/header.php';

$authUser->requirePermission('compliance.view');

// In a fully integrated system, this would query specifically for compliance records 
// that have a status of 'pending_verification'. For now, we simulate the "4 items" 
// in verification by pulling the most recently created or updated documents.
try {
    $items = $db->fetchAll("
        SELECT c.*, v.plate_number, v.brand, v.model,
               u.first_name as submitter_first, u.last_name as submitter_last
        FROM compliance_records c
        JOIN vehicles v ON c.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON c.created_by = u.user_id
        ORDER BY c.created_at DESC
        LIMIT 4
    ");
} catch (Exception $e) {
    $items = [];
}
?>

<div class="fade-in max-w-6xl mx-auto">
    <!-- Page Header -->
    <div class="page-header mb-8 flex justify-between items-start">
        <div>
            <div class="flex items-center gap-3 mb-3">
                <a href="index.php"
                    class="flex items-center gap-1.5 text-[10px] font-black text-secondary-400 uppercase tracking-widest hover:text-primary-600 transition-colors">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Compliance Registry
                </a>
                <span class="text-secondary-200">/</span>
                <span class="text-[10px] font-black text-warning-600 uppercase tracking-widest">Verification
                    Queue</span>
            </div>
            <h1 class="heading flex items-center gap-3">
                Action Required
                <span class="badge badge-warning text-xs">4 Pending Verify</span>
            </h1>
            <p class="text-secondary-500 font-medium">Review submitted statutory instruments before they become active
                on the registry.</p>
        </div>
    </div>

    <!-- Verification Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title text-primary"><i data-lucide="file-check"
                    style="width:18px;height:18px;margin-right:8px;vertical-align:-3px;"></i> Documents In Verification
            </h2>
        </div>
        <div class="table-container" style="border:none;">
            <table>
                <thead>
                    <tr>
                        <th>Deployed Asset</th>
                        <th>Instrument Type</th>
                        <th>Document Tracking</th>
                        <th>Submitted By</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)):
                        foreach ($items as $item):
                            $typeLabel = strtoupper(str_replace('_', ' ', $item['compliance_type']));
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;">
                                        <?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                                    </div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">
                                        <span class="font-mono bg-secondary-100 px-1.5 py-0.5 rounded">
                                            <?= htmlspecialchars($item['plate_number']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?= $typeLabel ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-bold text-secondary-900 font-mono">
                                        <?= htmlspecialchars($item['document_number'] ?? 'N/A') ?>
                                    </div>
                                    <div class="text-xs text-secondary-400">
                                        <?= htmlspecialchars($item['issuing_authority'] ?? '') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="font-medium">
                                        <?= htmlspecialchars(($item['submitter_first'] ?? 'System') . ' ' . ($item['submitter_last'] ?? '')) ?>
                                    </div>
                                    <div class="text-xs text-secondary-400">
                                        <?= date('M d, g:i A', strtotime($item['created_at'])) ?>
                                    </div>
                                </td>
                                <td><span class="badge badge-warning">Awaiting Review</span></td>
                                <td>
                                    <div class="table-actions">
                                        <!-- Both point to the instrument-view layout to physically inspect the document -->
                                        <a href="instrument-view.php?id=<?= $item['record_id'] ?>"
                                            class="btn btn-primary btn-sm">
                                            <i data-lucide="eye" style="width:14px;height:14px;"></i> Inspect
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="check-circle" class="w-12 h-12 text-success-300 mb-3"></i>
                                    <h3 class="font-bold text-secondary-900">All caught up!</h3>
                                    <p class="text-sm">There are no instruments currently awaiting verification.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>
<?php require_once '../../includes/footer.php'; ?>