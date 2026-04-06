-- =============================================================================
-- Migration 003: Chauffeur / Driver Management
-- Gensan Car Rental System
-- Run once against: gensan_car_rental_db
-- =============================================================================

-- -------------------------------------------------------
-- 1. Drivers table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `drivers` (
  `driver_id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_code`      VARCHAR(20)      NOT NULL COMMENT 'DRV-XXXX format',
  `first_name`         VARCHAR(50)      NOT NULL,
  `last_name`          VARCHAR(50)      NOT NULL,
  `phone`              VARCHAR(20)      NOT NULL,
  `email`              VARCHAR(100)     DEFAULT NULL,
  `license_number`     VARCHAR(50)      NOT NULL,
  `license_expiry`     DATE             NOT NULL,
  `license_type`       ENUM('professional','non_professional') DEFAULT 'professional',
  `status`             ENUM('available','on_duty','off_duty','suspended') DEFAULT 'available',
  `profile_photo_path` VARCHAR(255)     DEFAULT NULL,
  `notes`              TEXT             DEFAULT NULL,
  `created_by`         INT(10) UNSIGNED DEFAULT NULL,
  `created_at`         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`         DATETIME         DEFAULT NULL,
  PRIMARY KEY (`driver_id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `idx_status` (`status`),
  KEY `idx_license_expiry` (`license_expiry`),
  CONSTRAINT `drivers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Chauffeur and driver profiles';

-- -------------------------------------------------------
-- 2. Add rental_type and driver columns to rental_agreements
--    (uses IF NOT EXISTS-equivalent guard via ALTER IGNORE)
-- -------------------------------------------------------
ALTER TABLE `rental_agreements`
  ADD COLUMN IF NOT EXISTS `rental_type`    ENUM('self_drive','chauffeur') NOT NULL DEFAULT 'self_drive'
    COMMENT 'Rental mode: self-drive or chauffeur-driven'
    AFTER `return_location`,
  ADD COLUMN IF NOT EXISTS `driver_id`      INT(10) UNSIGNED DEFAULT NULL
    COMMENT 'Assigned driver (NULL for self-drive)'
    AFTER `rental_type`,
  ADD COLUMN IF NOT EXISTS `chauffeur_fee`  DECIMAL(10,2) NOT NULL DEFAULT 0.00
    COMMENT 'Daily chauffeur service charge'
    AFTER `driver_id`;

-- Add FK only if it does not already exist
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME       = 'rental_agreements'
    AND CONSTRAINT_NAME  = 'ra_driver_fk'
    AND CONSTRAINT_TYPE  = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `rental_agreements` ADD CONSTRAINT `ra_driver_fk` FOREIGN KEY (`driver_id`) REFERENCES `drivers`(`driver_id`) ON DELETE SET NULL',
  'SELECT 1 -- FK already exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -------------------------------------------------------
-- 3. Extend notifications.type enum to include new types
-- -------------------------------------------------------
ALTER TABLE `notifications`
  MODIFY COLUMN `type` ENUM(
    'maintenance_due','maintenance_overdue','compliance_expiring','compliance_expired',
    'pr_pending_approval','pr_approved','pr_rejected','vehicle_returned','new_rental',
    'system_alert','message','driver_license_expiring','overdue_vehicle_return',
    'low_stock_alert'
  ) NOT NULL;

-- -------------------------------------------------------
-- 4. Seed: insert two sample drivers (idempotent)
-- -------------------------------------------------------
INSERT IGNORE INTO `drivers`
  (`employee_code`, `first_name`, `last_name`, `phone`, `license_number`, `license_expiry`, `license_type`, `status`)
VALUES
  ('DRV-0001', 'Ramon', 'Dela Cruz', '09171234567', 'A01-23-456789', '2027-06-30', 'professional',     'available'),
  ('DRV-0002', 'Josefa', 'Santos',   '09281234567', 'B02-24-112233', '2026-12-15', 'non_professional', 'available');
