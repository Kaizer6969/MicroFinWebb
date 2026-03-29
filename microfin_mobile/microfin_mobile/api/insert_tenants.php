<?php
$conn = new mysqli("localhost", "root", "1234", "microfin_db");

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "All tenants inserted successfully!\n";
}
else {
    echo "Error inserting tenants: " . $conn->error;
}
?>
