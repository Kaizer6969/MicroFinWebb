-- Fix verification status for existing users
-- This script updates users who have 0 documents uploaded but are marked as 'Pending'
-- It sets them to 'Unverified' so they can submit documents.

-- 1. Ensure 'Unverified' is an allowed enum value (if not already added)
ALTER TABLE clients MODIFY COLUMN document_verification_status 
ENUM('Unverified', 'Pending', 'Approved', 'Rejected') DEFAULT 'Unverified';

-- 2. Update users with 0 documents to 'Unverified'
UPDATE clients c
SET c.document_verification_status = 'Unverified'
WHERE c.document_verification_status = 'Pending'
AND (SELECT COUNT(*) FROM client_documents cd WHERE cd.client_id = c.client_id) = 0;

-- 3. Verify the changes (Optional)
SELECT client_id, first_name, last_name, document_verification_status 
FROM clients 
WHERE document_verification_status = 'Unverified';
