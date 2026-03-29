-- Create payment_transactions table (NO FOREIGN KEYS to avoid errors)
CREATE TABLE IF NOT EXISTS payment_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_ref VARCHAR(100) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    loan_id INT NOT NULL,
    source_id VARCHAR(255) NULL COMMENT 'Payment gateway source ID',
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('PayMongo', 'GCash', 'Cash', 'Bank Transfer', 'Check') NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transaction_ref (transaction_ref),
    INDEX idx_client_loan (client_id, loan_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
