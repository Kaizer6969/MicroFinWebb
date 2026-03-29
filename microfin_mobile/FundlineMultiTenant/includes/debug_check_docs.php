<?php
require_once '../config/db.php';

echo "Searching for any documents with 'ID' in the name...\n";

$sql = "SELECT c.first_name, c.last_name, cd.client_document_id, dt.document_name, cd.file_name 
        FROM client_documents cd 
        JOIN document_types dt ON cd.document_type_id = dt.document_type_id 
        JOIN clients c ON cd.client_id = c.client_id
        WHERE dt.document_name LIKE '%ID%'
        LIMIT 10";
        
$docs = $conn->query($sql);
if ($docs->num_rows > 0) {
    while($row = $docs->fetch_assoc()) {
        echo " - Client: " . $row['first_name'] . " | Doc: '" . $row['document_name'] . "' | File: " . $row['file_name'] . "\n";
    }
} else {
    echo "No documents found with 'ID' in the name.\n";
}

// Also dump all document types to be sure
echo "\nAvailable Document Types:\n";
$res = $conn->query("SELECT * FROM document_types");
while($row = $res->fetch_assoc()) {
    echo "- ID: " . $row['document_type_id'] . " | Name: '" . $row['document_name'] . "' | Active: " . $row['is_active'] . "\n";
}
?>

