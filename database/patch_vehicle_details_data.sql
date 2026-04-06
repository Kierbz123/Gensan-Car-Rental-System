-- ============================================================
-- Patch: Vehicle Details Page Data Completeness
-- Adds missing compliance records and vehicle status logs
-- so all three sections in vehicle-details.php show real data.
-- Run with: mysql -u root gensan_car_rental_db < patch_vehicle_details_data.sql
-- ============================================================

USE `gensan_car_rental_db`;

-- Disable FK checks temporarily for safe insert
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
-- 1. COMPLIANCE RECORDS — fill vehicles with no records
-- Only INSERT if not already existing (by document_number uniqueness)
-- ─────────────────────────────────────────────────────────────

INSERT INTO `compliance_records`
    (`vehicle_id`, `compliance_type`, `document_number`, `issuing_authority`,
     `issue_date`, `expiry_date`, `renewal_cost`, `status`, `days_until_expiry`,
     `notes`, `created_by`, `created_at`, `updated_at`)
SELECT vals.*
FROM (
    SELECT 'GCR-HB-0002' v,'lto_registration' ct,'LTO-HB002-2025' dn,'Land Transportation Office - GenSan' ia,'2025-04-18' id,'2026-04-18' ed,2500.00 rc,'active' s,41 du,'Annual registration.' no,1 cb,'2026-03-05 03:58:21' ca,'2026-03-05 03:58:21' ua
    UNION ALL SELECT 'GCR-HB-0002','insurance_tpl','TPL-HB002-2025','Pioneer Insurance Corporation','2025-04-18','2026-04-18',2200.00,'active',41,'TPL — vehicle out of service.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-HB-0003','lto_registration','LTO-HB003-2026','Land Transportation Office - GenSan','2026-03-07','2027-03-07',2500.00,'active',364,'New vehicle registration.',1,'2026-03-07 12:36:08','2026-03-07 12:36:08'
    UNION ALL SELECT 'GCR-HB-0003','insurance_comprehensive','COMP-HB003-2026','Pioneer Insurance Corporation','2026-03-07','2027-03-07',12000.00,'active',364,'Full coverage for new vehicle.',1,'2026-03-07 12:36:08','2026-03-07 12:36:08'
    UNION ALL SELECT 'GCR-HB-0003','emission_test','EMI-HB003-2026','DENR Accredited Testing Center','2026-03-07','2027-03-07',500.00,'active',364,'Initial emission test passed.',1,'2026-03-07 12:36:08','2026-03-07 12:36:08'
    UNION ALL SELECT 'GCR-LV-0001','lto_registration','LTO-LV001-2025','Land Transportation Office - GenSan','2025-07-10','2026-07-10',5500.00,'active',124,'Annual renewal July 2025.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-LV-0001','insurance_comprehensive','COMP-LV001-2025','Pioneer Insurance Corporation','2025-07-10','2026-07-10',42000.00,'active',124,'Premium full coverage luxury van.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-LV-0001','emission_test','EMI-LV001-2025','DENR Accredited Testing Center','2025-07-10','2026-07-10',500.00,'active',124,'Annual emission test passed.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-LV-0002','lto_registration','LTO-LV002-2025','Land Transportation Office - GenSan','2025-08-22','2026-08-22',5500.00,'active',167,'Renewal Aug 2025.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-LV-0002','insurance_comprehensive','COMP-LV002-2025','Pioneer Insurance Corporation','2025-08-22','2026-08-22',38000.00,'active',167,'Comprehensive policy.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-LX-0001','lto_registration','LTO-LX001-2025','Land Transportation Office - GenSan','2025-03-01','2026-03-01',4800.00,'expired',-7,'EXPIRED — Renewal processing.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-LX-0001','insurance_comprehensive','COMP-LX001-2025','Pioneer Insurance Corporation','2025-03-01','2026-03-01',65000.00,'expired',-7,'High-value policy expired. Urgent renewal.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-LX-0001','emission_test','EMI-LX001-2025','DENR Accredited Testing Center','2025-03-01','2026-06-01',500.00,'active',85,'Semi-annual emission test valid.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-MP-0001','lto_registration','LTO-MP001-2025','Land Transportation Office - GenSan','2025-03-20','2026-03-20',3800.00,'renewal_pending',12,'Expiring soon! Renewal in progress.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-MP-0001','insurance_comprehensive','COMP-MP001-2025','Pioneer Insurance Corporation','2025-03-20','2026-03-20',28000.00,'renewal_pending',12,'Policy expires alongside LTO.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-MP-0001','emission_test','EMI-MP001-2025','DENR Accredited Testing Center','2025-07-15','2026-07-15',500.00,'active',129,'Passed emission test.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-MP-0002','lto_registration','LTO-MP002-2025','Land Transportation Office - GenSan','2025-02-14','2026-02-14',3200.00,'expired',-22,'EXPIRED — needs urgent renewal.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-MP-0002','insurance_comprehensive','COMP-MP002-2025','Pioneer Insurance Corporation','2025-02-14','2026-02-14',22000.00,'expired',-22,'Expired together with LTO.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-MP-0002','emission_test','EMI-MP002-2025','DENR Accredited Testing Center','2025-09-01','2026-09-01',500.00,'active',177,'Valid until Sept 2026.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-PU-0001','lto_registration','LTO-PU001-2025','Land Transportation Office - GenSan','2025-08-15','2026-08-15',4200.00,'active',160,'Annual renewal complete.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-PU-0001','insurance_comprehensive','COMP-PU001-2025','Pioneer Insurance Corporation','2025-08-15','2026-08-15',25000.00,'active',160,'Comprehensive coverage.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-PU-0002','lto_registration','LTO-PU002-2025','Land Transportation Office - GenSan','2025-03-25','2026-03-25',4200.00,'renewal_pending',17,'Coming up for renewal.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-PU-0002','insurance_comprehensive','COMP-PU002-2025','Pioneer Insurance Corporation','2025-03-25','2026-03-25',27000.00,'renewal_pending',17,'Comprehensive — renewal pending.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-SU-0002','lto_registration','LTO-SU002-2025','Land Transportation Office - GenSan','2025-06-01','2026-06-01',4800.00,'active',85,'Registration current.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-SU-0002','insurance_comprehensive','COMP-SU002-2025','Pioneer Insurance Corporation','2025-06-01','2026-06-01',30000.00,'active',85,'Premium SUV coverage.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-SU-0002','emission_test','EMI-SU002-2025','DENR Accredited Testing Center','2025-09-15','2026-09-15',500.00,'active',191,'Passed.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-VN-0002','lto_registration','LTO-VN002-2025','Land Transportation Office - GenSan','2025-03-18','2026-03-18',6000.00,'renewal_pending',10,'10 days to expiry!',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-VN-0002','insurance_tpl','TPL-VN002-2025','Pioneer Insurance Corporation','2025-03-18','2026-03-18',4200.00,'renewal_pending',10,'Renew with LTO.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
    UNION ALL SELECT 'GCR-VN-0002','emission_test','EMI-VN002-2025','DENR Accredited Testing Center','2025-09-05','2026-09-05',500.00,'active',181,'Passed annual emission test.',1,'2026-03-05 03:58:21','2026-03-05 03:58:21'
) vals(v,ct,dn,ia,id,ed,rc,s,du,no,cb,ca,ua)
WHERE NOT EXISTS (
    SELECT 1 FROM compliance_records cr 
    WHERE cr.vehicle_id = vals.v AND cr.document_number = vals.dn
);

