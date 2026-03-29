<?php
/**
 * Add payment_date column to payment_transactions table
 */

require_once '../config/db.php';

try {
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM payment_transactions LIKE 'payment_date'");
    
    if ($check->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE payment_transactions ADD COLUMN payment_date DATETIME NULL AFTER status";
        
        if ($conn->query($sql)) {
            echo "✅ Successfully added 'payment_date' column to payment_transactions table.\n";
        } else {
            echo "❌ Error adding column: " . $conn->error . "\n";
        }
    } else {
        echo "ℹ️ Column 'payment_date' already exists.\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

