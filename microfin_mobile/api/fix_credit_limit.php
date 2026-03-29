<?php
require 'db.php';
$conn->query("UPDATE clients SET credit_limit = 50000.00 WHERE verification_status = 'Approved' AND (credit_limit = 0 OR credit_limit IS NULL)");
echo "Affected rows: " . $conn->affected_rows;
?>
