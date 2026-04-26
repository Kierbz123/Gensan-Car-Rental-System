-- Migration 007: Add qr_scanner to users.role enum
-- Run this against your gensan_car_rental_db database.

ALTER TABLE `users`
    MODIFY COLUMN `role`
        ENUM(
            'system_admin',
            'fleet_manager',
            'procurement_officer',
            'maintenance_supervisor',
            'customer_service_staff',
            'mechanic',
            'viewer',
            'qr_scanner'
        ) NOT NULL DEFAULT 'viewer'
        COMMENT 'System role controlling module access';
