<?php
require_once '../config/db.php';

echo "Searching for 'Valid ID%' types:\n";
$res = $conn->query("SELECT * FROM document_types WHERE document_name LIKE 'Valid ID%'");
while($row = $res->fetch_assoc()) {
    echo "- ID: " . $row['document_type_id'] . " | Name: '" . $row['document_name'] . "'\n";
}
?>

