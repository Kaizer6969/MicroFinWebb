-- Migration: Add ID Type and Separate Front/Back ID Fields
-- This updates the clients table to support GCash-style ID verification

-- Add new columns for ID type and separate front/back images
ALTER TABLE `clients` 
ADD COLUMN `id_type` VARCHAR(100) NULL COMMENT 'Type of government ID selected by client',
ADD COLUMN `valid_id_front` VARCHAR(255) NULL COMMENT 'Path to front photo of valid ID',
ADD COLUMN `valid_id_back` VARCHAR(255) NULL COMMENT 'Path to back photo of valid ID';
