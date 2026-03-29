<?php
require_once '../config/db.php';

$query = "ALTER TABLE loan_applications ADD COLUMN application_data TEXT AFTER loan_purpose";

if ($conn->query($query) === TRUE) {
    echo "Column application_data added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}

$conn->close();
?>

