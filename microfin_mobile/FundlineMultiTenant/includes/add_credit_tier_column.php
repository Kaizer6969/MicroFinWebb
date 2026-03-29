<?php
/**
 * Add credit_limit_tier column to clients table
 */

require_once '../config/db.php';

try {
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM clients LIKE 'credit_limit_tier'");
    
    if ($check->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE clients ADD COLUMN credit_limit_tier INT DEFAULT 0 AFTER seen_approval_modal";
        
        if ($conn->query($sql)) {
            echo "✅ Successfully added 'credit_limit_tier' column to clients table.\n";
        } else {
            echo "❌ Error adding column: " . $conn->error . "\n";
        }
    } else {
        echo "ℹ️ Column 'credit_limit_tier' already exists.\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

