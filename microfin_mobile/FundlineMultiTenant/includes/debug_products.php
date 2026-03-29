<?php
require_once '../config/db.php';

$stmt = $conn->prepare("SELECT product_name, interest_rate FROM loan_products WHERE is_active = TRUE");
$stmt->execute();
$result = $stmt->get_result();

echo "Active Products:\n";
while ($row = $result->fetch_assoc()) {
    echo "Product: {$row['product_name']}, Rate: {$row['interest_rate']}\n";
}
$conn->close();
?>

