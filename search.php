<?php
require_once 'config/config.php';
require_once 'includes/session-manager.php';

$q = $_GET['q'] ?? '';
$pageTitle = "Search Results: " . htmlspecialchars($q);
require_once 'includes/header.php';

$db = Database::getInstance();

$results = [
    'vehicles' => [],
    'customers' => [],
    'rentals' => [],
    'procurement' => []
];

if (!empty($q)) {
    $searchWildcard = "%$q%";

    // Search Vehicles
    try {
        $results['vehicles'] = $db->fetchAll(
            "SELECT * FROM vehicles WHERE (vehicle_id LIKE ? OR plate_number LIKE ? OR brand LIKE ? OR model LIKE ?) AND deleted_at IS NULL LIMIT 10",
            [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]
        );
    } catch (Exception $e) {
        $results['errors'][] = "Vehicles search failed: " . $e->getMessage();
    }

    // Search Customers
    try {
        $results['customers'] = $db->fetchAll(
            "SELECT * FROM customers WHERE (first_name LIKE ? OR last_name LIKE ? OR customer_code LIKE ? OR email LIKE ?) AND deleted_at IS NULL LIMIT 10",
            [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]
        );
    } catch (Exception $e) {
        $results['errors'][] = "Customers search failed: " . $e->getMessage();
    }

    // Search Rentals
    try {
        $results['rentals'] = $db->fetchAll(
            "SELECT ra.*, v.plate_number, c.first_name, c.last_name 
             FROM rental_agreements ra
             JOIN vehicles v ON ra.vehicle_id = v.vehicle_id
             JOIN customers c ON ra.customer_id = c.customer_id
             WHERE ra.agreement_number LIKE ? 
             LIMIT 10",
            [$searchWildcard]
        );
    } catch (Exception $e) {
        $results['errors'][] = "Rentals search failed: " . $e->getMessage();
    }

    // Search Procurement
    try {
        $results['procurement'] = $db->fetchAll(
            "SELECT * FROM procurement_requests WHERE pr_number LIKE ? LIMIT 10",
            [$searchWildcard]
        );
    } catch (Exception $e) {
        $results['errors'][] = "Procurement search failed: " . $e->getMessage();
    }
}

?>

<div class="search-container">
    <h1>Search Results for "<?= htmlspecialchars($q) ?>"</h1>

    <?php if (!empty($results['errors'])): ?>
        <div style="color: red; margin-bottom: 20px;">
            <?php foreach ($results['errors'] as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($q)): ?>
        <p>Please enter a search term in the box above.</p>
    <?php else: ?>
        <?php
        $totalFound = count($results['vehicles']) + count($results['customers']) + count($results['rentals']) + count($results['procurement']);
        ?>

        <?php if ($totalFound === 0): ?>
            <p>No results found for your query. Try different keywords.</p>
        <?php else: ?>

            <?php if (!empty($results['vehicles'])): ?>
                <section>
                    <h2>Vehicles Found</h2>
                    <ul>
                        <?php foreach ($results['vehicles'] as $v): ?>
                            <li>
                                <a
                                    href="<?= BASE_URL ?>modules/asset-tracking/vehicle-details.php?id=<?= urlencode($v['vehicle_id']) ?>">
                                    <strong><?= htmlspecialchars($v['brand'] . ' ' . $v['model']) ?></strong>
                                    (Plate: <?= htmlspecialchars($v['plate_number']) ?>) - ID: <?= htmlspecialchars($v['vehicle_id']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (!empty($results['customers'])): ?>
                <section>
                    <h2>Customers Found</h2>
                    <ul>
                        <?php foreach ($results['customers'] as $c): ?>
                            <li>
                                <a href="<?= BASE_URL ?>modules/customers/customer-view.php?id=<?= $c['customer_id'] ?>">
                                    <strong><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></strong>
                                    (Code: <?= htmlspecialchars($c['customer_code']) ?>) - <?= htmlspecialchars($c['email']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (!empty($results['rentals'])): ?>
                <section>
                    <h2>Rentals Found</h2>
                    <ul>
                        <?php foreach ($results['rentals'] as $r): ?>
                            <li>
                                <a href="<?= BASE_URL ?>modules/rentals/view.php?id=<?= $r['agreement_id'] ?>">
                                    <strong>Agreement: <?= htmlspecialchars($r['agreement_number']) ?></strong>
                                    - Customer: <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>
                                    (Vehicle: <?= htmlspecialchars($r['plate_number']) ?>)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (!empty($results['procurement'])): ?>
                <section>
                    <h2>Procurement Requests Found</h2>
                    <ul>
                        <?php foreach ($results['procurement'] as $p): ?>
                            <li>
                                <a href="<?= BASE_URL ?>modules/procurement/pr-view.php?id=<?= $p['pr_id'] ?>">
                                    <strong>PR: <?= htmlspecialchars($p['pr_number']) ?></strong>
                                    - Status: <?= ucfirst(htmlspecialchars($p['status'])) ?>
                                    <?php if (!empty($p['purpose'])): ?> - Purpose:
                                        <?= htmlspecialchars($p['purpose']) ?>                <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>