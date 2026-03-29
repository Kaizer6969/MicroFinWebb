ALTER TABLE clients 
ADD COLUMN comaker_name VARCHAR(150),
ADD COLUMN comaker_relationship VARCHAR(50),
ADD COLUMN comaker_contact VARCHAR(20),
ADD COLUMN comaker_income DECIMAL(12, 2) DEFAULT 0.00,
ADD COLUMN comaker_house_no VARCHAR(50),
ADD COLUMN comaker_street VARCHAR(100),
ADD COLUMN comaker_barangay VARCHAR(100),
ADD COLUMN comaker_city VARCHAR(100),
ADD COLUMN comaker_province VARCHAR(100),
ADD COLUMN comaker_postal_code VARCHAR(10);
