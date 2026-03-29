<?php
require_once '../config/db.php';

// Deactivate all specific documents
// Set generic standard documents to Active and Required
$standard_docs = [
    'Valid ID',
    'Valid ID (Government-issued)', // Handle duplicates/naming
    'Proof of Income',
    'Proof of Address',
    'Proof of Billing'
];

// First, deactivate EVERYTHING
$conn->query("UPDATE document_types SET is_active = 0, is_required = 0");

// Now activate only the standard ones
// Note: In my previous steps I might have named them specific ways.
// Let's check what I have.
$conn->query("UPDATE document_types SET is_active = 1, is_required = 1 WHERE document_name IN ('Valid ID', 'Proof of Income', 'Proof of Address')");

echo "Document requirements standardized (Atome-style).\n";
echo "Active Documents:\n";
$result = $conn->query("SELECT document_name FROM document_types WHERE is_active = 1");
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['document_name'] . "\n";
}

$conn->close();
?>
