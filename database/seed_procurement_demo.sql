-- Demo Seeding for Procurement Module
USE `gensan_car_rental_db`;

-- 1. Tires Replacement for Fortuner (GCR-SU-0001)
SET @pr_number = 'PR-GCR-2026-0010';
INSERT INTO `procurement_requests` 
(pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, description, status, current_approval_level, created_at)
VALUES 
(@pr_number, 4, 'maintenance', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'high', 28000.00, '4 Brand New Tires for Toyota Fortuner (GHI-2468) - Bridgestone Ecopia.', 'approved', 2, NOW());

SET @pr_id = LAST_INSERT_ID();
INSERT INTO `procurement_items` 
(pr_id, item_number, item_name, category, description, quantity, unit, estimated_unit_cost, total_estimated_cost, vehicle_id, purpose, status)
VALUES 
(@pr_id, 1, 'Bridgestone Ecopia 265/60R18', 'tires', 'Heavy duty tires for SUV', 4, 'pcs', 7000.00, 28000.00, 'GCR-SU-0001', 'Scheduled tire replacement due to wear', 'fully_received');

-- 2. Battery for Toyota Vios (GCR-SD-0001)
SET @pr_number = 'PR-GCR-2026-0011';
INSERT INTO `procurement_requests` 
(pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, description, status, current_approval_level, created_at)
VALUES 
(@pr_number, 4, 'maintenance', NOW(), DATE_ADD(NOW(), INTERVAL 2 DAY), 'critical', 4500.00, '12V Motolite Gold Battery for Vios (ABC-1234)', 'pending_approval', 1, NOW());

SET @pr_id = LAST_INSERT_ID();
INSERT INTO `procurement_items` 
(pr_id, item_number, item_name, category, description, quantity, unit, estimated_unit_cost, total_estimated_cost, vehicle_id, purpose, status)
VALUES 
(@pr_id, 1, 'Motolite Gold NS40', 'auto_parts', 'Maintenance-free 12V battery', 1, 'pc', 4500.00, 4500.00, 'GCR-SD-0001', 'Battery failed testing during inspection', 'pending');

-- 3. Monthly Diesel Allocation for March 2026
SET @pr_number = 'PR-GCR-2026-0012';
INSERT INTO `procurement_requests` 
(pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, description, status, current_approval_level, created_at)
VALUES 
(@pr_number, 3, 'operations', NOW(), NOW(), 'medium', 125000.00, 'Fleet Diesel Fuel Allocation - March 2026', 'fully_received', 3, NOW());

SET @pr_id = LAST_INSERT_ID();
INSERT INTO `procurement_items` 
(pr_id, item_number, item_name, category, description, quantity, unit, estimated_unit_cost, total_estimated_cost, purpose, status)
VALUES 
(@pr_id, 1, 'Diesel Fuel', 'fuel', 'Shell V-Power Diesel', 2000, 'liters', 62.50, 125000.00, 'Monthly operations fuel budget', 'fully_received');

-- 4. Brake Pad Replacement (Batch for 3 Sedans)
SET @pr_number = 'PR-GCR-2026-0013';
INSERT INTO `procurement_requests` 
(pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, description, status, current_approval_level, created_at)
VALUES 
(@pr_number, 4, 'maintenance', NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY), 'medium', 7500.00, 'Front Brake Pad sets for Toyota Vios and Honda City fleet', 'approved', 2, NOW());

SET @pr_id = LAST_INSERT_ID();
INSERT INTO `procurement_items` 
(pr_id, item_number, item_name, category, description, quantity, unit, estimated_unit_cost, total_estimated_cost, purpose, status)
VALUES 
(@pr_id, 1, 'Genuine Toyota Vios Brake Pads', 'auto_parts', 'Front disk brake pads', 2, 'sets', 2500.00, 5000.00, 'Stock replenishment for fleet maintenance', 'pending'),
(@pr_id, 2, 'Honda City Brake Pads', 'auto_parts', 'OEM specification brake pads', 1, 'set', 2500.00, 2500.00, 'Stock replenishment', 'pending');

-- 5. Car Wash Chemicals & Detailing Kit
SET @pr_number = 'PR-GCR-2026-0014';
INSERT INTO `procurement_requests` 
(pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, description, status, current_approval_level, created_at)
VALUES 
(@pr_number, 2, 'operations', NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 'low', 5250.00, 'Quarterly refill of car wash chemicals and detailing tools', 'draft', 1, NOW());

SET @pr_id = LAST_INSERT_ID();
INSERT INTO `procurement_items` 
(pr_id, item_number, item_name, category, description, quantity, unit, estimated_unit_cost, total_estimated_cost, purpose, status)
VALUES 
(@pr_id, 1, 'Car Shampoo (5 Gal)', 'carwash_supplies', 'Concentrated pH neutral shampoo', 2, 'pails', 1500.00, 3000.00, 'Daily vehicle cleaning', 'pending'),
(@pr_id, 2, 'Microfiber Towel Pack', 'carwash_supplies', 'Pack of 10 ultra-soft towels', 5, 'packs', 450.00, 2250.00, 'Vehicle detailing', 'pending');
