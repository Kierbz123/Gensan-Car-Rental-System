-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: gensan_car_rental_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `gensan_car_rental_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `gensan_car_rental_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `gensan_car_rental_db`;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL COMMENT 'Denormalized for history',
  `user_role` varchar(50) DEFAULT NULL,
  `action` enum('create','read','update','delete','login','logout','export','print','approve','reject','cancel','complete','other') NOT NULL,
  `module` varchar(50) NOT NULL COMMENT 'asset_tracking, procurement, etc.',
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` varchar(50) DEFAULT NULL COMMENT 'Primary key of affected record',
  `record_description` varchar(255) DEFAULT NULL COMMENT 'Human-readable identifier',
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `changed_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of field names that changed' CHECK (json_valid(`changed_fields`)),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_url` text DEFAULT NULL,
  `action_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  PRIMARY KEY (`log_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_module` (`module`),
  KEY `idx_table` (`table_name`),
  KEY `idx_record` (`record_id`),
  KEY `idx_timestamp` (`action_timestamp`),
  KEY `idx_severity` (`severity`),
  FULLTEXT KEY `idx_description` (`record_description`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='System audit trail';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `compliance_records`
--

DROP TABLE IF EXISTS `compliance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compliance_records` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` varchar(20) NOT NULL,
  `compliance_type` enum('lto_registration','insurance_comprehensive','insurance_tpl','emission_test','franchise_ltfrb','pnp_clearance','mayors_permit') NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `issuing_authority` varchar(100) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `renewal_cost` decimal(10,2) DEFAULT NULL,
  `status` enum('active','expired','renewal_pending','renewed','cancelled') DEFAULT 'active',
  `days_until_expiry` int(11) DEFAULT NULL,
  `document_file_path` varchar(255) DEFAULT NULL,
  `renewal_reminder_sent` tinyint(1) DEFAULT 0,
  `renewal_reminder_sent_at` datetime DEFAULT NULL,
  `renewed_record_id` int(10) unsigned DEFAULT NULL COMMENT 'Link to new record after renewal',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`record_id`),
  KEY `idx_vehicle` (`vehicle_id`),
  KEY `idx_type` (`compliance_type`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `renewed_record_id` (`renewed_record_id`),
  CONSTRAINT `compliance_records_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  CONSTRAINT `compliance_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `compliance_records_ibfk_3` FOREIGN KEY (`renewed_record_id`) REFERENCES `compliance_records` (`record_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Vehicle compliance and registration records';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `customer_rental_stats`
--

DROP TABLE IF EXISTS `customer_rental_stats`;
/*!50001 DROP VIEW IF EXISTS `customer_rental_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `customer_rental_stats` AS SELECT
 1 AS `customer_id`,
  1 AS `customer_code`,
  1 AS `customer_name`,
  1 AS `customer_type`,
  1 AS `total_rentals`,
  1 AS `total_spent`,
  1 AS `last_rental_date`,
  1 AS `completed_rentals`,
  1 AS `cancelled_rentals`,
  1 AS `avg_rental_days` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `customer_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(20) NOT NULL COMMENT 'CUST-XXXXX format',
  `customer_type` enum('walk_in','online','corporate','repeat','referral') DEFAULT 'walk_in',
  `profile_picture_path` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') DEFAULT NULL,
  `phone_primary` varchar(20) NOT NULL,
  `phone_secondary` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT 'General Santos City',
  `province` varchar(50) DEFAULT 'South Cotabato',
  `zip_code` varchar(10) DEFAULT NULL,
  `id_type` enum('drivers_license','passport','national_id','company_id') DEFAULT 'drivers_license',
  `id_number` varchar(50) DEFAULT NULL,
  `id_expiry_date` date DEFAULT NULL,
  `id_photo_front_path` varchar(255) DEFAULT NULL,
  `id_photo_back_path` varchar(255) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_phone` varchar(20) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `authorized_representative` varchar(100) DEFAULT NULL,
  `credit_rating` enum('excellent','good','fair','poor','blacklisted') DEFAULT 'good',
  `is_blacklisted` tinyint(1) DEFAULT 0,
  `blacklist_reason` text DEFAULT NULL,
  `blacklisted_at` datetime DEFAULT NULL,
  `blacklisted_by` int(10) unsigned DEFAULT NULL,
  `total_rentals` int(10) unsigned DEFAULT 0,
  `total_spent` decimal(12,2) DEFAULT 0.00,
  `last_rental_date` date DEFAULT NULL,
  `emergency_name` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `emergency_relationship` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `customer_code` (`customer_code`),
  KEY `idx_customer_code` (`customer_code`),
  KEY `idx_name` (`last_name`,`first_name`),
  KEY `idx_phone` (`phone_primary`),
  KEY `idx_email` (`email`),
  KEY `idx_type` (`customer_type`),
  KEY `idx_credit` (`credit_rating`),
  KEY `idx_blacklist` (`is_blacklisted`),
  KEY `created_by` (`created_by`),
  KEY `blacklisted_by` (`blacklisted_by`),
  CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`blacklisted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Customer directory';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `damage_reports`
--

DROP TABLE IF EXISTS `damage_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `damage_reports` (
  `report_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agreement_id` int(10) unsigned NOT NULL,
  `report_type` enum('pre_rental','post_rental','during_rental','maintenance_discovered') NOT NULL,
  `damage_location` enum('front_bumper','rear_bumper','left_front_door','right_front_door','left_rear_door','right_rear_door','hood','trunk','roof','windshield','left_front_fender','right_front_fender','left_rear_fender','right_rear_fender','left_side_mirror','right_side_mirror','front_left_wheel','front_right_wheel','rear_left_wheel','rear_right_wheel','interior_dashboard','interior_seats','interior_carpet','interior_trunk','other') NOT NULL,
  `damage_type` enum('scratch','dent','crack','chip','tear','stain','burn','missing_part','mechanical','other') NOT NULL,
  `severity_level` tinyint(3) unsigned NOT NULL COMMENT '1-5 scale',
  `description` text NOT NULL,
  `probable_cause` varchar(255) DEFAULT NULL,
  `discovered_date` datetime NOT NULL,
  `photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of photo paths' CHECK (json_valid(`photos`)),
  `diagram_marks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Coordinates on vehicle diagram' CHECK (json_valid(`diagram_marks`)),
  `is_customer_responsible` tinyint(1) DEFAULT NULL,
  `customer_acknowledged` tinyint(1) DEFAULT 0,
  `customer_acknowledged_at` datetime DEFAULT NULL,
  `customer_comment` text DEFAULT NULL,
  `estimated_repair_cost` decimal(10,2) DEFAULT NULL,
  `actual_repair_cost` decimal(10,2) DEFAULT NULL,
  `amount_charged_to_customer` decimal(10,2) DEFAULT NULL,
  `status` enum('reported','under_review','repair_pending','repaired','waived','disputed') DEFAULT 'reported',
  `repaired_by` int(10) unsigned DEFAULT NULL,
  `repaired_at` datetime DEFAULT NULL,
  `repair_invoice_path` varchar(255) DEFAULT NULL,
  `reported_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`report_id`),
  KEY `idx_agreement` (`agreement_id`),
  KEY `idx_type` (`report_type`),
  KEY `idx_status` (`status`),
  KEY `repaired_by` (`repaired_by`),
  KEY `reported_by` (`reported_by`),
  CONSTRAINT `damage_reports_ibfk_1` FOREIGN KEY (`agreement_id`) REFERENCES `rental_agreements` (`agreement_id`) ON DELETE CASCADE,
  CONSTRAINT `damage_reports_ibfk_2` FOREIGN KEY (`repaired_by`) REFERENCES `mechanics` (`mechanic_id`) ON DELETE SET NULL,
  CONSTRAINT `damage_reports_ibfk_3` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `expiring_compliance`
--

DROP TABLE IF EXISTS `expiring_compliance`;
/*!50001 DROP VIEW IF EXISTS `expiring_compliance`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `expiring_compliance` AS SELECT
 1 AS `record_id`,
  1 AS `vehicle_id`,
  1 AS `plate_number`,
  1 AS `compliance_type`,
  1 AS `document_number`,
  1 AS `expiry_date`,
  1 AS `days_until_expiry`,
  1 AS `status`,
  1 AS `alert_level` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `maintenance_logs`
--

DROP TABLE IF EXISTS `maintenance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_logs` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` varchar(20) NOT NULL,
  `schedule_id` int(10) unsigned DEFAULT NULL COMMENT 'If scheduled maintenance',
  `service_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `service_type` enum('oil_change','tire_rotation','brake_inspection','engine_tuneup','transmission_service','aircon_cleaning','battery_check','coolant_flush','timing_belt','general_checkup','emergency_repair','body_repair','detailing','others') NOT NULL,
  `service_description` text NOT NULL,
  `mileage_at_service` int(10) unsigned NOT NULL,
  `mechanic_id` int(10) unsigned DEFAULT NULL,
  `supervisor_id` int(10) unsigned DEFAULT NULL,
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `parts_cost` decimal(10,2) DEFAULT 0.00,
  `other_costs` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(12,2) GENERATED ALWAYS AS (`labor_cost` + `parts_cost` + `other_costs`) STORED,
  `parts_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '[{"part_name": "Oil Filter", "quantity": 1, "unit_cost": 350, "supplier_id": 5}]' CHECK (json_valid(`parts_used`)),
  `service_rating` tinyint(3) unsigned DEFAULT NULL COMMENT '1-5 quality rating',
  `customer_satisfaction` varchar(255) DEFAULT NULL COMMENT 'Feedback if applicable',
  `before_photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_photos`)),
  `after_photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_photos`)),
  `service_report_path` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','in_progress','awaiting_parts','quality_check','completed','cancelled') DEFAULT 'scheduled',
  `next_service_recommended_date` date DEFAULT NULL,
  `next_service_recommended_mileage` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_vehicle` (`vehicle_id`),
  KEY `idx_service_date` (`service_date`),
  KEY `idx_status` (`status`),
  KEY `idx_mechanic` (`mechanic_id`),
  KEY `schedule_id` (`schedule_id`),
  KEY `supervisor_id` (`supervisor_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `maintenance_logs_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_logs_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `maintenance_schedules` (`schedule_id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_logs_ibfk_3` FOREIGN KEY (`mechanic_id`) REFERENCES `mechanics` (`mechanic_id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_logs_ibfk_4` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_logs_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Maintenance service records';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maintenance_schedules`
