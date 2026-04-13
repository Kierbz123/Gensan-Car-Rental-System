<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
$db = Database::getInstance();

$pageTitle = "Regulatory Compliance";
require_once '../../includes/header.php';

$authUser->requirePermission('compliance.view');

$sort = $_GET['sort'] ?? 'urgency';

try {
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_records,
            COALESCE(SUM(CASE WHEN expiry_date > DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) as total_active,
            COALESCE(SUM(CASE WHEN expiry_date < CURRENT_DATE() THEN 1 ELSE 0 END), 0) as expired,
            COALESCE(SUM(CASE WHEN expiry_date >= CURRENT_DATE() AND expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) as expiring_soon
        FROM compliance_records c
        WHERE status != 'renewed' AND status != 'cancelled'
          AND expiry_date IS NOT NULL
          AND expiry_date != '0000-00-00'
          AND record_id = (
              SELECT MAX(record_id)
              FROM compliance_records c2
              WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
          )
    ");

    $totalRecords = $stats['total_records'] ?: 1; // Prevent division by zero
    $systemicScore = round(($stats['total_active'] / $totalRecords) * 100);

    $orderClause = "ORDER BY CASE WHEN c.expiry_date < CURRENT_DATE() THEN 1 ELSE 2 END ASC, c.expiry_date ASC";
    if ($sort === 'expiry') {
        $orderClause = "ORDER BY c.expiry_date ASC";
    } elseif ($sort === 'vehicle') {
        $orderClause = "ORDER BY v.brand ASC, v.model ASC, c.expiry_date ASC";
    } elseif ($sort === 'type') {
        $orderClause = "ORDER BY c.compliance_type ASC, c.expiry_date ASC";
    }

    $items = $db->fetchAll("
        SELECT c.*, v.plate_number, v.brand, v.model
        FROM compliance_records c
        JOIN vehicles v ON c.vehicle_id = v.vehicle_id
        WHERE c.status NOT IN ('renewed', 'cancelled')
        AND c.expiry_date IS NOT NULL
          AND c.expiry_date != '0000-00-00'
          AND c.expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
          AND c.record_id = (
              SELECT MAX(record_id) 
              FROM compliance_records c2 
              WHERE c2.vehicle_id = c.vehicle_id AND c2.compliance_type = c.compliance_type
          )
        $orderClause
    ");
} catch (Exception $e) {
    $stats = ['total_active' => 0, 'expired' => 0, 'expiring_soon' => 0];
    $items = [];
    $systemicScore = 100;
}
?>

<div class="page-header">
    <div class="page-title">
        <h1>Regulatory Compliance</h1>
        <p>Franchise validity, insurance coverage, and statutory vehicle documentation.</p>
    </div>
    <div class="page-actions">
        <?php if ($authUser->hasPermission('compliance.create')): ?>
            <a href="renew-upload.php" class="btn btn-primary">
                <i data-lucide="upload-cloud" style="width:16px;height:16px;"></i> Archive Instrument
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- The stats grid seems fine where it is structurally, but the icons and labels are not matching properly -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon danger"><i data-lucide="shield-alert" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['expired'] ?? 0 ?></div>
        <div class="stat-label">Breached Instruments</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon warning"><i data-lucide="timer" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['expiring_soon'] ?? 0 ?></div>
        <div class="stat-label">30-Day Critical</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon primary"><i data-lucide="file-check" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $stats['total_active'] ?? 0 ?></div>
        <div class="stat-label">Valid Instruments</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon success"><i data-lucide="award" style="width:20px;height:20px;"></i></div>
        <div class="stat-value"><?= $systemicScore ?>%</div>
        <div class="stat-label">Systemic Score</div>
    </div>
</div>

<div class="card mt-8">
    <div class="card-header">
        <h2 class="card-title flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-warning-500"></i> Critical Watchlist
        </h2>
        <div class="card-header-filters">
            <form method="GET" class="card-header-form w-full flex justify-end">
                <select name="sort" onchange="this.form.submit()" class="form-control form-control--inline">
                    <option value="urgency" <?= $sort === 'urgency' ? 'selected' : '' ?>>Urgency (Breached First)</option>
                    <option value="expiry" <?= $sort === 'expiry' ? 'selected' : '' ?>>Earliest Expiry</option>
                    <option value="type" <?= $sort === 'type' ? 'selected' : '' ?>>Instrument Type</option>
                    <option value="vehicle" <?= $sort === 'vehicle' ? 'selected' : '' ?>>Vehicle Target</option>
                </select>
                <div class="card-header-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="?" class="btn btn-ghost btn-sm" title="Reset Filters"><i data-lucide="rotate-ccw"
                            class="w-4 h-4"></i></a>
                </div>
            </form>
        </div>
    </div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Vehicle Target</th>
                    <th>Instrument Type</th>
                    <th>Reference #</th>
                    <th>Expiry Horizon</th>
                    <th>State</th>
                    <th style="flex: 0 0 100px; justify-content: flex-end; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)):
                    foreach ($items as $item):
                        $hasExp   = !empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00';
                        $diff     = $hasExp ? (int) ceil((strtotime($item['expiry_date']) - time()) / (60 * 60 * 24)) : null;
                        $badgeCls = (!$hasExp || $diff >= 0) ? 'badge-warning' : 'badge-danger';
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    <?= htmlspecialchars($item['plate_number']) ?>
                                </div>
                            </td>
                            <td>
                                <span
                                    class="badge badge-secondary"><?= strtoupper(str_replace('_', ' ', $item['compliance_type'])) ?></span>
                            </td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($item['document_number'] ?? 'N/A') ?></td>
                            <td style="font-weight:600; color:<?= (!$hasExp || $diff >= 0) ? 'var(--text-main)' : 'var(--danger)' ?>;">
                                <?= $hasExp ? date('M d, Y', strtotime($item['expiry_date'])) : '<em style="font-weight:400;opacity:.6">No expiry set</em>' ?>
                                <div style="font-size:10px;">
                                    <?= !$hasExp ? 'Awaiting upload' : ($diff < 0 ? abs($diff) . ' days lapsed' : $diff . ' days left') ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $badgeCls ?>"><?= (!$hasExp) ? 'PENDING' : ($diff < 0 ? 'BREACHED' : 'EXPIRING') ?></span>
                            </td>
                            <td>
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="instrument-view.php?id=<?= $item['record_id'] ?>"
                                        class="btn btn-ghost btn-sm">Inspect</a>
                                    <?php if ($authUser->hasPermission('compliance.create')): ?>
                                        <a href="renew-upload.php?vehicle_id=<?= $item['vehicle_id'] ?>&type=<?= urlencode($item['compliance_type']) ?>"
                                            class="btn btn-<?= $diff < 0 ? 'danger' : 'warning' ?> btn-sm">
                                            Renew
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:0.5rem;">
                                <i data-lucide="shield-check" style="width:48px;height:48px;opacity:0.5;"></i>
                                <span style="font-weight:600;">No critical exposures detected.</span>
                                <span>All compliance instruments are valid.</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    lucide.createIcons();
</script>

<?php require_once '../../includes/footer.php'; ?>
