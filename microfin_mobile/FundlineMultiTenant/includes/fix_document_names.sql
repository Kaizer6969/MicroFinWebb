-- Fix Document Type Name Mismatch
-- This renames "Proof of Billing" to "Proof of Address" to match the code

-- First, check if "Proof of Billing" exists and rename it
UPDATE document_types 
SET document_name = 'Proof of Address', 
    description = 'Upload proof of address (utility bill, barangay certificate, etc.)'
WHERE document_name = 'Proof of Billing';

-- Ensure "Proof of Address" is marked as required
UPDATE document_types 
SET is_required = 1, is_active = 1 
WHERE document_name = 'Proof of Address';

-- Just to be safe, insert if it doesn't exist
INSERT IGNORE INTO document_types (document_name, description, is_required, is_active) 
VALUES ('Proof of Address', 'Upload proof of address (utility bill, barangay certificate, etc.)', 1, 1);

-- Disable old billing entry if it somehow still exists
UPDATE document_types 
SET is_active = 0 
WHERE document_name = 'Proof of Billing';
