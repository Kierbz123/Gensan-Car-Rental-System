<?php
/**
 * Compliance Renewal Page - Unstyled
 * Path: modules/compliance/renew.php
 *
 * Handles the renewal of existing compliance instruments.
 */

require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

// Security check
$authUser->requirePermission('compliance.create');

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Error: Missing record ID.");
}

// Fetch existing record to renew
$oldRecord = $db->fetchOne(
    "SELECT cr.*, v.plate_number, v.brand, v.model 
     FROM compliance_records cr 
     JOIN vehicles v ON cr.vehicle_id = v.vehicle_id 
     WHERE cr.record_id = ?",
    [$id]
);

if (!$oldRecord) {
    die("Error: Record not found.");
}

// Optimization: Check if record is already renewed to prevent double renewal
if (!empty($oldRecord['renewed_record_id'])) {
    die("Error: This record has already been renewed (New ID: {$oldRecord['renewed_record_id']}).");
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token mismatch. Please refresh and try again.";
    } else {
        $vehicle_id = $oldRecord['vehicle_id'];
        $compliance_type = $oldRecord['compliance_type'];
        $doc_number = trim($_POST['document_number'] ?? '');
        $issuer = trim($_POST['issuing_authority'] ?? '');
        $issue_date = $_POST['issue_date'] ?? '';
        $expiry_date = $_POST['expiry_date'] ?? '';
        $cost = $_POST['renewal_cost'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        // Basic Validation
        if (empty($doc_number))
            $errors[] = "Document number is required.";
        if (empty($issue_date))
            $errors[] = "Issue date is required.";
        if (empty($expiry_date))
            $errors[] = "Expiry date is required.";
        if (!empty($issue_date) && !empty($expiry_date) && $expiry_date <= $issue_date) {
            $errors[] = "Expiry date must be after the issue date.";
        }

        if (empty($errors)) {
            // File upload
            $documentFilePath = null;
            if (!empty($_FILES['document_file']['tmp_name'])) {
                $file = $_FILES['document_file'];
                $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
                $maxSize = MAX_UPLOAD_SIZE;

                if ($file['size'] > $maxSize) {
                    $errors[] = "File is too large (Max " . round($maxSize / 1024 / 1024) . "MB).";
                } elseif (!in_array(mime_content_type($file['tmp_name']), $allowedMime)) {
                    $errors[] = "Invalid file type. Only PDF, JPG, and PNG are allowed.";
                } else {
                    $uploadDir = DOCUMENTS_PATH;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName = 'renewal_' . $vehicle_id . '_' . time() . '.' . strtolower($ext);
                    $dest = $uploadDir . $fileName;

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $documentFilePath = 'assets/images/uploads/documents/' . $fileName;
                    } else {
                        $errors[] = "Failed to upload document file.";
                    }
                }
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // 1. Insert New Record
                $newId = $db->insert(
                    "INSERT INTO compliance_records 
                        (vehicle_id, compliance_type, document_number, issuing_authority, 
                         issue_date, expiry_date, renewal_cost, document_file_path, 
                         notes, created_by, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')",
                    [
                        $vehicle_id,
                        $compliance_type,
                        $doc_number,
                        $issuer ?: null,
                        $issue_date,
                        $expiry_date,
                        $cost !== '' ? (float) $cost : null,
                        $documentFilePath,
                        $notes ?: null,
                        $_SESSION['user_id']
                    ]
                );

                // 2. Mark Old Record as Renewed
                $db->execute(
                    "UPDATE compliance_records SET status = 'renewed', renewed_record_id = ? WHERE record_id = ?",
                    [$newId, $id]
                );

                $db->commit();

                // 3. Log Audit
                if (class_exists('AuditLogger')) {
                    AuditLogger::log(
                        $_SESSION['user_id'],
                        null,
                        null,
                        'update',
                        'compliance',
                        'compliance_records',
                        $id,
                        "Renewed {$compliance_type} for {$vehicle_id}. New Ref: {$doc_number}",
                        null,
                        json_encode(['old_id' => $id, 'new_id' => $newId]),
                        getClientIP(),
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'POST',
                        '/compliance/renew',
                        'info'
                    );
                }

                $_SESSION['success_message'] = "Compliance instrument renewed successfully.";
                header("Location: index.php");
                exit;

            } catch (Exception $e) {
                $db->rollback();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Renew Compliance";
require_once '../../includes/header.php';
?>

<h1>Renew Compliance Instrument</h1>
<p>
    <strong>Vehicle:</strong>
    <?= htmlspecialchars($oldRecord['brand'] . ' ' . $oldRecord['model'] . ' (' . $oldRecord['plate_number'] . ')') ?><br>
    <strong>Type:</strong>
    <?= str_replace('_', ' ', strtoupper(htmlspecialchars($oldRecord['compliance_type']))) ?><br>
    <strong>Expired/Current Ref:</strong>
    <?= htmlspecialchars($oldRecord['document_number'] . ' (Expiried: ' . $oldRecord['expiry_date'] . ')') ?>
</p>
<hr>

<?php if (!empty($errors)): ?>
    <div style="background: #fee2e2; border: 1px solid #f87171; color: #991b1b; padding: 15px; margin-bottom: 20px;">
        <strong>Validation Errors:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li>
                    <?= htmlspecialchars($e) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField(); ?>

    <table border="0" cellpadding="8" style="width: 100%; max-width: 600px;">
        <tr>
            <td width="200"><strong>New Document Number: *</strong></td>
            <td><input type="text" name="document_number" required
                    value="<?= htmlspecialchars($_POST['document_number'] ?? $oldRecord['document_number']) ?>"
                    style="width: 100%;"></td>
        </tr>
        <tr>
            <td><strong>Issuing Authority:</strong></td>
            <td><input type="text" name="issuing_authority"
                    value="<?= htmlspecialchars($_POST['issuing_authority'] ?? $oldRecord['issuing_authority']) ?>"
                    style="width: 100%;"></td>
        </tr>
        <tr>
            <td><strong>New Issue Date: *</strong></td>
            <td><input type="date" name="issue_date" required
                    value="<?= htmlspecialchars($_POST['issue_date'] ?? date('Y-m-d')) ?>"></td>
        </tr>
        <tr>
            <td><strong>New Expiry Date: *</strong></td>
            <td><input type="date" name="expiry_date" required
                    value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>"></td>
        </tr>
        <tr>
            <td><strong>Renewal Cost (₱):</strong></td>
            <td><input type="number" step="0.01" name="renewal_cost"
                    value="<?= htmlspecialchars($_POST['renewal_cost'] ?? '') ?>" placeholder="0.00"></td>
        </tr>
        <tr>
            <td><strong>Notes:</strong></td>
            <td><textarea name="notes" rows="4"
                    style="width: 100%;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea></td>
        </tr>
        <tr>
            <td><strong>Upload New Document:</strong><br><small>(PDF, JPG, PNG)</small></td>
            <td><input type="file" name="document_file"></td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-top: 20px;">
                <button type="submit" style="padding: 10px 30px; cursor: pointer;">Complete Renewal</button>
                &nbsp;&nbsp;
                <a href="index.php">Cancel and Go Back</a>
            </td>
        </tr>
    </table>
</form>

<hr>
<p><small>* Required fields. Renewing will create a new tracking record and link it to this historical entry.</small>
</p>

<?php require_once '../../includes/footer.php'; ?>