<?php
require_once 'db.php';
$res = $conn->query("DESCRIBE loan_applications");
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
