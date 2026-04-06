<?php
/**
 * Add Mechanic Page
 * Path: modules/maintenance/mechanics/mechanic-add.php
 */
require_once '../../../config/config.php';
require_once '../../../includes/session-manager.php';

$authUser->requirePermission('maintenance.create');

$pageTitle = 'Add Mechanic';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'specialization' => sanitize($_POST['specialization'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'email' => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
        'status' => 'active',
    ];

    foreach (['first_name', 'last_name'] as $field) {
        if (empty($data[$field])) {
            $errors[$field] = ucwords(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    if (empty($errors)) {
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO mechanics (first_name, last_name, specialization, phone, email, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            array_values($data)
        );
        $_SESSION['success_message'] = 'Mechanic added successfully.';
        redirect('index.php');
    }
}

include_once '../../../includes/header.php';
?>
<div class="mb-4">
    <h1 class="text-2xl font-bold text-gray-900">Add Mechanic</h1>
</div>
<div class="card max-w-lg">
    <form method="POST">
        <div class="grid grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" name="first_name" class="form-input" required
                    value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                <?php if (isset($errors['first_name'])): ?>
                    <p class="text-red-500 text-xs mt-1">
                        <?php echo $errors['first_name']; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" name="last_name" class="form-input" required
                    value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                <?php if (isset($errors['last_name'])): ?>
                    <p class="text-red-500 text-xs mt-1">
                        <?php echo $errors['last_name']; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Specialization</label>
            <input type="text" name="specialization" class="form-input"
                value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-input"
                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input"
                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <div class="flex gap-3 mt-4">
            <button type="submit" class="btn btn-primary">Save Mechanic</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php include_once '../../../includes/footer.php'; ?>