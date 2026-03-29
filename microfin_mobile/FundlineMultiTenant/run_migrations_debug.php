<?php
require_once 'config/db.php';

$files = [
    'includes/add_id_document_types.sql',
    'includes/add_comaker_columns.sql'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }

    echo "Running $file...\n";
    $sql = file_get_contents($file);
    
    // Split by semicolon for multiple queries, but rudimentary
    // These specific files seem to generally be single or few queries.
    // add_comaker_columns.sql is one ALTER statement.
    // add_id_document_types.sql is one INSERT statement.
    
    if ($conn->query($sql) === TRUE) {
        echo "Success.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

$conn->close();
?>
