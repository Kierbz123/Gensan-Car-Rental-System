<?php
/**
 * PR Submit Handler
 * Path: modules/procurement/pr-submit.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$prId = (int) ($_GET['id'] ?? 0);
if (!$prId) {
    redirect('modules/procurement/', 'Missing ID', 'error');
}

try {
    $procurement = new ProcurementRequest();
    $procurement->submitForApproval($prId, $_SESSION['user_id']);

    redirect('modules/procurement/pr-view.php?id=' . $prId, 'Purchase Request submitted for approval.', 'success');
} catch (Exception $e) {
    redirect('modules/procurement/pr-view.php?id=' . $prId, $e->getMessage(), 'error');
}
