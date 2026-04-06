<?php
// modules/rentals/ajax/record-payment.php
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!$authUser->hasPermission('rentals.update')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}


$agreementId = (int) ($_POST['agreement_id'] ?? 0);
$amount = (float) ($_POST['amount'] ?? 0);
$paymentMethod = trim($_POST['payment_method'] ?? 'cash');
$notes = trim($_POST['notes'] ?? '');

if (!$agreementId || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid agreement or amount.']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    // Fetch current amounts
    $ra = $db->fetchOne(
        "SELECT agreement_id, total_amount, amount_paid, payment_status FROM rental_agreements WHERE agreement_id = ?",
        [$agreementId]
    );
    if (!$ra)
        throw new Exception('Rental agreement not found.');

    $newAmountPaid = (float) $ra['amount_paid'] + $amount;
    $totalAmount = (float) $ra['total_amount'];

    if ($newAmountPaid >= $totalAmount) {
        $newStatus = 'fully_paid';
        $newAmountPaid = $totalAmount; // cap at total
    } elseif ($newAmountPaid > 0) {
        $newStatus = 'partial';
    } else {
        $newStatus = 'pending';
    }

    $db->execute(
        "UPDATE rental_agreements SET amount_paid = ?, payment_status = ?, updated_at = NOW() WHERE agreement_id = ?",
        [$newAmountPaid, $newStatus, $agreementId]
    );

    // Append to notes log
    $paymentNote = "[Payment] " . date('Y-m-d H:i') . " — " . strtoupper($paymentMethod) .
        " ₱" . number_format($amount, 2) .
        ($notes ? " | {$notes}" : '');

    $db->execute(
        "UPDATE rental_agreements SET notes = CONCAT(IFNULL(notes,''), '\n', ?) WHERE agreement_id = ?",
        [$paymentNote, $agreementId]
    );

    if (class_exists('AuditLogger')) {
        AuditLogger::log(
            $authUser->getId(),
            null,
            null,
            'update',
            'rentals',
            'rental_agreements',
            $agreementId,
            "Payment recorded: ₱{$amount} via {$paymentMethod}",
            json_encode($ra),
            json_encode(['amount_paid' => $newAmountPaid, 'payment_status' => $newStatus]),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            'POST',
            '/rentals/ajax/record-payment',
            'info'
        );
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'new_amount_paid' => $newAmountPaid,
        'payment_status' => $newStatus,
        'message' => 'Payment of ₱' . number_format($amount, 2) . ' recorded successfully.',
    ]);
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
