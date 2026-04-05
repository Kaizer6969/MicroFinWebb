<?php
require_once __DIR__ . '/db.php';
$r = $conn->query('DESCRIBE loans');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}
?>
