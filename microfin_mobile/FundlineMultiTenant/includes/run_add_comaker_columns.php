<?php
/**
 * Run this script once to add comaker columns to clients table
 */

require_once '../config/db.php';

echo "Adding comaker columns to clients table...\n";

$sql = file_get_contents(__DIR__ . '/add_comaker_columns.sql');

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    
    echo "✓ Comaker columns added successfully!\n";
} else {
    echo "✗ Error: " . $conn->error . "\n";
}

$conn->close();
?>

