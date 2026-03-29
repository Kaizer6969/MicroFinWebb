<?php
require 'db.php';
$res = $conn->query("DESCRIBE payment_transactions");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . " - " . $row['Default'] . "\n";
}
