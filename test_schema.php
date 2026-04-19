<?php
require 'microfin_mobile/api/db.php';

$res = $conn->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE COLUMN_NAME = 'policy_metadata'");
while($row = $res->fetch_assoc()) {
    echo "Found policy_metadata in: " . $row['TABLE_NAME'] . "\n";
}

$res = $conn->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE COLUMN_NAME = 'application_data'");
while($row = $res->fetch_assoc()) {
    echo "Found application_data in: " . $row['TABLE_NAME'] . "\n";
}
?>
