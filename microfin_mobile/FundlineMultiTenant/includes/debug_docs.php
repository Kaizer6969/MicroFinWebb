<?php
require_once '../config/db.php';

$sql = "SELECT * FROM document_types";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row["document_type_id"]. " - Name: " . $row["document_name"]. "\n";
    }
} else {
    echo "0 results";
}
$conn->close();
?>
