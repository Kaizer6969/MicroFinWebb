<?php
/**
 * Add missing seen_approval_modal column to clients table
 */

require_once '../config/db.php';

try {
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM clients LIKE 'seen_approval_modal'");
    
    if ($check->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE clients ADD COLUMN seen_approval_modal BOOLEAN DEFAULT FALSE";
        
        if ($conn->query($sql)) {
            echo "✅ Successfully added 'seen_approval_modal' column to clients table.\n";
        } else {
            echo "❌ Error adding column: " . $conn->error . "\n";
        }
    } else {
        echo "ℹ️ Column 'seen_approval_modal' already exists.\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

