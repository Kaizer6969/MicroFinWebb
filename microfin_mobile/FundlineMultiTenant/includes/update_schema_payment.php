<?php
require_once 'config/db.php';

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM payment_transactions LIKE 'payment_type'");
if ($check->num_rows == 0) {
    echo "Adding payment_type column...\n";
    if ($conn->query("ALTER TABLE payment_transactions ADD COLUMN payment_type VARCHAR(50) DEFAULT 'regular'")) {
        echo "Success: payment_type column added.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "Column payment_type already exists.\n";
}

$conn->close();
?>

