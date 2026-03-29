<?php
require 'db.php';
$res = $conn->query("DESCRIBE payment_transactions");
$out = "";
while($row = $res->fetch_assoc()) {
    $out .= $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . " - " . $row['Default'] . "\n";
}
file_put_contents('schema.txt', $out);
