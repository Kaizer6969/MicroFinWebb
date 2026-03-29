<?php
$conn = new mysqli('localhost', 'root', '', 'microfin_db');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$queries = [
    // Add purpose_category to loan_applications
    "ALTER TABLE loan_applications ADD COLUMN IF NOT EXISTS purpose_category VARCHAR(100) AFTER interest_rate",
    
    // Also check for comaker_income which seems to be used in api_apply_loan.php but might be missing/mismatched in name
    "ALTER TABLE loan_applications ADD COLUMN IF NOT EXISTS comaker_income DECIMAL(15,2) AFTER comaker_address",
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "✅ " . htmlspecialchars(substr($q, 0, 80)) . "...<br>";
    } else {
        echo "❌ ERROR: " . $conn->error . "<br><small>" . htmlspecialchars($q) . "</small><br>";
    }
}

echo "<br><strong>Done!</strong>";
$conn->close();
?>