-- ─────────────────────────────────────────────────────────────
-- 2. VEHICLE STATUS LOGS — add history for all vehicles
-- Only use user IDs 1-4 (existing users in the system)
-- ─────────────────────────────────────────────────────────────

INSERT INTO `vehicle_status_logs`
    (`vehicle_id`, `previous_status`, `new_status`, `previous_location`, `new_location`,
     `previous_mileage`, `new_mileage`, `reason`, `changed_by`, `changed_at`)
VALUES

-- GCR-SD-0001 (Toyota Vios — currently maintenance)
('GCR-SD-0001','available','rented','main_office','with_customer',
 14800,15000,'Dispatched for rental RA-GCR-2026-0004.',2,'2026-02-01 08:10:00'),
('GCR-SD-0001','rented','available','with_customer','main_office',
 15000,15200,'Returned. Clean condition verified.',2,'2026-02-03 09:00:00'),
('GCR-SD-0001','available','maintenance','main_office','main_office',
 15200,15200,'Pre-LTO renewal service and brake check.',4,'2026-03-06 10:00:00'),

-- GCR-HB-0001 (Toyota Wigo — available)
('GCR-HB-0001','available','rented','main_office','airport',
 4800,5000,'Airport pickup rental for CUST-00001.',2,'2026-02-01 08:00:00'),
