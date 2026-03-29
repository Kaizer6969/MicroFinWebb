<?php
require_once '../config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS client_documents (
    client_document_id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    document_type_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(client_id),
    FOREIGN KEY (document_type_id) REFERENCES document_types(document_type_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table client_documents created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}
$conn->close();
?>

