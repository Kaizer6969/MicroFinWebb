<?php
/**
 * Add last_seen_credit_limit column to clients table
 * This column tracks the last credit limit value the user has acknowledged
 */

require_once '../config/db.php';

try {
    // Check if column already exists
    $check = $conn->query("SHOW COLUMNS FROM clients LIKE 'last_seen_credit_limit'");
    
    if ($check->num_rows == 0) {
        // Add the column
        $sql = "ALTER TABLE clients ADD COLUMN last_seen_credit_limit DECIMAL(15,2) DEFAULT 0.00 AFTER credit_limit";
        
        if ($conn->query($sql)) {
            echo "✅ Successfully added 'last_seen_credit_limit' column to clients table.\n";
            
            // Initialize existing clients' last_seen_credit_limit to their current credit_limit
            // This prevents the modal from showing for existing users on first implementation
            $init_sql = "UPDATE clients SET last_seen_credit_limit = credit_limit WHERE last_seen_credit_limit = 0";
            if ($conn->query($init_sql)) {
                echo "✅ Initialized last_seen_credit_limit for existing clients.\n";
            }
        } else {
            echo "❌ Error adding column: " . $conn->error . "\n";
        }
    } else {
        echo "ℹ️ Column 'last_seen_credit_limit' already exists.\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
