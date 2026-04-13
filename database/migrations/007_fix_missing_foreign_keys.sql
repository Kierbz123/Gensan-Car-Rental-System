-- =============================================================================
-- Migration: 007_fix_missing_foreign_keys.sql
-- Description: Adds missing foreign key constraints identified in schema audit.
-- Affected tables:
--   1. system_settings  → updated_by  → users.user_id
--   2. audit_logs       → user_id     → users.user_id
--   3. vehicle_status_logs → related_rental_id    → rental_agreements.agreement_id
--   4. vehicle_status_logs → related_maintenance_id → maintenance_logs.log_id
--   5. maintenance_schedules → last_maintenance_id → maintenance_logs.log_id
-- =============================================================================

USE `gensan_car_rental_db`;

-- Disable FK checks during migration to avoid ordering issues
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- FIX 1: system_settings.updated_by → users.user_id
-- Tracks which admin user last changed a system setting.
-- ON DELETE SET NULL: if the user is deleted, the setting record is preserved
-- but the audit trail column is nulled out (safe and consistent with schema convention).
-- -----------------------------------------------------------------------------
ALTER TABLE `system_settings`
  ADD KEY `fk_system_settings_updated_by` (`updated_by`),
  ADD CONSTRAINT `system_settings_ibfk_1`
    FOREIGN KEY (`updated_by`)
    REFERENCES `users` (`user_id`)
    ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- FIX 2: audit_logs.user_id → users.user_id
-- The audit log already stores denormalized user_name and user_role for history,
-- so ON DELETE SET NULL is correct — logs must survive user deletion.
-- NOTE: security_logs.user_id is intentionally left WITHOUT a FK because it
-- records pre-authentication events (failed logins for non-existent users).
-- audit_logs, however, is only written post-authentication so the FK is safe.
-- -----------------------------------------------------------------------------
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- FIX 3: vehicle_status_logs.related_rental_id → rental_agreements.agreement_id
-- Links a vehicle status change to the rental that caused it (e.g., pickup, return).
-- ON DELETE SET NULL: if the rental is somehow deleted, the status log is kept
-- for audit trail purposes.
-- -----------------------------------------------------------------------------
ALTER TABLE `vehicle_status_logs`
  ADD KEY `fk_vsl_rental` (`related_rental_id`),
  ADD CONSTRAINT `vehicle_status_logs_ibfk_3`
    FOREIGN KEY (`related_rental_id`)
    REFERENCES `rental_agreements` (`agreement_id`)
    ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- FIX 4: vehicle_status_logs.related_maintenance_id → maintenance_logs.log_id
-- Links a vehicle status change to the maintenance job that caused it.
-- ON DELETE SET NULL: same reasoning as above.
-- -----------------------------------------------------------------------------
ALTER TABLE `vehicle_status_logs`
  ADD KEY `fk_vsl_maintenance` (`related_maintenance_id`),
  ADD CONSTRAINT `vehicle_status_logs_ibfk_4`
    FOREIGN KEY (`related_maintenance_id`)
    REFERENCES `maintenance_logs` (`log_id`)
    ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- FIX 5: maintenance_schedules.last_maintenance_id → maintenance_logs.log_id
-- Points to the most recent maintenance log entry for this schedule,
-- used to calculate the next due date.
-- ON DELETE SET NULL: if the log entry is deleted, the schedule is not broken —
-- it just loses its back-reference to the last job.
-- -----------------------------------------------------------------------------
ALTER TABLE `maintenance_schedules`
  ADD KEY `fk_ms_last_maintenance` (`last_maintenance_id`),
  ADD CONSTRAINT `maintenance_schedules_ibfk_3`
    FOREIGN KEY (`last_maintenance_id`)
    REFERENCES `maintenance_logs` (`log_id`)
    ON DELETE SET NULL;

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verify all new constraints (run manually to confirm)
-- SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = 'gensan_car_rental_db'
--   AND CONSTRAINT_NAME IN (
--     'system_settings_ibfk_1',
--     'audit_logs_ibfk_1',
--     'vehicle_status_logs_ibfk_3',
--     'vehicle_status_logs_ibfk_4',
--     'maintenance_schedules_ibfk_3'
--   );
