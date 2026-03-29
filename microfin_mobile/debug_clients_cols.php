<?php
require 'api/db.php';
$res = $conn->query('SHOW COLUMNS FROM clients');
while($row = $res->fetch_assoc()) echo $row['Field'] . ' ';
?>
