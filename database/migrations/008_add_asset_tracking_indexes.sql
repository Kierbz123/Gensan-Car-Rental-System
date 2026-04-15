-- ============================================================
-- Migration 008: Asset Tracking Performance Indexes
-- Applied: 2026-04-14
-- Scope: vehicles, rental_agreements, vehicle_status_logs
-- ============================================================

-- vehicles: accelerate fleet filtering, status queries, duplicate checks
ALTER TABLE vehicles
    ADD INDEX IF NOT EXISTS idx_vehicles_plate    (plate_number),
    ADD INDEX IF NOT EXISTS idx_vehicles_status   (current_status),
    ADD INDEX IF NOT EXISTS idx_vehicles_category (category_id),
    ADD INDEX IF NOT EXISTS idx_vehicles_deleted  (deleted_at);

-- rental_agreements: accelerate active rental/availability checks
ALTER TABLE rental_agreements
    ADD INDEX IF NOT EXISTS idx_ra_vehicle_status (vehicle_id, status);

-- vehicle_status_logs: accelerate history queries on vehicle-details.php
ALTER TABLE vehicle_status_logs
    ADD INDEX IF NOT EXISTS idx_vsl_vehicle_time  (vehicle_id, changed_at);
