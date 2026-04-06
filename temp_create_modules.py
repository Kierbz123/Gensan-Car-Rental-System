import os

def create_boilerplate(path, title, description, level=2):
    prefix = '../' * level
    content = f'''<?php
/**
 * Module Component: {title}
 * High-Premium SaaS Dashboard Implementation
 */

require_once '{prefix}config/config.php';
require_once '{prefix}includes/auth-check.php';

$pageTitle = "{title}";
require_once '{prefix}includes/header.php';
?>

<div class="page-header mb-8 flex justify-between items-center">
    <div class="animate-slide-up">
        <h1 class="text-3xl font-black text-secondary-900 mb-2">{title}</h1>
        <p class="text-secondary-500 font-medium">{description}</p>
    </div>
    <div class="flex gap-3">
        <a href="index.php" class="btn btn-secondary px-6 py-3 rounded-2xl font-bold flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back to Registry
        </a>
    </div>
</div>

<div class="grid grid-cols-1 gap-8 animate-fade-in">
    <div class="card p-12 bg-white rounded-[2.5rem] border border-secondary-100 shadow-sm flex flex-col items-center justify-center text-center">
        <div class="w-20 h-20 bg-primary-50 text-primary-600 rounded-3xl flex items-center justify-center mb-6">
            <i data-lucide="layers" class="w-10 h-10"></i>
        </div>
        <h2 class="text-2xl font-black text-secondary-900 mb-2">Terminal Interface Offline</h2>
        <p class="text-secondary-400 max-w-md mx-auto mb-8">
            The {title} interface is currently under technical orchestration. Our engineering team is finalizing the high-velocity data bindings.
        </p>
        <div class="flex gap-4">
            <div class="badge badge-primary px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest">v3.1.0-ULTIMA</div>
            <div class="badge badge-secondary px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest">STABLE_BETA</div>
        </div>
    </div>
</div>

<?php require_once '{prefix}includes/footer.php'; ?>
'''
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w') as f:
        f.write(content)

base_dir = r'c:\xampp\htdocs\IATPS\gensan-car-rental-system\modules'

files_to_create = [
    # Procurement
    ('procurement/pr-create.php', 'Create Purchase Requisition', 'Initialize a new procurement request flow.'),
    ('procurement/pr-view.php', 'View Requisition Details', 'Technical analysis of procurement request profile.'),
    ('procurement/pr-edit.php', 'Edit Requisition', 'Modify existing procurement credentials.'),
    ('procurement/pr-approve.php', 'PR Approval Terminal', 'Authorization gateway for procurement requests.'),
    ('procurement/pr-reject.php', 'PR Rejection Interface', 'Formalize procurement request termination.'),
    ('procurement/po-generate.php', 'Generate Purchase Order', 'Synchronize PR data with official PO documentation.'),
    ('procurement/suppliers/index.php', 'Supplier Registry', 'Manage enterprise vendor relationships.', 3),
    ('procurement/suppliers/supplier-add.php', 'Add New Supplier', 'Register a new vendor in the system ecosystem.', 3),
    ('procurement/suppliers/supplier-edit.php', 'Edit Supplier Profile', 'Update vendor identity and compliance data.', 3),
    ('procurement/suppliers/supplier-view.php', 'Vendor Intelligence', 'Deep-dive into supplier performance and history.', 3),

    # Maintenance
    ('maintenance/schedule.php', 'Service Scheduler', 'Orchestrate future fleet maintenance events.'),
    ('maintenance/preventive-schedule.php', 'Preventive Maintenance Plan', 'Configure automated maintenance cycles based on telemetry.'),
    ('maintenance/service-record-add.php', 'Register Service Event', 'Log new maintenance activity and resource allocation.'),
    ('maintenance/service-view.php', 'Technical Service Record', 'Comprehensive audit of maintenance execution.'),

    # Customers
    ('customers/customer-add.php', 'Register New Customer', 'Initialize new client identity profile.'),
    ('customers/customer-edit.php', 'Update Customer Metadata', 'Modify existing client registry information.'),
    ('customers/customer-view.php', 'Customer Intelligence', '360-degree view of client history and risk profile.'),
    ('customers/rental-agreement-create.php', 'Initialize Rental Contract', 'Process new vehicle deployment documentation.'),
    ('customers/rental-agreement-view.php', 'View Rental Agreement', 'Auditable digital contract profile.'),
    ('customers/damage-report.php', 'Asset Damage Documentation', 'Technical reporting of vehicle integrity breaches.'),

    # Rentals
    ('rentals/rental-create.php', 'New Rental Process', 'Streamlined workflow for vehicle dispatch.'),
    ('rentals/rental-view.php', 'Rental Transaction Profile', 'Complete audit trail of rental lifecycle.'),
    ('rentals/rental-edit.php', 'Modify Rental Parameters', 'Adjust active rental session configuration.'),
    ('rentals/check-out.php', 'Departure Protocol', 'Initiate asset release and checkout procedure.'),
    ('rentals/check-in.php', 'Arrival Protocol', 'Execute vehicle return and integrity audit.'),

    # Compliance
    ('compliance/vehicle-compliance.php', 'Fleet Compliance Audit', 'Real-time monitoring of regulatory status.'),
    ('compliance/document-upload.php', 'Regulatory Document Repository', 'Secure upload for LTO and insurance certificates.'),
    ('compliance/renewal-scheduler.php', 'Compliance Renewal Plan', 'Predictive scheduling for legal document updates.'),

    # Reports
    ('reports/fleet-utilization.php', 'Fleet Utilization Analytics', 'High-velocity telemetry on asset efficiency.'),
    ('reports/maintenance-costs.php', 'Maintenance Financial Analysis', 'Detailed breakdown of fleet upkeep expenditures.'),
    ('reports/procurement-summary.php', 'Procurement Velocity Report', 'Summary of system-wide resource acquisition.'),
    ('reports/customer-analytics.php', 'Customer Behavioral Insight', 'Data-driven analysis of client engagement and revenue.')
]

for rel_path, title, desc, *lvl in files_to_create:
    full_path = os.path.join(base_dir, rel_path)
    level = lvl[0] if lvl else 2
    create_boilerplate(full_path, title, desc, level)
    print(f"Created: {rel_path}")

