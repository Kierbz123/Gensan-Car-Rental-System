-- Corrected Demo Seeding for Procurement Module (V2)
USE `gensan_car_rental_db`;

-- Cleanup any partial/failed demo data first
DELETE FROM `procurement_items` WHERE `pr_id` > 100;
DELETE FROM `procurement_requests` WHERE `pr_id` > 100;

-- 1. Tires Replacement for Fortuner
INSERT INTO `procurement_requests` 
(pr_id, pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, purpose_summary, status, current_approval_level, approval_workflow, created_at)
VALUES 
(101, 'PR-GCR-2026-0010', 4, 'maintenance', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'high', 28000.00, '4 Brand New Tires for Toyota Fortuner (GHI-2468)', 'approved', 2, '{"levels":{"1":{"role":"maintenance_supervisor","limit":5000,"can_approve":true},"2":{"role":"fleet_manager","limit":20000,"can_approve":true}},"current_level":2}', NOW());

INSERT INTO `procurement_items` 
(pr_id, line_number, item_description, item_category, specification, quantity, unit, estimated_unit_cost, vehicle_id, purpose, status)
VALUES 
(101, 1, 'Bridgestone Ecopia 265/60R18', 'tires', 'Heavy duty tires for SUV', 4, 'pcs', 7000.00, 'GCR-SU-0001', 'Scheduled tire replacement', 'fully_received');

-- 2. Battery for Toyota Vios
INSERT INTO `procurement_requests` 
(pr_id, pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, purpose_summary, status, current_approval_level, approval_workflow, created_at)
VALUES 
(102, 'PR-GCR-2026-0011', 4, 'maintenance', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'critical', 4500.00, '12V Motolite Gold Battery for Vios (ABC-1234)', 'pending_approval', 1, '{"levels":{"1":{"role":"maintenance_supervisor","limit":5000,"can_approve":true}},"current_level":1}', NOW());

INSERT INTO `procurement_items` 
(pr_id, line_number, item_description, item_category, specification, quantity, unit, estimated_unit_cost, vehicle_id, purpose, status)
VALUES 
(102, 1, 'Motolite Gold NS40', 'parts', 'Maintenance-free 12V battery', 1, 'pc', 4500.00, 'GCR-SD-0001', 'Battery failed inspection', 'pending');

-- 3. Monthly Diesel Allocation
INSERT INTO `procurement_requests` 
(pr_id, pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, purpose_summary, status, current_approval_level, approval_workflow, created_at)
VALUES 
(103, 'PR-GCR-2026-0012', 3, 'operations', CURDATE(), CURDATE(), 'medium', 125000.00, 'Fleet Diesel Fuel Allocation - March 2026', 'fully_received', 3, '{"levels":{"1":{"role":"maintenance_supervisor","limit":5000,"can_approve":true},"2":{"role":"fleet_manager","limit":20000,"can_approve":false},"3":{"role":"system_admin","limit":null,"can_approve":true}},"current_level":3}', NOW());

INSERT INTO `procurement_items` 
(pr_id, line_number, item_description, item_category, specification, quantity, unit, estimated_unit_cost, purpose, status)
VALUES 
(103, 1, 'Diesel Fuel', 'fuel', 'Shell V-Power Diesel', 2000, 'liters', 62.50, 'Monthly operations fuel budget', 'fully_received');

-- 4. Brake Pad Replacement (Batch)
INSERT INTO `procurement_requests` 
(pr_id, pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, purpose_summary, status, current_approval_level, approval_workflow, created_at)
VALUES 
(104, 'PR-GCR-2026-0013', 4, 'maintenance', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'medium', 7500.00, 'Front Brake Pad sets for fleet sedans', 'approved', 2, '{"levels":{"1":{"role":"maintenance_supervisor","limit":5000,"can_approve":true},"2":{"role":"fleet_manager","limit":20000,"can_approve":true}},"current_level":2}', NOW());

INSERT INTO `procurement_items` 
(pr_id, line_number, item_description, item_category, quantity, unit, estimated_unit_cost, purpose, status)
VALUES 
(104, 1, 'Toyota Vios Brake Pads', 'parts', 2, 'sets', 2500.00, 'Stock replenishment', 'pending'),
(104, 2, 'Honda City Brake Pads', 'parts', 1, 'set', 2500.00, 'Stock replenishment', 'pending');

-- 5. Car Wash Chemicals
INSERT INTO `procurement_requests` 
(pr_id, pr_number, requestor_id, department, request_date, required_date, urgency, total_estimated_cost, purpose_summary, status, current_approval_level, approval_workflow, created_at)
VALUES 
(105, 'PR-GCR-2026-0014', 2, 'operations', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'low', 5250.00, 'Quarterly refill of detailing kit', 'draft', 1, '{"levels":{"1":{"role":"maintenance_supervisor","limit":5000,"can_approve":true}},"current_level":1}', NOW());

INSERT INTO `procurement_items` 
(pr_id, line_number, item_description, item_category, quantity, unit, estimated_unit_cost, purpose, status)
VALUES 
(105, 1, 'Car Shampoo (5 Gal)', 'supplies', 2, 'pails', 1500.00, 'Daily cleaning', 'pending'),
(105, 2, 'Microfiber Towel Pack', 'supplies', 5, 'packs', 450.00, 'Vehicle detailing', 'pending');
