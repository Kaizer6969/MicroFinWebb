<?php
require_once '../config/db.php';
$result = $conn->query("DESCRIBE clients");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
$conn->close();
?>

