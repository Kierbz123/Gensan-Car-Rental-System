<?php
// modules/documents/index.php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';
require_once '../../classes/DocumentManager.php';
if (!$authUser->hasPermission('documents.view') && $authUser->getData()['role'] !== 'system_admin') {
    // If you don't have a specific documents.view permission yet, fallback or grant to admins/managers
    if (!in_array($authUser->getData()['role'], ['system_admin', 'fleet_manager', 'customer_service_staff'])) {
        header("Location: " . BASE_URL . "/modules/dashboard/");
        exit;
    }
}

// Handle archive action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_document_id'])) {
    // Basic CSRF handling usually done here
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
    'date_to' => $_GET['date_to'] ?? ''
];

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 25;

$results = DocumentManager::searchDocuments($filters, $page, $perPage);
$documents = $results['data'];
$totalPages = $results['total_pages'];
$totalCount = $results['total'];

$pageTitle = "Document Repository";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div
                style="width:48px;height:48px;background:var(--secondary-light);border:1px solid var(--border-color);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--text-secondary);">
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

<div class="card">
    <div class="card-header" style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--secondary-50);">
        <div class="card-header-filters" style="width: 100%;">
            <form method="GET" class="card-header-form" style="display: flex; gap: 0.5rem; flex-wrap: wrap; width: 100%; align-items: center;">
                
                <div style="flex: 1; min-width: 200px; position: relative;">
                    <i data-lucide="search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: var(--text-muted);"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                           class="form-control" placeholder="Search title or ID..." style="padding-left: 32px; width: 100%;">
                </div>
                
                <select name="entity_type" class="form-control" style="width: 150px; font-weight: 600;">
                    <option value="">All Entities</option>
                    <option value="customer" <?php echo $filters['entity_type'] === 'customer' ? 'selected' : ''; ?>>Customers</option>
                    <option value="vehicle" <?php echo $filters['entity_type'] === 'vehicle' ? 'selected' : ''; ?>>Vehicles</option>
                    <option value="rental_agreement" <?php echo $filters['entity_type'] === 'rental_agreement' ? 'selected' : ''; ?>>Rentals</option>
                    <option value="driver" <?php echo $filters['entity_type'] === 'driver' ? 'selected' : ''; ?>>Drivers</option>
                </select>
                
                <input type="text" name="entity_id" value="<?php echo htmlspecialchars($filters['entity_id']); ?>" 
                       class="form-control" placeholder="Entity ID..." style="width: 120px; font-weight: 600;">
                
                <select name="category" class="form-control" style="width: 150px; font-weight: 600;">
                    <option value="">All Categories</option>
                    <option value="identity" <?php echo $filters['category'] === 'identity' ? 'selected' : ''; ?>>Identity</option>
                    <option value="contract" <?php echo $filters['category'] === 'contract' ? 'selected' : ''; ?>>Contracts</option>
                    <option value="insurance" <?php echo $filters['category'] === 'insurance' ? 'selected' : ''; ?>>Insurance</option>
                    <option value="registration" <?php echo $filters['category'] === 'registration' ? 'selected' : ''; ?>>Registration</option>
                    <option value="permit" <?php echo $filters['category'] === 'permit' ? 'selected' : ''; ?>>Permits</option>
                    <option value="other" <?php echo $filters['category'] === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                
                <div style="display: flex; gap: 0.25rem; align-items: center;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-right: 4px;">FROM</span>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" class="form-control" style="width: 140px;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin: 0 4px;">TO</span>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" class="form-control" style="width: 140px;">
                </div>
                
                <div style="display: flex; gap: 0.25rem; margin-left: auto;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.25rem; font-weight: 700;">
                        <i data-lucide="filter" style="width: 14px; height: 14px; margin-right: 6px;"></i> Filter
                    </button>
                    <a href="index.php" class="btn btn-ghost" style="padding: 0.5rem; display: flex; align-items: center; justify-content: center;" title="Clear Filters">
                        <i data-lucide="rotate-ccw" style="width: 18px; height: 18px;"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="table-container" style="border:none;">
        <?php if (count($documents) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Entity</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <div style="width:36px;height:36px;background:var(--accent-light);color:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                    <?php 
                                        $icon = 'file-text';
                                        if (strpos($doc['file_type'], 'image') !== false) $icon = 'image';
                                        else if (strpos($doc['file_type'], 'pdf') !== false) $icon = 'file-type-pdf';
                                    ?>
                                    <i data-lucide="<?php echo $icon; ?>" style="width:18px;height:18px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:0.875rem;color:var(--text-main);">
                                        <?php echo htmlspecialchars($doc['title']); ?>
                                    </div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                                        ID: #<?php echo $doc['document_id']; ?> &bull; <span style="text-transform:uppercase;"><?php echo htmlspecialchars($doc['document_category']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info" style="font-family:monospace;">
                                <?php echo htmlspecialchars($doc['entity_type']); ?>: <?php echo htmlspecialchars($doc['entity_id']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size:0.75rem;color:var(--text-secondary);font-weight:600;">
                                <?php 
                                    $ext = pathinfo($doc['file_path'], PATHINFO_EXTENSION); 
                                    echo strtoupper($ext ?: 'FILE'); 
                                ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:0.75rem;color:var(--text-secondary);font-weight:600;">
                                <?php echo round($doc['file_size'] / 1024, 1); ?> KB
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:0.8rem;">
                                <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--text-muted);">
                                by <?php echo htmlspecialchars($doc['uploader_name']); ?>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.25rem;justify-content:flex-end;">
                                <a href="serve.php?id=<?php echo $doc['document_id']; ?>" target="_blank" class="btn btn-ghost btn-sm" title="View">
                                    <i data-lucide="eye" style="width:16px;height:16px;"></i>
                                </a>
                                <a href="serve.php?id=<?php echo $doc['document_id']; ?>&download=1" class="btn btn-ghost btn-sm" title="Download">
                                    <i data-lucide="download" style="width:16px;height:16px;"></i>
                                </a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Archive this document? It will be hidden from view.');">
                                    <input type="hidden" name="archive_document_id" value="<?php echo $doc['document_id']; ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Archive" style="color:var(--danger);">
                                        <i data-lucide="archive" style="width:16px;height:16px;"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div style="padding:3rem 1rem;text-align:center;color:var(--text-muted);">
                <div style="width:64px;height:64px;background:var(--secondary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <i data-lucide="folder-search" style="width:32px;height:32px;color:var(--text-secondary);"></i>
                </div>
                <h3 style="font-size:1.125rem;font-weight:700;color:var(--text-main);margin:0 0 0.5rem;">No documents found</h3>
                <p style="margin:0;font-size:0.875rem;">Try adjusting your filters or upload a new document from an entity profile.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div style="padding:1rem 1.5rem;background:var(--secondary-light);border-top:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;">
                Showing <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $totalCount)); ?> of <?php echo number_format($totalCount); ?>
            </div>
            <div style="display:flex;gap:4px;align-items:center;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>" class="btn btn-ghost btn-sm">Prev</a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                    ?>
                    <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))); ?>" class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-ghost'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>" class="btn btn-ghost btn-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
