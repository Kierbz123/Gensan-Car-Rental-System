-- ==============================================================================
-- Candidate Tables for Removal / Consolidation
-- ==============================================================================
-- Note: Your database schema is very well-normalized, structured, and covers 
-- a full-scale ERP logistics & car rental application. Truly "useless" tables 
-- are rare here, but the following are tables that could be simplified, dropped, 
-- or handled outside the database to reduce schema bloat.

-- 1. security_logs
-- REASON: Highly redundant. You already have a very robust `audit_logs` table 
-- that includes a `severity` column and an `event_type` (`action` enum). 
-- You can simply update `audit_logs.action` to include 'security_event', 
-- 'failed_login', etc., and avoid maintaining two separate system log tables.
DROP TABLE IF EXISTS `security_logs`;

-- 2. rate_limit
-- REASON: Rate limiting in an SQL database is an anti-pattern. It generates 
-- high disk I/O and rapid bloat because it writes on almost every request. 
-- Rate limiting is much better handled in-memory using tools like Redis, 
-- Memcached, or even a fast file-system cache rather than persisting to MySQL.
DROP TABLE IF EXISTS `rate_limit`;

-- 3. backups
-- REASON: Storing backup metadata inside the database you are backing up is 
-- often an anti-pattern (if the DB crashes or corrupts, you can't query the 
-- backups table to know where your backups are). Backups are better tracked 
-- at the file-system level, via chronological file naming, or server-level 
-- cron/bash scripts decoupled from the application DB.
DROP TABLE IF EXISTS `backups`;
