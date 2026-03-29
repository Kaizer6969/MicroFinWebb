-- Migration: Add Valid ID Front and Back document types
-- This adds new document types for separate front/back ID uploads

-- Add new document types for front and back ID
INSERT INTO `document_types` (`document_name`, `description`) VALUES
('Valid ID Front', 'Front photo of government-issued valid ID'),
('Valid ID Back', 'Back photo of government-issued valid ID')
ON DUPLICATE KEY UPDATE document_name = document_name;
