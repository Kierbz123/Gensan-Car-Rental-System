<?php
require_once 'config/config.php';
$db = Database::getInstance();

$sql = "
CREATE TABLE IF NOT EXISTS compliance_records (
    record_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id VARCHAR(20) NOT NULL,
    compliance_type ENUM('lto_registration', 'insurance_comprehensive', 'insurance_tpl', 'emission_test', 'franchise_ltfrb', 'pnp_clearance', 'mayors_permit') NOT NULL,
    
    -- Document details
    document_number VARCHAR(50) NOT NULL,
    issuing_authority VARCHAR(100),
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    
    -- Cost
    renewal_cost DECIMAL(10,2),
    
    -- Status (Removed GENERATED ALWAYS AS for compatibility check, using simple column or view if needed)
    -- But schema suggests it, let's try exactly as in schema.sql but check first
    
    status ENUM('active', 'expired', 'renewal_pending', 'renewed', 'cancelled') DEFAULT 'active',
    days_until_expiry INT DEFAULT 0,
    
    -- Document file
    document_file_path VARCHAR(255),
    
    -- Renewal tracking
    renewal_reminder_sent BOOLEAN DEFAULT FALSE,
    renewal_reminder_sent_at DATETIME,
    renewed_record_id INT UNSIGNED COMMENT 'Link to new record after renewal',
    
    notes TEXT,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_type (compliance_type),
    INDEX idx_expiry (expiry_date),
    INDEX idx_status (status),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (renewed_record_id) REFERENCES compliance_records(record_id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Vehicle compliance and registration records';
";

try {
    $db->getConnection()->exec($sql);
    echo "Table compliance_records created or already exists.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>