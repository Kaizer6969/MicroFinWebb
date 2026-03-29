<?php
require 'db.php';
$res = $conn->query("DESCRIBE loan_products");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
