-- =============================================================================
-- Demo Data Patch
-- Gensan Car Rental System
-- Purpose:
--   1. Trim fleet to 8 vehicles
--   2. Add 5 drivers
--   3. Ensure ONLY 2 vehicles show "Renew" on compliance/index.php
--      (records expiring within 30 days or breached)
-- Run against: gensan_car_rental_db
-- Current date context: 2026-03-25
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. COMPLIANCE — Fix so only 2 records show on the watchlist
--    Page query: expiry_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
--    Currently showing 5 rows. We want exactly 2:
--      KEEP:  record 2  (GCR-SD-0001, insurance_tpl,    expiry 2026-04-01)  → PENDING
--      KEEP:  record 8  (GCR-VN-0001, lto_registration, expiry 2025-11-05)  → BREACHED
--    SILENCE: record 9  (GCR-VN-0001, insurance_tpl) — push to 2027
--    SILENCE: record 11 (GCR-HB-0001, lto_registration) — push to 2027
--    SILENCE: record 12 (GCR-HB-0001, emission_test)   — push to 2027
-- -----------------------------------------------------------------------------
UPDATE compliance_records
SET expiry_date = '2027-11-05', status = 'active', days_until_expiry = 590
WHERE record_id = 9;

UPDATE compliance_records
SET expiry_date = '2027-03-25', status = 'active', days_until_expiry = 365
WHERE record_id = 11;

UPDATE compliance_records
SET expiry_date = '2027-03-25', status = 'active', days_until_expiry = 365
WHERE record_id = 12;

-- -----------------------------------------------------------------------------
-- 2. REMOVE VEHICLES NOT IN THE 8-VEHICLE FLEET
--    Kept: GCR-HB-0001, GCR-HB-0002, GCR-SD-0001, GCR-SD-0002,
--          GCR-SU-0001, GCR-VN-0001, GCR-PU-0001, GCR-MP-0001
--    Removed: GCR-HB-0003, GCR-LV-0001, GCR-LV-0002, GCR-LX-0001,
--             GCR-MP-0002, GCR-PU-0002, GCR-SU-0002, GCR-VN-0002
-- -----------------------------------------------------------------------------

-- 2a. Maintenance schedules for removed vehicles
DELETE FROM maintenance_schedules
WHERE vehicle_id IN ('GCR-HB-0003','GCR-PU-0002');

-- 2b. Maintenance logs for removed vehicles
DELETE FROM maintenance_logs
WHERE vehicle_id IN ('GCR-HB-0003','GCR-LV-0001','GCR-LV-0002','GCR-LX-0001',
                     'GCR-MP-0002','GCR-PU-0002','GCR-SU-0002','GCR-VN-0002');

-- 2c. Vehicle status logs for removed vehicles
DELETE FROM vehicle_status_logs
WHERE vehicle_id IN ('GCR-HB-0003','GCR-LV-0001','GCR-LV-0002','GCR-LX-0001',
                     'GCR-MP-0002','GCR-PU-0002','GCR-SU-0002','GCR-VN-0002');

-- 2d. Compliance records for removed vehicles
DELETE FROM compliance_records
WHERE vehicle_id IN ('GCR-HB-0003','GCR-LV-0001','GCR-LV-0002','GCR-LX-0001',
                     'GCR-MP-0002','GCR-PU-0002','GCR-SU-0002','GCR-VN-0002');

-- 2e. Procurement items linked to removed vehicles
DELETE FROM procurement_items
WHERE vehicle_id IN ('GCR-HB-0003','GCR-LV-0001','GCR-LV-0002','GCR-LX-0001',
                     'GCR-MP-0002','GCR-PU-0002','GCR-SU-0002','GCR-VN-0002');

-- 2f. Remove PR-GCR-2026-0002 (brake pads for GCR-PU-0002, now removed)
--     and its items
DELETE FROM procurement_items WHERE pr_id = 2;
DELETE FROM procurement_requests WHERE pr_id = 2;

-- 2g. Remove notification that referenced PR#2
DELETE FROM notifications WHERE notification_id = 1;

-- 2h. Damage reports for rentals on removed vehicles
--     Report 1 references agreement_id=3 (GCR-LV-0001)
DELETE FROM damage_reports WHERE report_id = 1;

-- 2i. Rental agreements for removed vehicles
DELETE FROM rental_agreements
WHERE vehicle_id IN ('GCR-HB-0003','GCR-LV-0001','GCR-LV-0002','GCR-LX-0001',
                     'GCR-MP-0002','GCR-PU-0002','GCR-SU-0002','GCR-VN-0002');

-- 2j. Delete the 8 removed vehicles
DELETE FROM vehicles
WHERE vehicle_id IN ('GCR-HB-0003','GCR-LV-0001','GCR-LV-0002','GCR-LX-0001',
                     'GCR-MP-0002','GCR-PU-0002','GCR-SU-0002','GCR-VN-0002');

-- -----------------------------------------------------------------------------
-- 3. DRIVERS — Insert 5 drivers (idempotent)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `drivers`
  (`employee_code`, `first_name`, `last_name`, `phone`, `email`, `license_number`,
   `license_expiry`, `license_type`, `status`, `notes`, `created_by`)
VALUES
  ('DRV-0001', 'Ramon',   'Dela Cruz',  '09171234567', 'ramon.dc@gcr.com',
   'A01-23-456789', '2028-06-30', 'professional',     'available',
   'Senior driver. 8 years experience. Airport and inter-city routes.', 1),

  ('DRV-0002', 'Josefa',  'Santos',     '09281234567', 'josefa.s@gcr.com',
   'B02-24-112233', '2027-12-15', 'professional',     'available',
   'Licensed for vans and MPVs. Speaks English and Cebuano.', 1),

  ('DRV-0003', 'Eduardo', 'Reyes',      '09351234567', 'edu.reyes@gcr.com',
   'C03-22-778899', '2027-08-20', 'professional',     'on_duty',
   'Assigned to corporate shuttle runs. Punctual and professional.', 1),

  ('DRV-0004', 'Marites', 'Villanueva', '09461234567', 'marites.v@gcr.com',
   'D04-25-334455', '2029-03-10', 'professional',     'available',
   'Female driver. Preferred by female clients and families.', 1),

  ('DRV-0005', 'Rogelio', 'Bautista',   '09571234567', 'rogelio.b@gcr.com',
   'E05-23-556677', '2028-11-30', 'non_professional', 'off_duty',
   'Local routes only. Day off on weekends.', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- Done. Fleet = 8 vehicles, Drivers = 5, Compliance watchlist = 2 entries.