('GCR-HB-0001','rented','available','airport','main_office',
 5000,5200,'Returned. No issues noted.',2,'2026-02-03 10:00:00'),

-- GCR-HB-0002 (Suzuki Celerio — out_of_service, already has log #6)
('GCR-HB-0002','out_of_service','out_of_service','main_office','main_office',
 1100,1200,'Body shop inspection completed. Awaiting parts arrival.',4,'2026-02-20 10:00:00'),

-- GCR-HB-0003 (Honda Civic — available, newly added)
('GCR-HB-0003','available','available','main_office','main_office',
 0,0,'Initial fleet onboarding. QR and compliance records created.',1,'2026-03-07 12:36:08'),

-- GCR-LV-0001 (Toyota Hiace Grandia — rented)
('GCR-LV-0001','available','rented','main_office','with_customer',
 13500,14000,'Wedding entourage rental RA-GCR-2026-0007 dispatched.',2,'2026-03-03 06:10:00'),
('GCR-LV-0001','rented','rented','with_customer','with_customer',
 14000,14500,'Mid-rental mileage log. Vehicle with client.',2,'2026-03-04 08:00:00'),

-- GCR-LV-0002 (Nissan NV350 — reserved)
('GCR-LV-0002','available','reserved','main_office','main_office',
 8000,8200,'Reserved for Sarangani resort transfer RA-GCR-2026-0005.',2,'2026-03-05 08:00:00'),

-- GCR-LX-0001 (Toyota Alphard — available but LTO expired)
('GCR-LX-0001','available','available','main_office','main_office',
 2500,2800,'Post-detailing inspection. Top condition.',2,'2026-02-28 16:00:00'),
('GCR-LX-0001','available','available','main_office','main_office',
 2800,2800,'LTO registration expired — flagged. Grounded pending renewal.',1,'2026-03-02 09:00:00'),

-- GCR-MP-0001 (Toyota Innova — available)
('GCR-MP-0001','available','rented','main_office','with_customer',
 18000,18200,'Lake Sebu family trip RA-GCR-2026-0009 dispatched.',2,'2026-02-20 06:10:00'),
('GCR-MP-0001','rented','available','with_customer','main_office',
 18200,18500,'Returned with full tank. Good condition.',2,'2026-02-22 19:00:00'),

-- GCR-MP-0002 (Mitsubishi Xpander — reserved)
('GCR-MP-0002','available','reserved','main_office','main_office',
 3600,3800,'Reserved for RA-GCR-2026-0010 family tour.',2,'2026-03-05 08:00:00'),

-- GCR-PU-0001 (Mitsubishi Strada — available)
('GCR-PU-0001','available','rented','main_office','with_customer',
 17000,18000,'Off-road trip RA-GCR-2026-0006. 4WD mode verified.',2,'2026-02-20 09:10:00'),
('GCR-PU-0001','rented','available','with_customer','main_office',
 18000,18500,'Returned successfully. Light dust cleaning.',2,'2026-02-23 10:00:00'),

-- GCR-SU-0001 (Toyota Fortuner — has log #3 already; add earlier dispatch)
('GCR-SU-0001','available','rented','main_office','with_customer',
 23000,23500,'Dispatched to CUST-00003 for Koronadal trip RA-GCR-2026-0003.',2,'2026-02-10 10:10:00'),

-- GCR-SU-0002 (Mitsubishi Montero Sport — rented, no prior log)
('GCR-SU-0002','available','rented','main_office','with_customer',
 10500,11000,'Corporate weekly commute rental RA-GCR-2026-0008 dispatched.',2,'2026-03-01 07:10:00'),

-- GCR-VN-0002 (Toyota Hiace — already has log #2; add more context)
('GCR-VN-0002','rented','rented','with_customer','with_customer',
 32000,35000,'Corporate shuttle mid-period mileage update.',2,'2026-03-01 09:00:00');

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;
