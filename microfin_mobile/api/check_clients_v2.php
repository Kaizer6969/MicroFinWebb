<?php
require 'db.php';
$res = $conn->query("DESCRIBE clients");
while($row = $res->fetch_assoc()) {
    printf("%-30s | %-50s\n", $row['Field'], $row['Type']);
}
?>
