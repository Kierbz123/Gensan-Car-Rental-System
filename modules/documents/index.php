<?php
// modules/documents/index.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/DocumentManager.php';
// Access control: require documents.view permission OR elevated role
if (!$authUser->hasPermission('documents.view') &&
    !in_array($authUser->getData()['role'] ?? '', ['system_admin', 'fleet_manager', 'customer_service_staff'], true)) {
    header("Location: " . BASE_URL . "modules/dashboard/");
    exit;
}

// Handle archive action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_document_id'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid security token. Please try again.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: index.php');
        exit;
    }
    DocumentManager::archiveDocument((int)$_POST['archive_document_id'], $authUser->getId());
    $_SESSION['flash_message'] = "Document archived successfully.";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit;
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'entity_type' => $_GET['entity_type'] ?? '',
    'entity_id' => $_GET['entity_id'] ?? '',
    'category' => $_GET['category'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? '',
    'sort_order' => $_GET['sort_order'] ?? ''
];

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 25;

try {
    $stats = DocumentManager::getStats();
    $results = DocumentManager::searchDocuments($filters, $page, $perPage);
    $documents = $results['data'] ?? [];
    $totalPages = $results['total_pages'];
    $totalCount = $results['total'];

    $totalDocs = max(0, $stats['total_docs']);
    $storageSize = round(($stats['total_size'] ?? 0) / (1024 * 1024), 2); // To MB
    $totalContracts = $stats['total_contracts'] ?? 0;
    $totalIdentities = $stats['total_identities'] ?? 0;
} catch (Exception $e) {
    if (empty($_SESSION['error_message'])) {
        $_SESSION['error_message'] = "Failed to load document logic: " . $e->getMessage();
    }
    $stats = ['total_docs' => 0, 'total_size' => 0, 'total_contracts' => 0, 'total_identities' => 0];
    $documents = [];
    $totalDocs = 0; $storageSize = 0; $totalContracts = 0; $totalIdentities = 0;
    $totalPages = 1; $totalCount = 0;
}

$currentSortBy = $filters['sort_by'] ?? 'd.uploaded_at';
$currentSortOrder = $filters['sort_order'] ?? 'DESC';

function buildSortUrl($field, $currentSortBy, $currentSortOrder) {
    $order = ($currentSortBy === $field && strtoupper($currentSortOrder) === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort_by'] = $field;
    $params['sort_order'] = $order;
    unset($params['page']);
    return '?' . http_build_query($params);
}

function getSortIcon($field, $currentSortBy, $currentSortOrder) {
    if ($currentSortBy === $field) {
        $iconName = strtoupper($currentSortOrder) === 'ASC' ? 'chevron-up' : 'chevron-down';
        return '<i data-lucide="' . $iconName . '" style="width:14px;height:14px;display:inline-block;vertical-align:middle;margin-left:4px;"></i>';
    }
    return '';
}

function buildStatUrl($categoryValue) {
    $params = $_GET;
    if ($categoryValue !== null) {
        if (!empty($params['category']) && $params['category'] === $categoryValue) {
            unset($params['category']);
        } else {
            $params['category'] = $categoryValue;
        }
    } else {
        unset($params['category']);
    }
    unset($params['page']);
    return '?' . http_build_query($params);
}

$pageTitle = "Document Repository";
require_once '../../includes/header.php';
?>

<style>
.stat-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.stat-card.active {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 1px var(--primary-color) !important;
}
.sortable-header {
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    user-select: none;
}
.sortable-header:hover {
    color: var(--primary-color);
}
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}
.empty-state-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    opacity: 0.5;
}
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div style="width:48px;height:48px;background:var(--secondary-light);border:1px solid var(--border-color);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);">
                <i data-lucide="folder-open" style="width:24px;height:24px;"></i>
            </div>
            <div>
                <h1 style="margin:0;font-size:1.5rem;font-weight:800;letter-spacing:-0.02em;">Document Repository</h1>
                <p style="margin:0;color:var(--text-muted);font-size:0.875rem;font-weight:600;">
                    Centralized system for managing digital records and contracts
                </p>
            </div>
        </div>
        <div class="page-actions" style="margin:0;">
            <a href="../compliance/index.php" class="btn btn-secondary">
                <i data-lucide="shield-check" style="width:16px;height:16px;"></i> Compliance Watchlist
            </a>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-grid" style="margin-bottom:1.5rem;">
    <a href="index.php" class="stat-card-link">
        <div class="stat-card <?= empty($_GET['category']) ? 'active' : '' ?>">
            <div class="stat-card-icon primary"><i data-lucide="files" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalDocs) ?></div>
                <div class="stat-label">Total Documents</div>
            </div>
        </div>
    </a>
    <div class="stat-card" style="cursor:default;">
        <div class="stat-card-icon info"><i data-lucide="hard-drive" style="width:20px;height:20px;"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $storageSize ?> MB</div>
            <div class="stat-label">Storage Consumed</div>
        </div>
    </div>
    <a href="<?= buildStatUrl('contract') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['category']) && $_GET['category'] === 'contract') ? 'active' : '' ?>">
            <div class="stat-card-icon warning"><i data-lucide="file-signature" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalContracts) ?></div>
                <div class="stat-label">Active Contracts</div>
            </div>
        </div>
    </a>
    <a href="<?= buildStatUrl('identity') ?>" class="stat-card-link">
        <div class="stat-card <?= (isset($_GET['category']) && $_GET['category'] === 'identity') ? 'active' : '' ?>">
            <div class="stat-card-icon success"><i data-lucide="fingerprint" style="width:20px;height:20px;"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalIdentities) ?></div>
                <div class="stat-label">Identity Files</div>
            </div>
        </div>
    </a>
