<?php
/**
 * New Rental Booking Form - Unstyled
 * Path: modules/rentals/booking-new.php
 */
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$authUser->requirePermission('rentals.create');

// Fetch available vehicles
$vehicles = $db->fetchAll("
    SELECT vehicle_id, plate_number, brand, model, daily_rental_rate, security_deposit_amount 
    FROM vehicles 
    WHERE current_status = 'available' AND deleted_at IS NULL
");

// Fetch active customers
$customers = $db->fetchAll("
    SELECT customer_id, first_name, last_name, customer_code 
    FROM customers 
    WHERE is_blacklisted = 0 AND deleted_at IS NULL
");

$errors = [];
$successId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token mismatch.";
    } else {
        try {
            // Get vehicle rates for calculation (or let the class handle it)
            $vId = $_POST['vehicle_id'];
            $vCols = $db->fetchOne("SELECT daily_rental_rate, security_deposit_amount FROM vehicles WHERE vehicle_id = ?", [$vId]);

            if (!$vCols) {
                throw new Exception("Selected vehicle not found.");
            }

            $rentalObj = new RentalAgreement();
            $agreementId = $rentalObj->create([
                'customer_id' => $_POST['customer_id'],
                'vehicle_id' => $vId,
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'rental_rate' => $vCols['daily_rental_rate'],
                'security_deposit' => $vCols['security_deposit_amount'],
                'pickup_location' => $_POST['pickup_location'] ?? 'main_office',
                'return_location' => $_POST['return_location'] ?? 'main_office',
            ], $authUser->getId());

            // User wants this fully functional - Redirect to review page
            header("Location: view.php?id={$agreementId}");
            exit;

        } catch (Exception $e) {
            $errors[] = "Process Failed: " . $e->getMessage();
        }
    }
}

$pageTitle = "New Rental Booking";
require_once '../../includes/header.php';
?>

<h1>New Rental Booking</h1>
<p>Initialize a new rental agreement directly into <strong>Confirmed</strong> status.</p>
<hr>

<?php if (!empty($errors)): ?>
    <div style="color:red; border:1px solid red; padding:10px; margin-bottom:20px;">
        <strong>Errors:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li>
                    <?= htmlspecialchars($e) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST">
    <?= csrfField(); ?>

    <table border="0" cellpadding="10">
        <tr>
            <td width="200"><strong>Customer: *</strong></td>
            <td>
                <select name="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['customer_id'] ?>">
                            <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ' [' . $c['customer_code'] . ']') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><strong>Vehicle: *</strong></td>
            <td>
                <select name="vehicle_id" required>
                    <option value="">-- Select Available Vehicle --</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['vehicle_id'] ?>">
                            <?= htmlspecialchars($v['brand'] . ' ' . $v['model'] . ' (' . $v['plate_number'] . ')') ?> - ₱
                            <?= number_format($v['daily_rental_rate'], 2) ?>/day
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><strong>Pickup Date: *</strong></td>
            <td><input type="date" name="start_date" required min="<?= date('Y-m-d') ?>"></td>
        </tr>
        <tr>
            <td><strong>Return Date: *</strong></td>
            <td><input type="date" name="end_date" required min="<?= date('Y-m-d') ?>"></td>
        </tr>
        <tr>
            <td><strong>Pickup Location:</strong></td>
            <td>
                <select name="pickup_location">
                    <option value="main_office">Main Office</option>
                    <option value="airport">Airport</option>
                    <option value="hotel_delivery">Hotel Delivery</option>
                    <option value="other">Other</option>
                </select>
            </td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-top:20px;">
                <button type="submit"
                    style="padding:10px 30px; cursor:pointer; background:#28a745; color:white; border:none;">Process &
                    Confirm Booking</button>
                &nbsp;&nbsp;
                <a href="confirmed.php">Cancel</a>
            </td>
        </tr>
    </table>
</form>

<hr>
<p><small>* Fields marked with asterisk are required. Upon submission, the vehicle will be reserved and a "Confirmed"
        agreement will be created.</small></p>

<?php require_once '../../includes/footer.php'; ?>