--

DROP TABLE IF EXISTS `maintenance_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_schedules` (
  `schedule_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` varchar(20) NOT NULL,
  `service_type` enum('oil_change','tire_rotation','brake_inspection','engine_tuneup','transmission_service','aircon_cleaning','battery_check','coolant_flush','timing_belt','general_checkup','others') NOT NULL,
  `schedule_basis` enum('time_only','mileage_only','time_and_mileage') NOT NULL,
  `interval_months` tinyint(3) unsigned DEFAULT NULL COMMENT 'Months between services',
  `interval_mileage` int(10) unsigned DEFAULT NULL COMMENT 'Km between services',
  `advance_notice_days` tinyint(3) unsigned DEFAULT 7 COMMENT 'Days before to alert',
  `next_due_date` date DEFAULT NULL,
  `next_due_mileage` int(10) unsigned DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `last_service_mileage` int(10) unsigned DEFAULT NULL,
  `last_maintenance_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','paused','completed','overdue','scheduled','in_progress') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  KEY `idx_vehicle` (`vehicle_id`),
  KEY `idx_status` (`status`),
  KEY `idx_next_due` (`next_due_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `maintenance_schedules_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_schedules_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Preventive maintenance schedules';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_maintenance_overdue AFTER UPDATE ON maintenance_schedules
FOR EACH ROW
BEGIN
    IF OLD.status != 'overdue' AND NEW.status = 'overdue' THEN
        INSERT INTO notifications (user_id, type, title, message, related_module, related_record_id)
        SELECT user_id, 
               'maintenance_overdue',
               'Maintenance Overdue',
               CONCAT('Vehicle ', NEW.vehicle_id, ' has overdue maintenance: ', NEW.service_type),
               'maintenance',
               NEW.schedule_id
        FROM users
        WHERE role IN ('maintenance_supervisor', 'fleet_manager', 'system_admin')
          AND status = 'active';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `mechanics`
--

DROP TABLE IF EXISTS `mechanics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mechanics` (
  `mechanic_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'Linked system user if applicable',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `specialization` enum('general','engine','transmission','electrical','body','aircon','detailing') DEFAULT 'general',
  `certification` text DEFAULT NULL COMMENT 'Certifications and training',
  `employment_type` enum('regular','contractual','outsourced') DEFAULT 'regular',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`mechanic_id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_specialization` (`specialization`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `mechanics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` enum('maintenance_due','maintenance_overdue','compliance_expiring','compliance_expired','pr_pending_approval','pr_approved','pr_rejected','vehicle_returned','new_rental','system_alert','message') NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `related_module` varchar(50) DEFAULT NULL,
  `related_record_id` varchar(50) DEFAULT NULL,
  `related_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `pending_approval_summary`
--

DROP TABLE IF EXISTS `pending_approval_summary`;
/*!50001 DROP VIEW IF EXISTS `pending_approval_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `pending_approval_summary` AS SELECT
 1 AS `pr_id`,
  1 AS `pr_number`,
  1 AS `requestor_id`,
  1 AS `requestor_name`,
  1 AS `department`,
  1 AS `request_date`,
  1 AS `required_date`,
  1 AS `total_estimated_cost`,
  1 AS `urgency`,
  1 AS `current_approval_level`,
  1 AS `required_approval_level`,
  1 AS `days_until_required` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `procurement_items`
--

DROP TABLE IF EXISTS `procurement_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `procurement_items` (
  `item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pr_id` int(10) unsigned NOT NULL,
  `line_number` tinyint(3) unsigned NOT NULL COMMENT 'Item sequence in PR',
  `item_description` varchar(255) NOT NULL,
  `item_category` enum('parts','supplies','fuel','services','others') DEFAULT 'parts',
  `specification` text DEFAULT NULL COMMENT 'Technical specs, model numbers',
  `quantity` decimal(10,3) NOT NULL,
  `unit` varchar(20) NOT NULL COMMENT 'pcs, liters, kg, hours',
  `estimated_unit_cost` decimal(10,2) NOT NULL,
  `estimated_total_cost` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `estimated_unit_cost`) STORED,
  `actual_unit_cost` decimal(10,2) DEFAULT NULL,
  `actual_total_cost` decimal(12,2) DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `vehicle_id` varchar(20) DEFAULT NULL COMMENT 'If item is for specific vehicle',
  `purpose` text DEFAULT NULL COMMENT 'Why this item is needed',
  `urgency_note` text DEFAULT NULL,
  `quantity_ordered` decimal(10,3) DEFAULT NULL,
  `quantity_received` decimal(10,3) DEFAULT 0.000,
  `received_at` datetime DEFAULT NULL,
  `received_by` int(10) unsigned DEFAULT NULL,
  `quality_rating` tinyint(3) unsigned DEFAULT NULL COMMENT '1-5 rating upon receipt',
  `status` enum('pending','ordered','partially_received','fully_received','cancelled') DEFAULT 'pending',
  PRIMARY KEY (`item_id`),
  KEY `idx_pr` (`pr_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_vehicle` (`vehicle_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `procurement_items_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `procurement_requests` (`pr_id`) ON DELETE CASCADE,
  CONSTRAINT `procurement_items_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_items_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_items_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Procurement request line items';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `procurement_requests`
--

DROP TABLE IF EXISTS `procurement_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `procurement_requests` (
  `pr_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pr_number` varchar(30) NOT NULL COMMENT 'PR-GCR-YYYY-XXXX format',
  `requestor_id` int(10) unsigned NOT NULL,
  `department` enum('maintenance','operations','admin','management') NOT NULL,
  `request_date` date NOT NULL,
  `required_date` date NOT NULL,
  `urgency` enum('low','medium','high','critical') DEFAULT 'medium',
  `total_estimated_cost` decimal(12,2) DEFAULT 0.00,
  `total_actual_cost` decimal(12,2) DEFAULT NULL,
  `purpose_summary` varchar(255) DEFAULT NULL COMMENT 'Brief description of request purpose',
  `status` enum('draft','pending_approval','approved','rejected','ordered','partially_received','fully_received','cancelled','closed') DEFAULT 'draft',
  `current_approval_level` tinyint(3) unsigned DEFAULT 1 COMMENT '1=Supervisor, 2=Manager, 3=Owner',
  `approval_workflow` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of approver IDs and statuses' CHECK (json_valid(`approval_workflow`)),
  `approved_by_level1` int(10) unsigned DEFAULT NULL,
  `approved_at_level1` datetime DEFAULT NULL,
  `approval_notes_level1` text DEFAULT NULL,
  `approved_by_level2` int(10) unsigned DEFAULT NULL,
  `approved_at_level2` datetime DEFAULT NULL,
  `approval_notes_level2` text DEFAULT NULL,
  `approved_by_level3` int(10) unsigned DEFAULT NULL,
  `approved_at_level3` datetime DEFAULT NULL,
  `approval_notes_level3` text DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `po_number` varchar(30) DEFAULT NULL,
  `po_generated_at` datetime DEFAULT NULL,
  `po_generated_by` int(10) unsigned DEFAULT NULL,
  `fully_received_at` datetime DEFAULT NULL,
  `received_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`pr_id`),
  UNIQUE KEY `pr_number` (`pr_number`),
  KEY `idx_pr_number` (`pr_number`),
  KEY `idx_status` (`status`),
  KEY `idx_requestor` (`requestor_id`),
  KEY `idx_request_date` (`request_date`),
  KEY `idx_required_date` (`required_date`),
  KEY `approved_by_level1` (`approved_by_level1`),
  KEY `approved_by_level2` (`approved_by_level2`),
  KEY `approved_by_level3` (`approved_by_level3`),
  KEY `rejected_by` (`rejected_by`),
  KEY `po_generated_by` (`po_generated_by`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `procurement_requests_ibfk_1` FOREIGN KEY (`requestor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `procurement_requests_ibfk_2` FOREIGN KEY (`approved_by_level1`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_requests_ibfk_3` FOREIGN KEY (`approved_by_level2`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_requests_ibfk_4` FOREIGN KEY (`approved_by_level3`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_requests_ibfk_5` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_requests_ibfk_6` FOREIGN KEY (`po_generated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_requests_ibfk_7` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Procurement requests';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rate_limit`
--

DROP TABLE IF EXISTS `rate_limit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL COMMENT 'Username, IP, or endpoint identifier',
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Rate limiting tracker';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rental_agreements`
--

DROP TABLE IF EXISTS `rental_agreements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rental_agreements` (
  `agreement_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `agreement_number` varchar(30) NOT NULL COMMENT 'RA-GCR-YYYY-XXXX',
  `customer_id` int(10) unsigned NOT NULL,
  `vehicle_id` varchar(20) NOT NULL,
  `rental_start_date` datetime NOT NULL,
  `rental_end_date` datetime NOT NULL,
  `actual_return_date` datetime DEFAULT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `total_days` int(10) unsigned GENERATED ALWAYS AS (ceiling(timestampdiff(HOUR,`rental_start_date`,`rental_end_date`) / 24)) STORED,
  `base_amount` decimal(12,2) GENERATED ALWAYS AS (`daily_rate` * ceiling(timestampdiff(HOUR,`rental_start_date`,`rental_end_date`) / 24)) STORED,
  `additional_driver_fee` decimal(10,2) DEFAULT 0.00,
  `insurance_fee` decimal(10,2) DEFAULT 0.00,
  `gps_fee` decimal(10,2) DEFAULT 0.00,
  `child_seat_fee` decimal(10,2) DEFAULT 0.00,
  `other_charges` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `discount_reason` varchar(100) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT NULL,
  `tax_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `security_deposit` decimal(10,2) NOT NULL,
  `security_deposit_returned` tinyint(1) DEFAULT 0,
  `security_deposit_returned_at` datetime DEFAULT NULL,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','fully_paid','refunded','cancelled') DEFAULT 'pending',
  `fuel_policy` enum('full_to_full','pre_purchase','return_empty') DEFAULT 'full_to_full',
  `mileage_limit` int(10) unsigned DEFAULT 0 COMMENT '0 = unlimited',
  `excess_mileage_charge` decimal(8,2) DEFAULT 10.00 COMMENT 'Per km',
  `allowed_areas` text DEFAULT NULL COMMENT 'Geographic restrictions if any',
  `mileage_at_pickup` int(10) unsigned DEFAULT NULL,
  `mileage_at_return` int(10) unsigned DEFAULT NULL,
  `excess_mileage_charged` decimal(10,2) DEFAULT NULL,
  `status` enum('reserved','confirmed','active','returned','completed','cancelled','no_show') DEFAULT 'reserved',
  `pickup_location` enum('main_office','airport','hotel_delivery','other') DEFAULT 'main_office',
  `return_location` enum('main_office','airport','hotel_pickup','other') DEFAULT 'main_office',
  `picked_up_by` varchar(100) DEFAULT NULL,
  `returned_by` varchar(100) DEFAULT NULL,
  `customer_signature_path` varchar(255) DEFAULT NULL,
  `staff_signature_path` varchar(255) DEFAULT NULL,
  `agreement_pdf_path` varchar(255) DEFAULT NULL,
  `checklist_pickup_path` varchar(255) DEFAULT NULL,
  `checklist_return_path` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `confirmed_by` int(10) unsigned DEFAULT NULL,
  `picked_up_by_staff` int(10) unsigned DEFAULT NULL,
  `received_by_staff` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`agreement_id`),
  UNIQUE KEY `agreement_number` (`agreement_number`),
  KEY `idx_agreement_number` (`agreement_number`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_vehicle` (`vehicle_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`rental_start_date`,`rental_end_date`),
  KEY `idx_payment` (`payment_status`),
  KEY `created_by` (`created_by`),
  KEY `confirmed_by` (`confirmed_by`),
  KEY `picked_up_by_staff` (`picked_up_by_staff`),
  KEY `received_by_staff` (`received_by_staff`),
  CONSTRAINT `rental_agreements_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `rental_agreements_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`),
  CONSTRAINT `rental_agreements_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `rental_agreements_ibfk_4` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `rental_agreements_ibfk_5` FOREIGN KEY (`picked_up_by_staff`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `rental_agreements_ibfk_6` FOREIGN KEY (`received_by_staff`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Rental agreements';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_update_customer_stats AFTER UPDATE ON rental_agreements
FOR EACH ROW
BEGIN
    IF OLD.status != 'completed' AND NEW.status = 'completed' THEN
        UPDATE customers
        SET total_rentals = total_rentals + 1,
            total_spent = total_spent + NEW.total_amount,
            last_rental_date = NEW.rental_end_date
        WHERE customer_id = NEW.customer_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `security_logs`
--

DROP TABLE IF EXISTS `security_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_logs` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL COMMENT 'login_failed, csrf_violation, etc.',
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `user_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_event` (`event_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Security event log';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `supplier_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_code` varchar(20) NOT NULL COMMENT 'SUP-XXXX format',
  `company_name` varchar(100) NOT NULL,
  `business_type` enum('corporation','partnership','sole_proprietor','cooperative') DEFAULT 'sole_proprietor',
  `tax_id` varchar(50) DEFAULT NULL COMMENT 'TIN/BIR number',
  `category` enum('auto_parts','maintenance_supplies','fuel','tires','carwash_supplies','insurance','registration_services','others') NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) DEFAULT 'General Santos City',
  `province` varchar(50) DEFAULT 'South Cotabato',
  `zip_code` varchar(10) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `phone_primary` varchar(20) NOT NULL,
  `phone_secondary` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(50) DEFAULT NULL COMMENT 'Net 30, COD, etc.',
  `credit_limit` decimal(12,2) DEFAULT NULL,
  `performance_rating` decimal(3,2) DEFAULT 3.00 COMMENT '1.00-5.00 scale',
  `lead_time_days` tinyint(3) unsigned DEFAULT 1 COMMENT 'Average delivery days',
  `is_accredited` tinyint(1) DEFAULT 0,
  `accreditation_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`supplier_id`),
  UNIQUE KEY `supplier_code` (`supplier_code`),
  KEY `idx_category` (`category`),
  KEY `idx_city` (`city`),
  KEY `idx_active` (`is_active`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Supplier directory';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json','encrypted') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_editable` tinyint(1) DEFAULT 1,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `upcoming_maintenance`
--

DROP TABLE IF EXISTS `upcoming_maintenance`;
/*!50001 DROP VIEW IF EXISTS `upcoming_maintenance`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `upcoming_maintenance` AS SELECT
 1 AS `schedule_id`,
  1 AS `vehicle_id`,
  1 AS `plate_number`,
  1 AS `brand`,
  1 AS `model`,
  1 AS `category_id`,
  1 AS `category_name`,
  1 AS `service_type`,
  1 AS `next_due_date`,
  1 AS `next_due_mileage`,
  1 AS `current_mileage`,
  1 AS `days_until_due`,
  1 AS `urgency` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `last_activity` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `is_valid` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Active user sessions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL COMMENT 'Internal employee ID',
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL COMMENT 'Bcrypt hash',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` enum('management','operations','maintenance','procurement','customer_service','admin') NOT NULL,
  `role` enum('system_admin','fleet_manager','procurement_officer','maintenance_supervisor','customer_service_staff','mechanic','viewer') NOT NULL DEFAULT 'viewer',
  `avatar_path` varchar(255) DEFAULT 'assets/images/default-avatar.png',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `login_attempts` tinyint(3) unsigned DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT current_timestamp(),
  `must_change_password` tinyint(1) DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete timestamp',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_department` (`department`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='System users and authentication';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `vehicle_availability_summary`
--

DROP TABLE IF EXISTS `vehicle_availability_summary`;
/*!50001 DROP VIEW IF EXISTS `vehicle_availability_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vehicle_availability_summary` AS SELECT
 1 AS `category_name`,
  1 AS `category_code`,
  1 AS `total_vehicles`,
  1 AS `available_count`,
  1 AS `rented_count`,
  1 AS `maintenance_count`,
  1 AS `reserved_count`,
  1 AS `cleaning_count`,
  1 AS `out_of_service_count` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `vehicle_categories`
--

DROP TABLE IF EXISTS `vehicle_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_categories` (
  `category_id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `category_code` varchar(10) NOT NULL COMMENT 'HB, SD, MP, PU, SU, VN, LX',
  `category_name` varchar(50) NOT NULL COMMENT 'Hatchback, Sedan, etc.',
  `description` text DEFAULT NULL,
  `default_seating` tinyint(3) unsigned DEFAULT NULL,
  `default_fuel_type` enum('gasoline','diesel','hybrid','electric') DEFAULT NULL,
  `icon_class` varchar(50) DEFAULT NULL COMMENT 'FontAwesome or Lucide icon class',
  `display_order` tinyint(3) unsigned DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_code` (`category_code`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Vehicle classification categories';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle_photos`
--

DROP TABLE IF EXISTS `vehicle_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_photos` (
  `photo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` varchar(20) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `photo_type` enum('exterior_front','exterior_rear','exterior_side','interior','damage','maintenance','other') DEFAULT 'exterior_front',
  `is_primary` tinyint(1) DEFAULT 0,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`photo_id`),
  KEY `idx_vehicle` (`vehicle_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `vehicle_photos_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  CONSTRAINT `vehicle_photos_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle_status_logs`
--

DROP TABLE IF EXISTS `vehicle_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_status_logs` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` varchar(20) NOT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `previous_location` varchar(50) DEFAULT NULL,
  `new_location` varchar(50) DEFAULT NULL,
  `previous_mileage` int(10) unsigned DEFAULT NULL,
  `new_mileage` int(10) unsigned DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Reason for status change',
  `changed_by` int(10) unsigned NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `related_rental_id` int(10) unsigned DEFAULT NULL,
  `related_maintenance_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_vehicle` (`vehicle_id`),
  KEY `idx_changed_at` (`changed_at`),
  KEY `idx_new_status` (`new_status`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `vehicle_status_logs_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  CONSTRAINT `vehicle_status_logs_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Vehicle status change audit trail';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicles` (
  `vehicle_id` varchar(20) NOT NULL COMMENT 'Format: GCR-{CATEGORY}-{SEQUENCE}',
  `category_id` tinyint(3) unsigned NOT NULL,
  `plate_number` varchar(20) NOT NULL COMMENT 'LTO plate number',
  `engine_number` varchar(50) DEFAULT NULL,
  `chassis_number` varchar(50) DEFAULT NULL,
  `year_model` year(4) NOT NULL,
  `brand` varchar(50) NOT NULL COMMENT 'Toyota, Honda, etc.',
  `model` varchar(100) NOT NULL COMMENT 'Vios, Civic, etc.',
  `variant` varchar(50) DEFAULT NULL COMMENT '1.3 E, RS, etc.',
  `color` varchar(30) NOT NULL,
  `seating_capacity` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `fuel_type` enum('gasoline','diesel','hybrid','electric') NOT NULL,
  `transmission` enum('manual','automatic','cvt') NOT NULL DEFAULT 'manual',
  `acquisition_date` date NOT NULL,
  `acquisition_cost` decimal(12,2) DEFAULT NULL COMMENT 'Purchase price',
  `current_status` enum('available','rented','maintenance','reserved','cleaning','out_of_service','retired') DEFAULT 'available',
  `current_location` enum('main_office','satellite_location','with_customer','service_center','unknown') DEFAULT 'main_office',
  `mileage` int(10) unsigned DEFAULT 0 COMMENT 'Current odometer reading',
  `fuel_level` tinyint(3) unsigned DEFAULT 100 COMMENT 'Percentage',
  `daily_rental_rate` decimal(10,2) NOT NULL,
  `weekly_rental_rate` decimal(10,2) DEFAULT NULL,
  `monthly_rental_rate` decimal(10,2) DEFAULT NULL,
  `security_deposit_amount` decimal(10,2) DEFAULT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL COMMENT 'Path to generated QR code image',
  `qr_code_data` text DEFAULT NULL COMMENT 'JSON data embedded in QR',
  `primary_photo_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete',
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `plate_number` (`plate_number`),
  UNIQUE KEY `engine_number` (`engine_number`),
  UNIQUE KEY `chassis_number` (`chassis_number`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`current_status`),
  KEY `idx_location` (`current_location`),
  KEY `idx_plate` (`plate_number`),
  KEY `idx_brand_model` (`brand`,`model`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `vehicle_categories` (`category_id`),
  CONSTRAINT `vehicles_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Fleet vehicle registry';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'gensan_car_rental_db'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GeneratePRNumber` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `GeneratePRNumber`(OUT p_pr_number VARCHAR(30))
BEGIN
    DECLARE v_year INT;
    DECLARE v_sequence INT;
    
    SET v_year = YEAR(CURDATE());
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(pr_number, -4) AS UNSIGNED)), 0) + 1
    INTO v_sequence
    FROM procurement_requests
    WHERE YEAR(created_at) = v_year;
    
    SET p_pr_number = CONCAT('PR-GCR-', v_year, '-', LPAD(v_sequence, 4, '0'));
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GenerateVehicleID` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerateVehicleID`(
    IN p_category_code VARCHAR(10),
    OUT p_vehicle_id VARCHAR(20)
)
BEGIN
    DECLARE v_sequence INT;
    DECLARE v_category_id TINYINT UNSIGNED;
    
    
    SELECT category_id INTO v_category_id 
    FROM vehicle_categories 
    WHERE category_code = p_category_code;
    
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(vehicle_id, -4) AS UNSIGNED)), 0) + 1
    INTO v_sequence
    FROM vehicles
    WHERE category_id = v_category_id;
    
    
    SET p_vehicle_id = CONCAT('GCR-', p_category_code, '-', LPAD(v_sequence, 4, '0'));
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `LogAudit` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `LogAudit`(
    IN p_user_id INT UNSIGNED,
    IN p_user_name VARCHAR(100),
    IN p_user_role VARCHAR(50),
    IN p_action VARCHAR(50),
    IN p_module VARCHAR(50),
    IN p_table_name VARCHAR(50),
    IN p_record_id VARCHAR(50),
    IN p_record_description VARCHAR(255),
    IN p_old_values JSON,
    IN p_new_values JSON,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_request_method VARCHAR(10),
    IN p_request_url TEXT,
    IN p_severity VARCHAR(20)
)
BEGIN
    INSERT INTO audit_logs (
        user_id, user_name, user_role,
        action, module, table_name, record_id, record_description,
        old_values, new_values, changed_fields,
        ip_address, user_agent, session_id,
        request_method, request_url,
        action_timestamp, severity
    ) VALUES (
        p_user_id, p_user_name, p_user_role,
        p_action, p_module, p_table_name, p_record_id, p_record_description,
        p_old_values, p_new_values,
        JSON_KEYS(JSON_MERGE_PATCH(COALESCE(p_old_values, '{}'), COALESCE(p_new_values, '{}'))),
        p_ip_address, p_user_agent, NULL,
        p_request_method, p_request_url,
        NOW(), p_severity
    );
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ProcessPRApproval` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `ProcessPRApproval`(
    IN p_pr_id INT UNSIGNED,
    IN p_approver_id INT UNSIGNED,
    IN p_approval_level TINYINT UNSIGNED,
    IN p_notes TEXT,
    IN p_action ENUM('approve', 'reject'),
    OUT p_result_message VARCHAR(255)
)
BEGIN
    DECLARE v_current_level TINYINT UNSIGNED;
    DECLARE v_total_cost DECIMAL(12,2);
    DECLARE v_pr_status VARCHAR(50);
    
    
    SELECT current_approval_level, total_estimated_cost, status
    INTO v_current_level, v_total_cost, v_pr_status
    FROM procurement_requests
    WHERE pr_id = p_pr_id;
    
    
    IF v_pr_status != 'pending_approval' THEN
        SET p_result_message = 'PR is not in pending approval status';
    ELSEIF v_current_level != p_approval_level THEN
        SET p_result_message = 'Invalid approval level';
    ELSEIF p_action = 'reject' THEN
        
        UPDATE procurement_requests
        SET status = 'rejected',
            rejected_by = p_approver_id,
            rejected_at = NOW(),
            rejection_reason = p_notes
        WHERE pr_id = p_pr_id;
        
        SET p_result_message = 'PR rejected successfully';
    ELSE
        
        IF p_approval_level = 1 THEN
            UPDATE procurement_requests
            SET approved_by_level1 = p_approver_id,
                approved_at_level1 = NOW(),
                approval_notes_level1 = p_notes,
                current_approval_level = 2
            WHERE pr_id = p_pr_id;
            
            
            IF v_total_cost <= 5000 THEN
                UPDATE procurement_requests
                SET status = 'approved'
                WHERE pr_id = p_pr_id;
            END IF;
            
        ELSEIF p_approval_level = 2 THEN
            UPDATE procurement_requests
            SET approved_by_level2 = p_approver_id,
                approved_at_level2 = NOW(),
                approval_notes_level2 = p_notes,
                current_approval_level = 3
            WHERE pr_id = p_pr_id;
            
            
            IF v_total_cost <= 20000 THEN
                UPDATE procurement_requests
                SET status = 'approved'
                WHERE pr_id = p_pr_id;
            END IF;
            
        ELSEIF p_approval_level = 3 THEN
            UPDATE procurement_requests
            SET approved_by_level3 = p_approver_id,
                approved_at_level3 = NOW(),
                approval_notes_level3 = p_notes,
                status = 'approved'
            WHERE pr_id = p_pr_id;
        END IF;
        
        SET p_result_message = 'PR approved successfully';
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `UpdateVehicleStatus` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateVehicleStatus`(
    IN p_vehicle_id VARCHAR(20),
    IN p_new_status VARCHAR(50),
    IN p_new_location VARCHAR(50),
    IN p_new_mileage INT UNSIGNED,
    IN p_reason TEXT,
    IN p_changed_by INT UNSIGNED,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_related_rental_id INT UNSIGNED,
    IN p_related_maintenance_id INT UNSIGNED
)
BEGIN
    DECLARE v_old_status VARCHAR(50);
    DECLARE v_old_location VARCHAR(50);
    DECLARE v_old_mileage INT UNSIGNED;
    
    
    SELECT current_status, current_location, mileage 
    INTO v_old_status, v_old_location, v_old_mileage
    FROM vehicles 
    WHERE vehicle_id = p_vehicle_id;
    
    
    UPDATE vehicles 
    SET current_status = p_new_status,
        current_location = p_new_location,
        mileage = COALESCE(p_new_mileage, mileage),
        updated_at = NOW()
    WHERE vehicle_id = p_vehicle_id;
    
    
    INSERT INTO vehicle_status_logs (
        vehicle_id, previous_status, new_status,
        previous_location, new_location,
        previous_mileage, new_mileage,
        reason, changed_by, changed_at,
        related_rental_id, related_maintenance_id,
        ip_address, user_agent
    ) VALUES (
        p_vehicle_id, v_old_status, p_new_status,
        v_old_location, p_new_location,
        v_old_mileage, p_new_mileage,
        p_reason, p_changed_by, NOW(),
        p_related_rental_id, p_related_maintenance_id,
        p_ip_address, p_user_agent
    );
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Current Database: `gensan_car_rental_db`
--

USE `gensan_car_rental_db`;

--
-- Final view structure for view `customer_rental_stats`
--

/*!50001 DROP VIEW IF EXISTS `customer_rental_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `customer_rental_stats` AS select `c`.`customer_id` AS `customer_id`,`c`.`customer_code` AS `customer_code`,concat(`c`.`first_name`,' ',`c`.`last_name`) AS `customer_name`,`c`.`customer_type` AS `customer_type`,count(`ra`.`agreement_id`) AS `total_rentals`,sum(`ra`.`total_amount`) AS `total_spent`,max(`ra`.`rental_start_date`) AS `last_rental_date`,sum(case when `ra`.`status` = 'completed' then 1 else 0 end) AS `completed_rentals`,sum(case when `ra`.`status` = 'cancelled' then 1 else 0 end) AS `cancelled_rentals`,avg(to_days(`ra`.`rental_end_date`) - to_days(`ra`.`rental_start_date`)) AS `avg_rental_days` from (`customers` `c` left join `rental_agreements` `ra` on(`c`.`customer_id` = `ra`.`customer_id` and `ra`.`status` <> 'cancelled')) where `c`.`deleted_at` is null group by `c`.`customer_id`,`c`.`customer_code`,`c`.`first_name`,`c`.`last_name`,`c`.`customer_type` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `expiring_compliance`
--

/*!50001 DROP VIEW IF EXISTS `expiring_compliance`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `expiring_compliance` AS select `cr`.`record_id` AS `record_id`,`cr`.`vehicle_id` AS `vehicle_id`,`v`.`plate_number` AS `plate_number`,`cr`.`compliance_type` AS `compliance_type`,`cr`.`document_number` AS `document_number`,`cr`.`expiry_date` AS `expiry_date`,to_days(`cr`.`expiry_date`) - to_days(curdate()) AS `days_until_expiry`,`cr`.`status` AS `status`,case when `cr`.`expiry_date` < curdate() then 'expired' when `cr`.`expiry_date` <= curdate() + interval 7 day then 'critical' when `cr`.`expiry_date` <= curdate() + interval 30 day then 'warning' else 'good' end AS `alert_level` from (`compliance_records` `cr` join `vehicles` `v` on(`cr`.`vehicle_id` = `v`.`vehicle_id`)) where `cr`.`status` in ('active','renewal_pending') and `v`.`deleted_at` is null and `v`.`current_status` <> 'retired' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `pending_approval_summary`
--

/*!50001 DROP VIEW IF EXISTS `pending_approval_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `pending_approval_summary` AS select `pr`.`pr_id` AS `pr_id`,`pr`.`pr_number` AS `pr_number`,`pr`.`requestor_id` AS `requestor_id`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `requestor_name`,`pr`.`department` AS `department`,`pr`.`request_date` AS `request_date`,`pr`.`required_date` AS `required_date`,`pr`.`total_estimated_cost` AS `total_estimated_cost`,`pr`.`urgency` AS `urgency`,`pr`.`current_approval_level` AS `current_approval_level`,case when `pr`.`current_approval_level` = 1 then 'Supervisor' when `pr`.`current_approval_level` = 2 then 'Manager' when `pr`.`current_approval_level` = 3 then 'Owner' end AS `required_approval_level`,to_days(`pr`.`required_date`) - to_days(curdate()) AS `days_until_required` from (`procurement_requests` `pr` join `users` `u` on(`pr`.`requestor_id` = `u`.`user_id`)) where `pr`.`status` = 'pending_approval' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `upcoming_maintenance`
--

/*!50001 DROP VIEW IF EXISTS `upcoming_maintenance`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `upcoming_maintenance` AS select `ms`.`schedule_id` AS `schedule_id`,`ms`.`vehicle_id` AS `vehicle_id`,`v`.`plate_number` AS `plate_number`,`v`.`brand` AS `brand`,`v`.`model` AS `model`,`v`.`category_id` AS `category_id`,`vc`.`category_name` AS `category_name`,`ms`.`service_type` AS `service_type`,`ms`.`next_due_date` AS `next_due_date`,`ms`.`next_due_mileage` AS `next_due_mileage`,`v`.`mileage` AS `current_mileage`,to_days(`ms`.`next_due_date`) - to_days(curdate()) AS `days_until_due`,case when `ms`.`next_due_date` < curdate() then 'overdue' when `ms`.`next_due_date` <= curdate() + interval 7 day then 'due_soon' else 'scheduled' end AS `urgency` from ((`maintenance_schedules` `ms` join `vehicles` `v` on(`ms`.`vehicle_id` = `v`.`vehicle_id`)) join `vehicle_categories` `vc` on(`v`.`category_id` = `vc`.`category_id`)) where `ms`.`status` = 'active' and `v`.`current_status` <> 'retired' and `v`.`deleted_at` is null and (`ms`.`next_due_date` is not null or `ms`.`next_due_mileage` is not null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vehicle_availability_summary`
--

/*!50001 DROP VIEW IF EXISTS `vehicle_availability_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vehicle_availability_summary` AS select `vc`.`category_name` AS `category_name`,`vc`.`category_code` AS `category_code`,count(`v`.`vehicle_id`) AS `total_vehicles`,sum(case when `v`.`current_status` = 'available' then 1 else 0 end) AS `available_count`,sum(case when `v`.`current_status` = 'rented' then 1 else 0 end) AS `rented_count`,sum(case when `v`.`current_status` = 'maintenance' then 1 else 0 end) AS `maintenance_count`,sum(case when `v`.`current_status` = 'reserved' then 1 else 0 end) AS `reserved_count`,sum(case when `v`.`current_status` = 'cleaning' then 1 else 0 end) AS `cleaning_count`,sum(case when `v`.`current_status` = 'out_of_service' then 1 else 0 end) AS `out_of_service_count` from (`vehicle_categories` `vc` left join `vehicles` `v` on(`vc`.`category_id` = `v`.`category_id` and `v`.`deleted_at` is null)) where `vc`.`is_active` = 1 group by `vc`.`category_id`,`vc`.`category_name`,`vc`.`category_code` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-08 15:55:15
