<?php
require_once '../config/db.php';

$sql = file_get_contents('add_comaker_columns.sql');

if ($conn->multi_query($sql)) {
    echo "Successfully altered clients table.\n";
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>
