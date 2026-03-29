<?php
require_once '../config/db.php';

// List of documents to make optional or remove requirement
$updates = [
    // Business
    'Business Financial Statements' => 0, // Make optional
    'Business Plan' => 0, // Make optional
    // Education
    'Tuition Fee Assessment' => 0, // Make optional
    'School ID' => 0, // Make optional
    // Agricultural
    'Farm Plan' => 0, // Make optional
    // Housing
    'Construction Estimate' => 0, // Make optional
    // Medical
    'Medical Certificate' => 0 // Keep strictly necessary? Maybe make optional and just require prescription?
                               // Actually, let's keep Medical Cert as 1 but Prescription as 0 (already 0).
];

foreach ($updates as $name => $required) {
    $stmt = $conn->prepare("UPDATE document_types SET is_required = ? WHERE document_name = ?");
    $stmt->bind_param("is", $required, $name);
    if ($stmt->execute()) {
        echo "Updated $name to " . ($required ? "Required" : "Optional") . "\n";
    }
    $stmt->close();
}

// Check what is left as required
echo "\nRemaining REQUIRED documents:\n";
$result = $conn->query("SELECT document_name, loan_purpose FROM document_types WHERE is_required = 1 AND is_active = 1 ORDER BY loan_purpose");
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['document_name'] . " (" . ($row['loan_purpose'] ?? 'General') . ")\n";
}

$conn->close();
?>

