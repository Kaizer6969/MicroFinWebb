<?php
require_once '../config/db.php';

$query = "UPDATE loan_products SET interest_rate = 3.00 WHERE product_name LIKE '%Business Loan%'";

if ($conn->query($query) === TRUE) {
    echo "Business Loan rate updated to 3.00% successfully.";
} else {
    echo "Error updating record: " . $conn->error;
}
$conn->close();
?>

