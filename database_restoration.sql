-- ==================================================
-- INTERNCONNECT DATABASE RESTORATION SCRIPT
-- ==================================================
-- This script restores your database and adds missing columns
-- Run this AFTER importing your sql3806785.sql backup

-- ==================================================
-- 1. ADD CRITICAL MOA COLUMNS (REQUIRED)
-- ==================================================
-- Your MOA functionality will break without these columns

ALTER TABLE `host_training_establishment`
  ADD COLUMN `MOA_FILE_URL` varchar(500) DEFAULT NULL COMMENT 'Cloudinary URL for MOA PDF document',
  ADD COLUMN `MOA_PUBLIC_ID` varchar(255) DEFAULT NULL COMMENT 'Cloudinary public ID for file management',
  ADD COLUMN `MOA_START_DATE` date DEFAULT NULL COMMENT 'MOA validity start date',
  ADD COLUMN `MOA_END_DATE` date DEFAULT NULL COMMENT 'MOA validity end date',  
  ADD COLUMN `MOA_UPLOAD_DATE` timestamp NULL DEFAULT NULL COMMENT 'When MOA was uploaded';

-- ==================================================
-- 2. IMPROVE SESSION MANAGEMENT (RECOMMENDED)
-- ==================================================
-- These columns will help with better session tracking

ALTER TABLE `session_details`
  ADD COLUMN `SESSION_ID` int(11) AUTO_INCREMENT COMMENT 'Unique session identifier',
  ADD COLUMN `SESSION_NAME` varchar(100) DEFAULT NULL COMMENT 'Descriptive session name',
  ADD COLUMN `START_DATE` date DEFAULT NULL COMMENT 'Session start date',
  ADD COLUMN `END_DATE` date DEFAULT NULL COMMENT 'Session end date',
  ADD PRIMARY KEY (`SESSION_ID`);

-- ==================================================
-- 3. STANDARDIZE ATTENDANCE COLUMNS (OPTIONAL)
-- ==================================================
-- Alternative column names for compatibility

ALTER TABLE `interns_attendance`
  ADD COLUMN `ATTENDANCE_DATE` date DEFAULT NULL COMMENT 'Alternative to ON_DATE',
  ADD COLUMN `TIME_IN` time DEFAULT NULL COMMENT 'Alternative to TIMEIN', 
  ADD COLUMN `TIME_OUT` time DEFAULT NULL COMMENT 'Alternative to TIMEOUT';

-- ==================================================
-- 4. CREATE INDEXES FOR PERFORMANCE (RECOMMENDED)
-- ==================================================

-- Index for MOA queries
CREATE INDEX `idx_hte_moa_dates` ON `host_training_establishment` (`MOA_START_DATE`, `MOA_END_DATE`);

-- Index for session queries  
CREATE INDEX `idx_session_dates` ON `session_details` (`START_DATE`, `END_DATE`);

-- ==================================================
-- 5. VERIFICATION QUERIES
-- ==================================================
-- Run these to verify the restoration worked

-- Check if MOA columns exist
DESCRIBE `host_training_establishment`;

-- Count total records in key tables
SELECT 
  'coordinator' as table_name, COUNT(*) as record_count FROM coordinator
UNION ALL
SELECT 'host_training_establishment', COUNT(*) FROM host_training_establishment  
UNION ALL
SELECT 'student_evaluation', COUNT(*) FROM student_evaluation
UNION ALL  
SELECT 'weekly_reports', COUNT(*) FROM weekly_reports
UNION ALL
SELECT 'interns_attendance', COUNT(*) FROM interns_attendance;

-- Check latest data timestamps
SELECT 
  'Latest coordinator entry' as info, MAX(COORDINATOR_ID) as value FROM coordinator
UNION ALL
SELECT 'Latest HTE entry', MAX(HTE_ID) FROM host_training_establishment
UNION ALL  
SELECT 'Latest attendance entry', MAX(ID) FROM interns_attendance;

-- ==================================================
-- RESTORATION COMPLETE!
-- ==================================================
-- Next steps:
-- 1. Update database/database.php with InfinityFree credentials  
-- 2. Test MOA upload functionality
-- 3. Verify all application features work correctly