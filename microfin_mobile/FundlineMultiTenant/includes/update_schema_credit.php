<?php
require_once '../config/db.php';

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM clients LIKE 'credit_limit'");
if ($check->num_rows == 0) {
    // Add column
    if($conn->query("ALTER TABLE clients ADD COLUMN credit_limit DECIMAL(15,2) DEFAULT 0.00 AFTER document_verification_status")) {
        echo "Successfully added credit_limit column to clients table.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column credit_limit already exists.\n";
}

$conn->close();
?>

