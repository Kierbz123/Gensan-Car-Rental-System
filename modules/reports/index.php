<?php
require_once '../../config/config.php';
require_once '../../includes/session-manager.php';

$authUser->requirePermission('reports.view');

$pageTitle = "Analytics Portal";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Analytics Portal</h1>
        <p>Synthesizing raw data into actionable operational insights.</p>
    </div>
    <div class="page-actions">
        <a href="../settings/index.php" class="btn btn-secondary">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Settings
        </a>
    </div>
</div>

<div class="grid-2">
    <!-- Fleet Analytics -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Fleet Analytics</h2>
        </div>
        <div class="card-body">
            <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">Utilization trajectories, fuel
                efficiency, and uptime technicals.</p>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                <a href="fleet-utilization.php" class="btn btn-secondary btn-sm"
                    style="text-align:left; justify-content:space-between;">
                    Utilization Map <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </a>
                <a href="fuel-consumption.php" class="btn btn-secondary btn-sm"
                    style="text-align:left; justify-content:space-between;">
                    Energy Metrics <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Fiscal Intelligence -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Fiscal Intelligence</h2>
        </div>
        <div class="card-body">
            <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">Revenue streams, OPEX ledger,
                and profitability indices.</p>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                <a href="revenue-summary.php" class="btn btn-secondary btn-sm"
                    style="text-align:left; justify-content:space-between;">
                    Income Statement <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </a>
                <a href="expense-tracking.php" class="btn btn-secondary btn-sm"
                    style="text-align:left; justify-content:space-between;">
                    Expense Tracking <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Supply Chain -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Supply Chain</h2>
        </div>
        <div class="card-body">
            <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">Vendor reliability,
                procurement velocity, and auditing.</p>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                <a href="vendor-performance.php" class="btn btn-secondary btn-sm"
                    style="text-align:left; justify-content:space-between;">
                    Vendor Analysis <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </a>
                <a href="procurement-history.php" class="btn btn-secondary btn-sm"
                    style="text-align:left; justify-content:space-between;">
                    Procurement Log <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Safety & Audit -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Safety & Audit</h2>
        </div>
        <div class="card-body">
            <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">Event forensics, integrity
                verification, and breaches.</p>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                <a href="audit-logs.php" class="btn btn-secondary btn-sm"
                    style="text-align:left; justify-content:space-between;">
                    Event Forensics <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </a>
                <a href="compliance-status.php" class="btn btn-secondary btn-sm"
                    style="text-align:left; justify-content:space-between;">
                    Statutory Matrix <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:2rem; background:var(--primary); color:white; border:none; padding:2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h3 style="font-size:1.25rem; font-weight:700; margin-bottom:0.5rem;">Instant Intelligence Report</h3>
            <p style="opacity:0.8; font-size:0.875rem;">Generate a vectorized PDF snapshot of fleet health and active
                contracts.</p>
        </div>
        <a href="export/export-pdf.php?type=fleet_utilization" target="_blank" class="btn btn-secondary">
            <i data-lucide="file-text" style="width:16px;height:16px;"></i> Generate PDF
        </a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>