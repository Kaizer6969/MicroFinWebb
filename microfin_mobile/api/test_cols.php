<?php
require 'db.php';
$res = $conn->query("DESCRIBE clients");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "==== client_documents ====\n";
$res2 = $conn->query("DESCRIBE client_documents");
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
