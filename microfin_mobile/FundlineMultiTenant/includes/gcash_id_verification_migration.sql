-- ============================================================================
-- FUNDLINE MICROFINANCING - DATABASE MIGRATION
-- GCash-Style ID Verification Implementation
-- Run this script on your hosting database
-- ============================================================================

-- Step 1: Add ID Type and Front/Back Columns to Clients Table
-- ----------------------------------------------------------------------------
ALTER TABLE `clients` 
ADD COLUMN `id_type` VARCHAR(100) NULL COMMENT 'Type of government ID selected by client',
ADD COLUMN `valid_id_front` VARCHAR(255) NULL COMMENT 'Path to front photo of valid ID',
ADD COLUMN `valid_id_back` VARCHAR(255) NULL COMMENT 'Path to back photo of valid ID';

-- Step 2: Add New Document Types for Valid ID Front and Back
-- ----------------------------------------------------------------------------
INSERT INTO `document_types` (`document_name`, `description`, `is_required`) VALUES
('Valid ID Front', 'Front photo of government-issued valid ID', TRUE),
('Valid ID Back', 'Back photo of government-issued valid ID', TRUE)
ON DUPLICATE KEY UPDATE document_name = document_name;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- 
-- What was added:
-- 1. clients.id_type - Stores the selected ID type (e.g., "Driver's License")
-- 2. clients.valid_id_front - Stores file path to front ID image
-- 3. clients.valid_id_back - Stores file path to back ID image
-- 4. Two new document types in document_types table
--
-- Next steps:
-- 1. Test the verification form on apply_loan.php
-- 2. Upload documents with the new ID type selection
-- 3. Verify that both front and back images are saved
-- ============================================================================
