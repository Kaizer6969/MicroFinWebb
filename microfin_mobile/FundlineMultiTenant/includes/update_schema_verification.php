<?php
require_once '../config/db.php';

// Check if column exists first
$check_col = $conn->query("SHOW COLUMNS FROM clients LIKE 'document_verification_status'");
if ($check_col->num_rows == 0) {
    $sql1 = "ALTER TABLE clients ADD COLUMN document_verification_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending' AFTER registration_date";
    if ($conn->query($sql1) === TRUE) {
        echo "Column document_verification_status added successfully\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column document_verification_status already exists\n";
}

// Check if rejection reason column exists
$check_col2 = $conn->query("SHOW COLUMNS FROM clients LIKE 'verification_rejection_reason'");
if ($check_col2->num_rows == 0) {
    $sql2 = "ALTER TABLE clients ADD COLUMN verification_rejection_reason TEXT NULL AFTER document_verification_status";
    if ($conn->query($sql2) === TRUE) {
        echo "Column verification_rejection_reason added successfully\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column verification_rejection_reason already exists\n";
}

// Insert required document types
$doc_types = [
    ['Proof of Income', 'Upload proof of income (payslip, ITR, certificate of employment, etc.)', 1],
    ['Proof of Address', 'Upload proof of address (utility bill, barangay certificate, etc.)', 1],
    ['Valid ID', 'Upload a valid government-issued ID', 1]
];

foreach ($doc_types as $doc) {
    $check = $conn->prepare("SELECT document_type_id FROM document_types WHERE document_name = ?");
    $check->bind_param("s", $doc[0]);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO document_types (document_name, description, is_required, is_active) VALUES (?, ?, ?, 1)");
        $insert->bind_param("ssi", $doc[0], $doc[1], $doc[2]);
        if ($insert->execute()) {
            echo "Document type '{$doc[0]}' added successfully\n";
        }
        $insert->close();
    } else {
        echo "Document type '{$doc[0]}' already exists\n";
    }
    $check->close();
}

$conn->close();
echo "\nDatabase schema updated successfully!";
?>
