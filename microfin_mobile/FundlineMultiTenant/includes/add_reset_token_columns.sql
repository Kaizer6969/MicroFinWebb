-- Add reset token columns to users table for forgot password functionality

ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(64) NULL,
ADD COLUMN reset_token_expiry DATETIME NULL;

-- Add index for faster token lookups
ALTER TABLE users 
ADD INDEX idx_reset_token (reset_token);