</div>

<div class="card">
    <div style="padding:.875rem 1.25rem; border-bottom:1px solid var(--border-color);">
        <form method="GET" id="filterForm" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;width:100%;">
            
            <!-- Search -->
            <div style="position:relative;flex:1;min-width:200px;">
                <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
                <input type="text" name="search" id="searchInput" class="form-control" style="padding-left:34px;width:100%;" placeholder="Search title or ID..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>

            <select name="entity_type" class="form-control" style="width:auto;flex-shrink:0;">
                <option value="">All Entities</option>
                <option value="customer" <?= $filters['entity_type'] === 'customer' ? 'selected' : '' ?>>Customers</option>
                <option value="vehicle" <?= $filters['entity_type'] === 'vehicle' ? 'selected' : '' ?>>Vehicles</option>
                <option value="rental_agreement" <?= $filters['entity_type'] === 'rental_agreement' ? 'selected' : '' ?>>Rentals</option>
                <option value="driver" <?= $filters['entity_type'] === 'driver' ? 'selected' : '' ?>>Drivers</option>
            </select>

            <?php if (!empty($_GET['category'])): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($_GET['category']) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['sort_by'])): ?>
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($_GET['sort_by']) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order']) ?>">
            <?php endif; ?>

            <div style="display:flex;gap:.5rem;flex-shrink:0;">
                <button type="submit" class="btn btn-primary btn-sm" id="applyFilterBtn">
                    <i data-lucide="search" style="width:13px;height:13px;"></i> Search
                </button>
                <?php if (!empty($_GET['search']) || !empty($_GET['category']) || !empty($_GET['entity_type']) || !empty($_GET['sort_by'])): ?>
                    <a href="index.php" class="btn btn-ghost btn-sm" title="Clear Filters"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></a>
                <?php endif; ?>
            </div>

            <!-- Result count -->
            <span style="margin-left:auto;font-size:.8125rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0;">
                <?= number_format($totalCount) ?> document<?= $totalCount !== 1 ? 's' : '' ?>
            </span>
        </form>
    </div>
    
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>
                        <a href="<?= buildSortUrl('d.title', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Document <?= getSortIcon('d.title', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('d.entity_type', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Entity <?= getSortIcon('d.entity_type', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('d.document_category', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Type <?= getSortIcon('d.document_category', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('d.file_size', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Size <?= getSortIcon('d.file_size', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th>
                        <a href="<?= buildSortUrl('d.uploaded_at', $currentSortBy, $currentSortOrder) ?>" class="sortable-header">Uploaded <?= getSortIcon('d.uploaded_at', $currentSortBy, $currentSortOrder) ?></a>
                    </th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($documents)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i data-lucide="folder-search" class="empty-state-icon"></i>
                                <h3>No Documents Found</h3>
                                <p style="margin-bottom:1rem;">We couldn't find any uploaded documents matching your filter criteria.</p>
                                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    foreach ($documents as $doc): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <div style="width:36px;height:36px;background:var(--accent-light);color:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                    <?php 
                                        $icon = 'file-text';
                                        if (strpos($doc['file_type'], 'image') !== false) $icon = 'image';
                                        else if (strpos($doc['file_type'], 'pdf') !== false) $icon = 'file-type-pdf';
                                    ?>
                                    <i data-lucide="<?= $icon ?>" style="width:18px;height:18px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:0.875rem;color:var(--text-main);">
                                        <?= htmlspecialchars($doc['title']) ?>
                                    </div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                                        ID: #<?= $doc['document_id'] ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info" style="font-family:monospace; font-weight:700;">
                                <?= strtoupper(htmlspecialchars($doc['entity_type'])) ?>: <?= htmlspecialchars($doc['entity_id']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-secondary" style="text-transform:uppercase;">
                                <?= htmlspecialchars($doc['document_category']) ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size:0.75rem;color:var(--text-secondary);font-weight:600; font-family:monospace;">
                                <?= number_format(round($doc['file_size'] / 1024, 1), 1) ?> KB
                            </div>
                            <div style="font-size:0.75rem;color:var(--text-muted); font-weight:600;">
                                <?= strtoupper(pathinfo($doc['file_path'], PATHINFO_EXTENSION) ?: 'FILE') ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:0.8rem;">
                                <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--text-muted);">
                                by <?= htmlspecialchars($doc['uploader_name']) ?>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.25rem;justify-content:flex-end;">
                                <a href="serve.php?id=<?= $doc['document_id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="View">
                                    <i data-lucide="eye" style="width:16px;height:16px;"></i>
                                </a>
                                <a href="serve.php?id=<?= $doc['document_id'] ?>&download=1" class="btn btn-ghost btn-sm" title="Download">
                                    <i data-lucide="download" style="width:16px;height:16px;"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Archive this document? It will be hidden from view.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="archive_document_id" value="<?= $doc['document_id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Archive" style="color:var(--danger);">
                                        <i data-lucide="archive" style="width:16px;height:16px;"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div style="padding:1rem 1.5rem;border-top:1px solid var(--border-color);display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
            <?php
                $paginationParams = $_GET;
                unset($paginationParams['page']);
                $baseQuery = http_build_query($paginationParams);
                for ($p = 1; $p <= $totalPages; $p++):
                    $sep = $baseQuery ? $baseQuery . '&' : '';
            ?>
                <a href="?<?= $sep ?>page=<?= $p ?>"
                    class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
