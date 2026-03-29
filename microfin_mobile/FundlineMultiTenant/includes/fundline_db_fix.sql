-- Fundline Database Fix Script
-- Run this in your database (e.g., phpMyAdmin) to fix the "Still Error" issues.

-- 1. Fix Clients Table (Missing Columns for Apply Loan)
-- These columns are needed to check if a client is verified before they can apply.

SET @dbname = DATABASE();
SET @tablename = "clients";
SET @columnname = "document_verification_status";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE clients ADD COLUMN document_verification_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending' AFTER registration_date;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "verification_rejection_reason";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE clients ADD COLUMN verification_rejection_reason TEXT NULL AFTER document_verification_status;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Create client_documents table (Required for Manage Profile)
-- Your existing schema has 'kyc_documents', but the code looks for 'client_documents'.
-- We create this table to ensure the file upload feature works.

CREATE TABLE IF NOT EXISTS client_documents (
    client_document_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    document_type_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    FOREIGN KEY (document_type_id) REFERENCES document_types(document_type_id)
);

-- 3. Ensure Document Types Exist (Prevent dropdown errors)
INSERT IGNORE INTO document_types (document_name, description, is_required, is_active) VALUES
('Proof of Income', 'Upload proof of income (payslip, ITR, certificate of employment, etc.)', 1, 1),
('Proof of Address', 'Upload proof of address (utility bill, barangay certificate, etc.)', 1, 1),
('Valid ID', 'Upload a valid government-issued ID', 1, 1);

-- 4. Fix potential discrepancies in System Settings (optional but recommended)
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_category, description, data_type) VALUES
('company_name', 'FUNDLINE Micro Financing Services', 'Company', 'Company name', 'String');
