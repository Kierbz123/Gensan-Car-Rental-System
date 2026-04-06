<?php
/**
 * Customer Profile View
 * Path: modules/customers/customer-view.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/DocumentManager.php';

$authUser->requirePermission('customers.view');

$db = Database::getInstance();
$customerId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    if ($authUser->hasPermission('customers.update')) {
        try {
            $cat = $_POST['document_category'] ?? 'other';
            $title = !empty($_POST['document_title']) ? $_POST['document_title'] : null;
            $exp = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            DocumentManager::uploadDocument($_FILES['document_file'], 'customer', $customerId, $cat, $title, $authUser->getId(), $exp);
            $_SESSION['success_message'] = "Document uploaded successfully.";
            header("Location: customer-view.php?id=" . $customerId);
            exit;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
}

if (!$customerId) {
    redirect('modules/customers/', 'Customer ID missing', 'error');
}

try {
    $customer = $db->fetchOne(
        "SELECT c.*, CONCAT(c.first_name,' ',c.last_name) AS full_name
         FROM customers c WHERE c.customer_id = ? AND c.deleted_at IS NULL",
        [$customerId]
    );
    if (!$customer) {
        redirect('modules/customers/', 'Customer not found', 'error');
    }

    $rentals = $db->fetchAll(
        "SELECT ra.*, v.plate_number, v.brand, v.model
         FROM rental_agreements ra
         JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
         WHERE ra.customer_id = ?
         ORDER BY ra.created_at DESC LIMIT 10",
        [$customerId]
    );
    $totalSpent = $db->fetchColumn(
        "SELECT COALESCE(SUM(total_amount),0) FROM rental_agreements WHERE customer_id = ? AND status IN ('completed','returned')",
        [$customerId]
    );
    
    $customerDocs = DocumentManager::getDocumentsByEntity('customer', $customerId);
} catch (Exception $e) {
    error_log($e->getMessage());
    $rentals = [];
    $customerDocs = [];
    $totalSpent = 0;
}

// Flash success
$successMsg = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

$pageTitle = $customer['full_name'] . ' — Profile';
require_once '../../includes/header.php';
?>
<div class="fade-in max-w-7xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 mb-8 text-[10px] font-black uppercase tracking-widest flex-wrap">
        <a href="index.php"
            class="text-secondary-400 hover:text-primary-600 transition-colors whitespace-nowrap">Customer Registry</a>
        <span class="text-secondary-200">/</span>
        <span class="text-primary-600 break-words"><?= htmlspecialchars($customer['full_name']) ?></span>
    </div>


    <!-- Main Layout Grid -->
    <div class="grid" style="grid-template-columns: 1fr 2fr; gap: var(--space-6);">
        <!-- Avatar / Summary Card (Left Column) -->
        <div class="flex flex-col gap-6">
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <?php if (!empty($customer['profile_picture_path'])): ?>
                        <img src="<?= BASE_URL . ltrim($customer['profile_picture_path'], '/') ?>"
                            style="width: 120px; height: 120px; border-radius: 50%; margin: 0 auto var(--space-4); object-fit: cover; border: 4px solid var(--primary-100);"
                            alt="Profile Picture">
                    <?php else: ?>
                        <div
                            style="width: 120px; height: 120px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; margin: 0 auto var(--space-4);">
                            <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <h2 style="margin-bottom: var(--space-2);">
                        <?= htmlspecialchars($customer['full_name']) ?>
                    </h2>
                    <p
                        style="color: var(--text-muted); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-1);">
                        <?= str_replace('_', ' ', ucfirst($customer['customer_type'])) ?>
                    </p>
                    <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: var(--space-4);">
                        <?= htmlspecialchars($customer['customer_code']) ?>
                    </p>
                    <?php
                    if (!empty($customer['is_blacklisted'])) {
                        $statusLabel = 'BLACKLISTED';
                        $statusColor = 'var(--danger)';
                    } else if (isset($customer['status']) && $customer['status'] === 'active') {
                        $statusLabel = 'ACTIVE';
                        $statusColor = 'var(--success)';
                    } else {
                        $statusLabel = strtoupper($customer['status'] ?? 'ACTIVE');
                        $statusColor = 'var(--secondary-500)';
                    }
                    ?>
                    <div
                        style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: <?= $statusColor ?>; color: white; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">
                        <span style="width: 6px; height: 6px; background: white; border-radius: 50%;"></span>
                        <?= $statusLabel ?>
                    </div>

                    <div
                        style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border-color); text-align: left;">
                        <p
                            style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold; margin-bottom: var(--space-3);">
                            Metadata</p>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Registered</span>
                            <strong><?= formatDate($customer['created_at']) ?></strong>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Last Updated</span>
                            <strong><?= formatDate($customer['updated_at']) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                            <span style="color: var(--text-secondary);">Last Rental</span>
                            <span><?= $customer['last_rental_date'] ? formatDate($customer['last_rental_date']) : 'N/A' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="card">
                <div class="card-body">
                    <h2 style="margin-bottom: var(--space-4); margin-top: 0; font-size: 1rem;">Client Statistics</h2>

                    <div
                        style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem; align-items: center;">
                        <span style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px;"><i
                                data-lucide="calendar" style="width: 14px; height: 14px;"></i> Total Rentals</span>
                        <strong><?= number_format($customer['total_rentals'] ?? 0) ?></strong>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; margin-bottom: var(--space-2); font-size: 0.875rem; align-items: center;">
                        <span style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px;"><i
                                data-lucide="banknote" style="width: 14px; height: 14px;"></i> Total Spent</span>
                        <strong><?= formatCurrency($totalSpent) ?></strong>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; font-size: 0.875rem; align-items: center;">
                        <span style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px;"><i
                                data-lucide="star" style="width: 14px; height: 14px;"></i> Credit Rating</span>
                        <strong><?= ucfirst($customer['credit_rating'] ?? 'good') ?></strong>
                    </div>
                </div>
            </div>

            <!-- Notes Card -->
            <div class="card">
                <div class="card-body">
                    <label
                        class="block text-[10px] font-black uppercase tracking-widest text-secondary-400 mb-3">Notes</label>
                    <textarea name="notes" rows="6"
                        class="form-input w-full rounded-2xl py-3.5 bg-secondary-50 resize-none"
                        readonly><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Page Actions via Template -->
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?php if ($authUser->hasPermission('customers.update')): ?>
                    <a href="customer-edit.php?id=<?= $customerId ?>" class="btn btn-primary"
                        style="justify-content: center;">
                        <i data-lucide="pencil" class="w-4 h-4"></i> Edit Profile
                    </a>
                <?php endif; ?>
                <?php if ($authUser->hasPermission('rentals.create')): ?>
                    <a href="../rentals/reserve.php?customer_id=<?= $customerId ?>" class="btn btn-secondary"
                        style="justify-content: center;">
                        <i data-lucide="key" class="w-4 h-4"></i> New Rental
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detail Sections (Right Column) -->
        <div class="flex flex-col gap-6">
            <!-- Contact Info -->
            <div class="card">
                <div class="card-body">
                    <h2 style="margin-bottom: var(--space-4); margin-top: 0;">Contact Information</h2>
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <!-- Fields mapping -->
                        <?php
                        $fields = [
                            ['Phone', 'phone_primary'],
                            ['Alt Phone', 'phone_secondary'],
                            ['Email', 'email'],
                            ['Date of Birth', 'date_of_birth'],
                            ['Gender', 'gender'],
                            ['ID Number', 'id_number'],
                            ['ID Type', 'id_type'],
                            ['ID Expiry', 'id_expiry_date'],
                        ];
                        foreach ($fields as [$label, $key]):
                            $val = $customer[$key] ?? null;
                            if (!$val)
                                continue;
                            ?>
                            <div>
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;"><?= $label ?></label>
                                <p style="font-weight: bold; margin: 0; word-break: break-all;">
                                    <?= htmlspecialchars(str_contains($key, 'date') ? formatDate($val) : $val) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($customer['address']): ?>
                            <div style="grid-column: 1 / -1;">
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">Address</label>
                                <p style="font-weight: bold; margin: 0;">
                                    <?= htmlspecialchars($customer['address']) ?>,
                                    <?= htmlspecialchars($customer['city']) ?>,
                                    <?= htmlspecialchars($customer['province']) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Identity Documents -->
            <?php if (!empty($customer['id_photo_front_path']) || !empty($customer['id_photo_back_path'])): ?>
                <div class="card">
                    <div class="card-body">
                        <h2
                            style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="scan-line" style="color: var(--primary);"></i> Scanned Documents
                        </h2>
                        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <?php if (!empty($customer['id_photo_front_path'])): ?>
                                <div>
                                    <label
                                        style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">ID
                                        Photo (Front)</label>
                                    <a href="<?= BASE_URL . ltrim($customer['id_photo_front_path'], '/') ?>" target="_blank"
                                        style="display: block; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); background: var(--secondary-50); padding: 4px;">
                                        <img src="<?= BASE_URL . ltrim($customer['id_photo_front_path'], '/') ?>"
                                            style="width: 100%; height: 180px; object-fit: contain; display: block;"
                                            alt="ID Front">
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($customer['id_photo_back_path'])): ?>
                                <div>
                                    <label
                                        style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">ID
                                        Photo (Back)</label>
                                    <a href="<?= BASE_URL . ltrim($customer['id_photo_back_path'], '/') ?>" target="_blank"
                                        style="display: block; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); background: var(--secondary-50); padding: 4px;">
                                        <img src="<?= BASE_URL . ltrim($customer['id_photo_back_path'], '/') ?>"
                                            style="width: 100%; height: 180px; object-fit: contain; display: block;"
                                            alt="ID Back">
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($errorMsg)): ?>
                <div style="padding:1rem; background:var(--danger-light); color:var(--danger); border-radius:var(--radius-md); margin-bottom:var(--space-4);">
                    <i data-lucide="alert-circle" style="width:16px;height:16px; display:inline-block; vertical-align:-3px; margin-right:4px;"></i>
                    <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>

            <!-- Document Repository -->
            <div class="card">
                <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: var(--space-4); margin: -var(--space-4) -var(--space-4) var(--space-4) -var(--space-4);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h2 class="card-title" style="margin: 0; font-size: 1.1rem; display:flex; align-items:center; gap:8px;">
                            <i data-lucide="files" style="color:var(--accent);"></i> Customer Documents
                        </h2>
                        <?php if ($authUser->hasPermission('customers.update')): ?>
                        <button type="button" onclick="document.getElementById('uploadDocForm').style.display='block'; this.style.display='none';" class="btn btn-sm btn-primary">
                            <i data-lucide="upload-cloud" style="width:14px;height:14px;"></i> Upload
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($authUser->hasPermission('customers.update')): ?>
                <form id="uploadDocForm" method="POST" enctype="multipart/form-data" style="display:none; padding:1rem; background:var(--bg-muted); border-radius:var(--radius-md); margin-bottom:1rem; border:1px solid var(--border-color);">
                    <p style="margin:0 0 1rem 0; font-size:0.875rem; font-weight:700;">Upload New Document</p>
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap:0.75rem; margin-bottom:0.75rem;">
                        <div>
                            <label style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">File * (Max 10MB)</label>
                            <input type="file" name="document_file" required class="form-control" style="padding:4px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">Category *</label>
                            <select name="document_category" required class="form-control" style="padding:6px;">
                                <option value="identity">Identity / ID Card</option>
                                <option value="contract">Contract / Agreement</option>
                                <option value="billing">Proof of Billing</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label style="display:block; font-size:0.75rem; margin-bottom:4px; font-weight:600;">Title & Expiry Date (Optional)</label>
                            <div style="display:flex; gap:0.75rem;">
                                <input type="text" name="document_title" class="form-control" placeholder="Custom name" style="flex:1;">
                                <input type="date" name="expires_at" class="form-control" style="width:150px;">
                            </div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <button type="button" onclick="document.getElementById('uploadDocForm').style.display='none'; document.querySelector('#uploadDocForm').previousElementSibling.querySelector('button').style.display='inline-flex';" class="btn btn-sm btn-ghost">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary">Save Document</button>
                    </div>
                </form>
                <?php endif; ?>

                <div class="table-container" style="border:none; margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <?php if (empty($customerDocs)): ?>
                        <div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:0.875rem;">No documents attached to this profile.</div>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead style="background: var(--secondary-50); border-bottom: 1px solid var(--border-color);">
                                <tr>
                                    <th style="padding: 10px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase;">Document</th>
                                    <th style="padding: 10px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase;">Date</th>
                                    <th style="padding: 10px 16px; text-align: right; font-size: 0.75rem; text-transform: uppercase;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customerDocs as $doc): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 10px 16px;">
                                            <div style="font-weight:700; font-size:0.85rem; color:var(--text-main); display:flex; align-items:center; gap:6px;">
                                                <i data-lucide="file-text" style="width:14px;height:14px;color:var(--text-muted);"></i>
                                                <?= htmlspecialchars($doc['title']) ?>
                                            </div>
                                            <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; margin-top:2px;">
                                                <?= htmlspecialchars($doc['document_category']) ?> &bull; <?= round($doc['file_size']/1024) ?> KB
                                            </div>
                                        </td>
                                        <td style="padding: 10px 16px; font-size:0.8rem;">
                                            <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?>
                                        </td>
                                        <td style="padding: 10px 16px; text-align:right;">
                                            <a href="../documents/serve.php?id=<?= $doc['document_id'] ?>" target="_blank" class="btn btn-sm btn-ghost" title="View"><i data-lucide="external-link" style="width:14px;height:14px;"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Emergency Contact -->
            <?php if (!empty($customer['emergency_name'])): ?>
                <div class="card" style="border-left: 4px solid var(--danger);">
                    <div class="card-body">
                        <h2
                            style="margin-bottom: var(--space-4); margin-top: 0; display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="heart-pulse" style="color: var(--danger);"></i> Emergency Contact
                        </h2>
                        <div class="grid"
                            style="grid-template-columns: 1fr 1fr; gap: var(--space-4); background: var(--danger-50); padding: var(--space-4); border-radius: var(--radius-md);">
                            <div>
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--danger); margin-bottom: 4px;">Contact
                                    Name & Relationship</label>
                                <p style="font-weight: bold; margin: 0; color: var(--danger-900);">
                                    <?= htmlspecialchars($customer['emergency_name']) ?> <span
                                        style="font-weight: normal; font-size: 0.8em; color: var(--danger-700);">(<?= htmlspecialchars($customer['emergency_relationship'] ?? '') ?>)</span>
                                </p>
                            </div>
                            <div>
                                <label
                                    style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--danger); margin-bottom: 4px;">Contact
                                    Phone</label>
                                <p style="font-weight: bold; margin: 0; color: var(--danger-900);">
                                    <?= htmlspecialchars($customer['emergency_phone'] ?? '') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Rental History -->
            <div class="card">
                <div class="card-header"
                    style="border-bottom: 1px solid var(--border-color); padding: var(--space-4); margin: -var(--space-4) -var(--space-4) var(--space-4) -var(--space-4);">
                    <h2 class="card-title" style="margin: 0; font-size: 1.1rem;">Recent Rentals</h2>
                </div>
                <div class="table-container"
                    style="border:none; margin: 0 -var(--space-4) -var(--space-4) -var(--space-4);">
                    <?php if (empty($rentals)): ?>
                        <div style="text-align:center;padding:3rem;color:var(--text-muted);">No rental history found.</div>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead style="background: var(--secondary-50); border-bottom: 1px solid var(--border-color);">
                                <tr>
                                    <th
                                        style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                        Agreement</th>
                                    <th
                                        style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                        Vehicle</th>
                                    <th
                                        style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                        Start</th>
                                    <th
                                        style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                        Amount</th>
                                    <th
                                        style="padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">
                                        Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rentals as $r): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px 16px;">
                                            <span
                                                style="font-family:monospace;background:var(--secondary-light);padding:2px 6px;border-radius:4px; font-weight: bold; font-size: 0.8em;"><?= htmlspecialchars($r['agreement_number']) ?></span>
                                        </td>
                                        <td style="padding: 12px 16px;">
                                            <div style="font-weight:600; font-size: 0.875rem;">
                                                <?= htmlspecialchars($r['brand'] . ' ' . $r['model']) ?>
                                            </div>
                                            <div style="font-size:0.75rem;color:var(--text-muted);">
                                                <?= htmlspecialchars($r['plate_number']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 0.875rem;">
                                            <?= formatDate($r['rental_start_date']) ?>
                                        </td>
                                        <td style="padding: 12px 16px; font-weight: 600; font-size: 0.875rem;">
                                            <?= formatCurrency($r['total_amount'] ?? 0) ?>
                                        </td>
                                        <td style="padding: 12px 16px;">
                                            <?= getBadge($r['status'], $r['status']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</script>

<?php if ($successMsg): ?>
    <div id="customer-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--success,#22c55e);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="check-circle" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($successMsg) ?></span>
        <button onclick="document.getElementById('customer-toast').remove()"
            style="background:none;border:none;cursor:pointer;color:#fff;padding:0;margin:0;display:flex;align-items:center;opacity:0.8;"
            aria-label="Dismiss">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    </div>
    <style>
        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(60px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }
    </style>
    <script>
        setTimeout(function () {
            var t = document.getElementById('customer-toast');
            if (t) {
                t.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                t.style.opacity = '0';
                t.style.transform = 'translateX(60px)';
                setTimeout(function () { if (t) t.remove(); }, 400);
            }
        }, 3500);
    </script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>