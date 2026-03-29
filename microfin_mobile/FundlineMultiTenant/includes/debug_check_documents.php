<?php
/**
 * Debug Document Types and Client Documents
 * Run this to check what's in the database
 */

require_once '../config/db.php';

echo "<h2>Document Types in Database</h2>";
$result = $conn->query("SELECT * FROM document_types ORDER BY document_type_id");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Document Name</th><th>Description</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['document_type_id']}</td>";
    echo "<td><strong>{$row['document_name']}</strong></td>";
    echo "<td>{$row['description']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>Recent Client Documents</h2>";
$result = $conn->query("
    SELECT 
        cd.client_document_id,
        cd.client_id,
        c.first_name,
        c.last_name,
        dt.document_name,
        cd.file_name,
        cd.file_path,
        cd.upload_date
    FROM client_documents cd
    JOIN document_types dt ON cd.document_type_id = dt.document_type_id
    JOIN clients c ON cd.client_id = c.client_id
    ORDER BY cd.upload_date DESC
    LIMIT 20
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Doc ID</th><th>Client</th><th>Document Type</th><th>File Name</th><th>Upload Date</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['client_document_id']}</td>";
    echo "<td>{$row['first_name']} {$row['last_name']} (ID: {$row['client_id']})</td>";
    echo "<td><strong>{$row['document_name']}</strong></td>";
    echo "<td>{$row['file_name']}</td>";
    echo "<td>{$row['upload_date']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>Documents by Client</h2>";
$result = $conn->query("
    SELECT 
        c.client_id,
        c.first_name,
        c.last_name,
        GROUP_CONCAT(dt.document_name SEPARATOR ', ') as documents
    FROM clients c
    LEFT JOIN client_documents cd ON c.client_id = cd.client_id
    LEFT JOIN document_types dt ON cd.document_type_id = dt.document_type_id
    WHERE c.document_verification_status = 'Pending'
    GROUP BY c.client_id
    ORDER BY c.registration_date DESC
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Client ID</th><th>Name</th><th>Documents Uploaded</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['client_id']}</td>";
    echo "<td>{$row['first_name']} {$row['last_name']}</td>";
    echo "<td>{$row['documents']}</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>

