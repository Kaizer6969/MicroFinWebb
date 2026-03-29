<?php
require_once '../config/db.php';

echo "Adding has_seen_tour column to clients table...\n";

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM clients LIKE 'has_seen_tour'");

if ($check->num_rows == 0) {
    $sql = "ALTER TABLE clients ADD COLUMN has_seen_tour BOOLEAN DEFAULT FALSE";
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added has_seen_tour column.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column has_seen_tour already exists.\n";
}

$conn->close();
?>

