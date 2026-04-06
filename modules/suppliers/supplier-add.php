<?php
// modules/suppliers/supplier-add.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('suppliers.create');

$error = '';
$data = [
    'company_name' => '', 'business_type' => 'sole_proprietor', 'tax_id' => '',
    'category' => 'auto_parts', 'address' => '', 'city' => 'General Santos City',
    'province' => 'South Cotabato', 'zip_code' => '', 'contact_person' => '',
    'position' => '', 'phone_primary' => '', 'phone_secondary' => '',
    'email' => '', 'website' => '', 'payment_terms' => '', 'credit_limit' => '',
    'lead_time_days' => '1', 'is_accredited' => 0, 'accreditation_date' => '',
    'is_active' => 1, 'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $data = array_merge($data, array_map(function ($v) { return is_string($v) ? trim($v) : $v; }, $_POST));
        $data['is_accredited'] = isset($_POST['is_accredited']) ? 1 : 0;
        $data['is_active']     = isset($_POST['is_active']) ? 1 : 0;

        if (empty($data['company_name'])) $error = 'Company name is required.';
        elseif (empty($data['phone_primary'])) $error = 'Primary phone is required.';

        if (!$error) {
            try {
                $supplier = new Supplier();
                $newId = $supplier->create($data, $authUser->getData()['user_id']);
                $_SESSION['success_message'] = 'Supplier added successfully.';
                header("Location: supplier-view.php?id={$newId}");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Add Supplier';
require_once '../../includes/header.php';

$CATEGORIES = [
    'auto_parts' => 'Auto Parts', 'maintenance_supplies' => 'Maintenance Supplies',
    'fuel' => 'Fuel', 'tires' => 'Tires', 'carwash_supplies' => 'Carwash Supplies',
    'insurance' => 'Insurance', 'registration_services' => 'Registration Services', 'others' => 'Others',
];
$BIZ_TYPES = [
    'corporation' => 'Corporation', 'partnership' => 'Partnership',
    'sole_proprietor' => 'Sole Proprietor', 'cooperative' => 'Cooperative',
];
?>

<div class="page-header">
    <div class="page-title">
        <h1><i data-lucide="truck"
                style="width:22px;height:22px;vertical-align:-4px;margin-right:8px;color:var(--primary)"></i>Add
            Supplier</h1>
        <p>Register a new vendor or service provider.</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back
        </a>
    </div>
</div>

<form method="POST" style="max-width:720px;">
    <?= csrfField() ?>

    <?php if ($error): ?>
        <div
            style="margin-bottom:1.5rem;padding:1rem;background:var(--danger-light);color:var(--danger);border-radius:var(--radius-md);font-weight:500;display:flex;align-items:center;gap:.5rem;">
            <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="building-2"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Company
                Details</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="company_name">Company Name <span style="color:var(--danger)">*</span></label>
                <input type="text" id="company_name" name="company_name" class="form-control" required
                    value="<?= htmlspecialchars($data['company_name']) ?>"
                    placeholder="e.g. GenSan Toyota Genuine Parts">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="business_type">Business Type</label>
                    <select id="business_type" name="business_type" class="form-control">
                        <?php foreach ($BIZ_TYPES as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $data['business_type'] === $v ? 'selected' : '' ?>><?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="category">Category <span style="color:var(--danger)">*</span></label>
                    <select id="category" name="category" class="form-control">
                        <?php foreach ($CATEGORIES as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $data['category'] === $v ? 'selected' : '' ?>><?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="tax_id">TIN / Tax ID</label>
                <input type="text" id="tax_id" name="tax_id" class="form-control"
                    value="<?= htmlspecialchars($data['tax_id']) ?>" placeholder="e.g. 123-456-789-000">
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" class="form-control"
                    value="<?= htmlspecialchars($data['address']) ?>"
                    placeholder="Street address">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" class="form-control"
                        value="<?= htmlspecialchars($data['city']) ?>">
                </div>
                <div class="form-group">
                    <label for="province">Province</label>
                    <input type="text" id="province" name="province" class="form-control"
                        value="<?= htmlspecialchars($data['province']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title"><i data-lucide="contact"
                    style="width:16px;height:16px;margin-right:6px;vertical-align:-2px;color:var(--primary)"></i>Contact
                & Terms</h2>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="contact_person">Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" class="form-control"
                        value="<?= htmlspecialchars($data['contact_person']) ?>" placeholder="Full name">
                </div>
                <div class="form-group">
                    <label for="position">Position</label>
                    <input type="text" id="position" name="position" class="form-control"
                        value="<?= htmlspecialchars($data['position']) ?>" placeholder="e.g. Sales Manager">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone_primary">Primary Phone <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="phone_primary" name="phone_primary" class="form-control" required
                        value="<?= htmlspecialchars($data['phone_primary']) ?>" placeholder="e.g. 0917-123-4567">
                </div>
                <div class="form-group">
                    <label for="phone_secondary">Secondary Phone</label>
                    <input type="text" id="phone_secondary" name="phone_secondary" class="form-control"
                        value="<?= htmlspecialchars($data['phone_secondary']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                        value="<?= htmlspecialchars($data['email']) ?>" placeholder="vendor@example.com">
                </div>
                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" class="form-control"
                        value="<?= htmlspecialchars($data['website']) ?>" placeholder="https://...">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="payment_terms">Payment Terms</label>
                    <input type="text" id="payment_terms" name="payment_terms" class="form-control"
                        value="<?= htmlspecialchars($data['payment_terms']) ?>" placeholder="e.g. Net 30, COD">
                </div>
                <div class="form-group">
                    <label for="credit_limit">Credit Limit (₱)</label>
                    <input type="number" id="credit_limit" name="credit_limit" class="form-control" min="0"
                        step="0.01" value="<?= htmlspecialchars($data['credit_limit']) ?>" placeholder="0.00">
                </div>
            </div>
            <div class="form-group">
                <label for="lead_time_days">Avg. Lead Time (days)</label>
                <input type="number" id="lead_time_days" name="lead_time_days" class="form-control" min="0"
                    value="<?= htmlspecialchars($data['lead_time_days']) ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="checkbox" name="is_accredited" value="1"
                            <?= $data['is_accredited'] ? 'checked' : '' ?>
                            style="width:16px;height:16px;">
                        Accredited Vendor
                    </label>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1"
                            <?= $data['is_active'] ? 'checked' : '' ?>
                            style="width:16px;height:16px;">
                        Active
                    </label>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control"
                    rows="2"><?= htmlspecialchars($data['notes']) ?></textarea>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="plus" style="width:16px;height:16px;"></i> Add Supplier
        </button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<?php require_once '../../includes/footer.php'; ?>
