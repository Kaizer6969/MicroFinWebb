ALTER TABLE payment_transactions 
ADD COLUMN payment_type ENUM('regular', 'early_settlement') DEFAULT 'regular' AFTER payment_method;
