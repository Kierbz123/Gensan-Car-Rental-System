-- =============================================================================
-- Migration 004: Spare Parts Inventory Management
-- Gensan Car Rental System
-- Run once against: gensan_car_rental_db
-- =============================================================================

-- -------------------------------------------------------
-- 1. Parts inventory master ledger
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parts_inventory` (
  `inventory_id`     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_code`        VARCHAR(30)      NOT NULL COMMENT 'INV-XXXX',
  `item_name`        VARCHAR(255)     NOT NULL,
  `item_category`    ENUM('parts','supplies','fuel','others') DEFAULT 'parts',
  `unit`             VARCHAR(20)      NOT NULL DEFAULT 'pcs' COMMENT 'pcs, liters, kg, etc.',
  `quantity_on_hand` DECIMAL(10,3)    NOT NULL DEFAULT 0.000,
  `reorder_level`    DECIMAL(10,3)    NOT NULL DEFAULT 0.000 COMMENT 'Alert when stock ≤ this',
  `unit_cost`        DECIMAL(10,2)    DEFAULT NULL COMMENT 'Last purchase unit price',
  `supplier_id`      INT(10) UNSIGNED DEFAULT NULL,
  `storage_location` VARCHAR(100)     DEFAULT 'Main Garage',
  `notes`            TEXT             DEFAULT NULL,
  `created_by`       INT(10) UNSIGNED DEFAULT NULL,
  `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `idx_category` (`item_category`),
  KEY `idx_qty` (`quantity_on_hand`),
  CONSTRAINT `pi_supplier_fk` FOREIGN KEY (`supplier_id`)  REFERENCES `suppliers`(`supplier_id`) ON DELETE SET NULL,
  CONSTRAINT `pi_user_fk`     FOREIGN KEY (`created_by`)   REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Spare parts and supplies stock master';

-- -------------------------------------------------------
-- 2. Transaction ledger (double-entry stock movements)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `txn_id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_id`   INT(10) UNSIGNED NOT NULL,
  `txn_type`       ENUM('receipt','consumption','adjustment','write_off') NOT NULL,
  `quantity`       DECIMAL(10,3)    NOT NULL COMMENT 'Positive = in, Negative = out',
  `balance_after`  DECIMAL(10,3)    NOT NULL,
  `unit_cost`      DECIMAL(10,2)    DEFAULT NULL,
  `reference_type` ENUM('procurement','maintenance','manual') DEFAULT 'manual',
  `reference_id`   INT(10) UNSIGNED DEFAULT NULL COMMENT 'pr_id or log_id',
  `notes`          TEXT             DEFAULT NULL,
  `created_by`     INT(10) UNSIGNED DEFAULT NULL,
  `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`txn_id`),
  KEY `idx_inventory`  (`inventory_id`),
  KEY `idx_type`       (`txn_type`),
  KEY `idx_created`    (`created_at`),
  CONSTRAINT `invtxn_item_fk` FOREIGN KEY (`inventory_id`) REFERENCES `parts_inventory`(`inventory_id`) ON DELETE CASCADE,
  CONSTRAINT `invtxn_user_fk` FOREIGN KEY (`created_by`)   REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Double-entry inventory ledger';

-- -------------------------------------------------------
-- 3. Link procurement_items to inventory (nullable FK)
-- -------------------------------------------------------
ALTER TABLE `procurement_items`
  ADD COLUMN IF NOT EXISTS `inventory_id` INT(10) UNSIGNED DEFAULT NULL
    COMMENT 'Links to parts_inventory when item is received'
    AFTER `vehicle_id`;

SET @fk_exists2 = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME       = 'procurement_items'
    AND CONSTRAINT_NAME  = 'pi_link_fk'
    AND CONSTRAINT_TYPE  = 'FOREIGN KEY'
);
SET @sql2 = IF(@fk_exists2 = 0,
  'ALTER TABLE `procurement_items` ADD CONSTRAINT `pi_link_fk` FOREIGN KEY (`inventory_id`) REFERENCES `parts_inventory`(`inventory_id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- -------------------------------------------------------
-- 4. Seed: sample inventory items (idempotent)
-- -------------------------------------------------------
INSERT IGNORE INTO `parts_inventory`
  (`item_code`, `item_name`, `item_category`, `unit`, `quantity_on_hand`, `reorder_level`, `unit_cost`, `storage_location`)
VALUES
  ('INV-0001', 'Engine Oil (10W-40)',      'supplies', 'liters', 20.000, 5.000, 250.00, 'Storage Room A'),
  ('INV-0002', 'Oil Filter',               'parts',    'pcs',    10.000, 3.000, 180.00, 'Storage Room A'),
  ('INV-0003', 'Air Filter',               'parts',    'pcs',    8.000,  2.000, 350.00, 'Storage Room A'),
  ('INV-0004', 'Windshield Washer Fluid',  'supplies', 'liters', 15.000, 4.000,  75.00, 'Storage Room B'),
  ('INV-0005', 'Tire Pressure Gauge',      'parts',    'pcs',    3.000,  1.000, 450.00, 'Tool Cabinet');
