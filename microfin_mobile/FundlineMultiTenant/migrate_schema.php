<?php
require_once 'config/db.php';

echo "Running migrations...\n";

// Add has_seen_tour column
$sql1 = "ALTER TABLE clients ADD COLUMN has_seen_tour TINYINT(1) DEFAULT 0";
if ($conn->query($sql1) === TRUE) {
    echo "Added has_seen_tour column successfully.\n";
} else {
    // 1060 is Duplicate column name error
    if ($conn->errno == 1060) {
        echo "has_seen_tour column already exists.\n";
    } else {
        echo "Error adding has_seen_tour: " . $conn->error . "\n";
    }
}

// Add seen_rejection_modal column
$sql2 = "ALTER TABLE clients ADD COLUMN seen_rejection_modal TINYINT(1) DEFAULT 0";
if ($conn->query($sql2) === TRUE) {
    echo "Added seen_rejection_modal column successfully.\n";
} else {
    if ($conn->errno == 1060) {
        echo "seen_rejection_modal column already exists.\n";
    } else {
        echo "Error adding seen_rejection_modal: " . $conn->error . "\n";
    }
}

echo "Migration script finished.\n";
?>
