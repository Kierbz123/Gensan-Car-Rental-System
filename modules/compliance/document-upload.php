<?php
/**
 * Document Upload (Compliance)
 * Path: modules/compliance/document-upload.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
if (!$authUser) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
$authUser->requirePermission('compliance.create');

$db = Database::getInstance();
$recordId = (int) ($_GET['id'] ?? 0);
$errors = [];
$success = false;

if ($recordId) {
    $record = $db->fetchOne("SELECT cr.*, v.plate_number, v.brand, v.model FROM compliance_records cr JOIN vehicles v ON cr.vehicle_id=v.vehicle_id WHERE cr.record_id=?", [$recordId]);
    if (!$record) {
        $_SESSION['error_message'] = 'Compliance record not found.';
        header('Location: index.php');
        exit;
    }
} else {
    $record = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid token.';
    } elseif (empty($_FILES['document']['name'])) {
        $errors[] = 'No file selected.';
    } else {
        $file = $_FILES['document'];
        $allowed = ALLOWED_DOCUMENT_TYPES;
        $maxSize = MAX_UPLOAD_SIZE;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowed)) {
            $errors[] = 'Invalid file type. PDF, JPG, PNG accepted.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'File exceeds size limit.';
        } else {
            $uploadDir = DOCUMENTS_PATH;
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                if ($recordId && $record) {
                    $db->execute(
                        "UPDATE compliance_records SET document_file_path=?, updated_at=NOW() WHERE record_id=?",
                        ['assets/images/uploads/documents/' . $filename, $recordId]
                    );
                    $success = true;
                }
            } else {
                $errors[] = 'Upload failed. Check permissions.';
            }
        }
    }
}

$pageTitle = 'Upload Compliance Document';
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest">
        <a href="index.php" class="text-secondary-400 hover:text-primary-600">Compliance</a><span
            class="text-secondary-200">/</span><span class="text-primary-600">Document Upload</span>
    </div>
    <h1 class="heading mb-2">Upload Compliance Document</h1>
    <p class="text-secondary-500 font-medium mb-8">Attach a scanned PDF, JPEG, or PNG document to a compliance record.
    </p>

    <?php if ($success): ?>
        <div
            class="flex items-center gap-3 p-5 mb-6 bg-success-50 border border-success-100 rounded-2xl text-success-700 font-bold text-sm">
            <i data-lucide="check-circle" class="w-5 h-5"></i> Document uploaded successfully.
            <a href="vehicle-compliance.php?vehicle_id=<?= urlencode($record['vehicle_id']) ?>" class="ml-auto text-xs underline">View
                Records</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="flex gap-3 p-4 mb-5 bg-danger-50 border border-danger-100 rounded-2xl text-danger-700">
            <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
            <ul class="text-xs list-disc list-inside"><?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($record): ?>
        <div class="card mb-5 bg-secondary-50 border-secondary-100 flex items-center gap-4">
            <div class="p-2.5 bg-primary-50 rounded-xl"><i data-lucide="file-badge" class="w-5 h-5 text-primary-600"></i>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-0.5">Attaching to</p>
                <p class="font-black text-secondary-900">
                    <?= str_replace('_', ' ', ucwords(str_replace('_', ' ', $record['compliance_type']))) ?></p>
                <p class="text-xs text-secondary-500">
                    <?= htmlspecialchars($record['plate_number'] . ' – ' . $record['brand'] . ' ' . $record['model']) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <?php if ($recordId): ?><input type="hidden" name="record_id" value="<?= $recordId ?>"><?php endif; ?>
        <div class="card flex flex-col gap-5">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-3">Document
                    File <span class="text-danger-500">*</span></label>
                <div id="drop-zone"
                    class="border-2 border-dashed border-secondary-200 rounded-2xl p-10 text-center cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition-colors">
                    <i data-lucide="upload-cloud" class="w-10 h-10 text-secondary-300 mx-auto mb-3"></i>
                    <p class="font-bold text-secondary-600 text-sm mb-1">Drag & drop or <span
                            class="text-primary-600">browse</span></p>
                    <p class="text-xs text-secondary-400">PDF, JPG, PNG · Max 10MB</p>
                    <input type="file" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png"
                        class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFile(this)">
                </div>
                <div id="file-preview"
                    class="hidden mt-3 flex items-center gap-3 p-3.5 bg-success-50 border border-success-100 rounded-xl text-success-700 text-xs font-bold">
                    <i data-lucide="file-check" class="w-4 h-4"></i>
                    <span id="file-name"></span>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit"
                    class="btn btn-primary flex-1 py-4 font-black text-xs uppercase tracking-widest gap-2"><i
                        data-lucide="upload" class="w-4 h-4"></i> Upload Document</button>
                <a href="index.php"
                    class="btn btn-ghost flex-1 py-4 text-xs font-bold text-center border border-secondary-100">Cancel</a>
            </div>
        </div>
    </form>
</div>
<style>
    #drop-zone {
        position: relative;
    }
</style>
<script>
    function previewFile(input) {
        if (input.files && input.files[0]) {
            document.getElementById('file-name').textContent = input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
            document.getElementById('file-preview').classList.remove('hidden');
        }
    }
    lucide.createIcons();
</script>
<?php require_once '../../includes/footer.php'; ?>