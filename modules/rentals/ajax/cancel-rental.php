<?php
/**
 * AJAX handler: Cancel a rental agreement
 * POST  modules/rentals/ajax/cancel-rental.php
 */
header('Content-Type: application/json');

require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Permission check
if (!$authUser->hasPermission('rentals.update')) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to cancel rentals.']);
    exit;
}

// CSRF validation
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh and try again.']);
    exit;
}

$agreementId = (int) ($_POST['agreement_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$agreementId) {
    echo json_encode(['success' => false, 'message' => 'Agreement ID is required.']);
    exit;
}

try {
    $ra = new RentalAgreement();
    $ra->cancel($agreementId, $authUser->getId(), $reason ?: null);

    echo json_encode(['success' => true, 'message' => 'Rental agreement cancelled successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
