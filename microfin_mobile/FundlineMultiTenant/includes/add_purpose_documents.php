<?php
require_once '../config/db.php';

// Add loan_purpose column to document_types if not exists
$check_col = $conn->query("SHOW COLUMNS FROM document_types LIKE 'loan_purpose'");
if ($check_col->num_rows == 0) {
    $sql = "ALTER TABLE document_types ADD COLUMN loan_purpose VARCHAR(100) NULL AFTER is_active";
    if ($conn->query($sql) === TRUE) {
        echo "Column loan_purpose added successfully\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column loan_purpose already exists\n";
}

// Add purpose-specific document types
$purpose_docs = [
    // Business Loan Documents
    ['Business Permit', 'Valid business permit or DTI/SEC registration', 1, 'Business'],
    ['Business Financial Statements', 'Latest financial statements or income records', 1, 'Business'],
    ['Business Plan', 'Business plan or proposal (for new businesses)', 0, 'Business'],
    
    // Personal Loan Documents (already covered by client_documents)
    
    // Education Loan Documents
    ['School Enrollment Certificate', 'Certificate of enrollment or admission letter', 1, 'Education'],
    ['School ID', 'Valid school ID', 1, 'Education'],
    ['Tuition Fee Assessment', 'Official assessment of tuition and fees', 1, 'Education'],
    
    // Agricultural Loan Documents
    ['Land Title/Lease Agreement', 'Proof of land ownership or lease', 1, 'Agricultural'],
    ['Farm Plan', 'Detailed farm plan or proposal', 1, 'Agricultural'],
    
    // Medical/Emergency Loan Documents
    ['Medical Certificate', 'Medical certificate or hospital bill', 1, 'Medical'],
    ['Prescription/Treatment Plan', 'Doctor\'s prescription or treatment plan', 0, 'Medical'],
    
    // Housing Loan Documents
    ['Property Documents', 'Land title, tax declaration, or contract to sell', 1, 'Housing'],
    ['Construction Estimate', 'Detailed construction estimate or quotation', 1, 'Housing']
];

foreach ($purpose_docs as $doc) {
    $check = $conn->prepare("SELECT document_type_id FROM document_types WHERE document_name = ?");
    $check->bind_param("s", $doc[0]);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO document_types (document_name, description, is_required, is_active, loan_purpose) VALUES (?, ?, ?, 1, ?)");
        $insert->bind_param("ssis", $doc[0], $doc[1], $doc[2], $doc[3]);
        if ($insert->execute()) {
            echo "Document type '{$doc[0]}' for {$doc[3]} loans added successfully\n";
        } else {
            echo "Error adding '{$doc[0]}': " . $insert->error . "\n";
        }
        $insert->close();
    } else {
        echo "Document type '{$doc[0]}' already exists\n";
    }
    $check->close();
}

$conn->close();
echo "\nPurpose-specific document types added successfully!";
?>