if (isset($_SESSION['flash_message'])): ?>
    <div id="doc-toast"
        style="position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:center;gap:0.75rem;background:var(--<?= $_SESSION['flash_type'] === 'success' ? 'success' : 'danger' ?>);color:#fff;padding:0.875rem 1.25rem;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.18);font-size:0.9rem;font-weight:600;min-width:280px;max-width:380px;animation:toastSlideIn 0.35s cubic-bezier(.4,0,.2,1);">
        <i data-lucide="<?= $_SESSION['flash_type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;"><?= htmlspecialchars($_SESSION['flash_message']) ?></span>
        <button onclick="document.getElementById('doc-toast').remove()"
            style="background:none;border:none;cursor:pointer;color:#fff;padding:0;margin:0;display:flex;align-items:center;opacity:0.8;"
            aria-label="Dismiss">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    </div>
    <style>
        @keyframes toastSlideIn { from { opacity:0; transform:translateX(60px); } to { opacity:1; transform:translateX(0); } }
    </style>
    <script>
        setTimeout(function(){var t=document.getElementById('doc-toast');if(t){t.style.opacity='0';setTimeout(function(){t.remove();},400);}},3500);
    </script>
<?php 
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
endif; 
?>

<?php require_once '../../includes/footer.php'; ?>
