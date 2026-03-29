<?php
$conn = new mysqli('localhost', 'root', '', 'microfin_db');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$queries = [
    // 1. Expand document_verification_status ENUM on clients table
    "ALTER TABLE clients MODIFY COLUMN document_verification_status ENUM('Unverified','Pending','Verified','Rejected','Approved') DEFAULT 'Unverified'",

    // 2. Add verification_status column to clients (if not exists)
    "ALTER TABLE clients ADD COLUMN IF NOT EXISTS verification_status ENUM('Unverified','Pending','Approved','Rejected') NOT NULL DEFAULT 'Unverified'",

    // 3. Expand verification_status ENUM on client_documents
    "ALTER TABLE client_documents MODIFY COLUMN verification_status ENUM('CONSIDER','Pending','Verified','Rejected','Expired') DEFAULT 'CONSIDER'",

    // 4. Sync existing rows: set verification_status='Approved' where document_verification_status='Verified'
    "UPDATE clients SET verification_status='Approved' WHERE document_verification_status='Verified'",

    // 5. Sync existing rows: set verification_status='Unverified' where document_verification_status='Unverified'
    "UPDATE clients SET verification_status='Unverified' WHERE document_verification_status='Unverified' AND verification_status='Unverified'",

    // 6. Normalise old CONSIDER status on client_documents to Pending
    "UPDATE client_documents SET verification_status='Pending' WHERE verification_status='CONSIDER'",
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "✅ " . htmlspecialchars(substr($q, 0, 80)) . "...<br>";
    } else {
        echo "❌ ERROR: " . $conn->error . "<br><small>" . htmlspecialchars($q) . "</small><br>";
    }
}

echo "<br><strong>Done!</strong> You can now delete this file.";
$conn->close();
?>
