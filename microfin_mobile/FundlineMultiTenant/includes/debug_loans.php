<?php
require_once '../config/db.php';

// Print DB Info
echo "DB Host info: " . $conn->host_info . "\n";
echo "DB Server info: " . $conn->server_info . "\n";
// Try to print DB name if possible, or query it
$db_result = $conn->query("SELECT DATABASE()");
$row = $db_result->fetch_row();
echo "Selected DB: " . $row[0] . "\n";

// List databases
$dbs = $conn->query("SHOW DATABASES");
echo "Databases:\n";
while ($r = $dbs->fetch_row()) {
    echo " - " . $r[0] . "\n";
}

// Search for the specific timestamp in loan_number
$suffix = '1769354160';
$query = "SELECT * FROM loans WHERE loan_number LIKE '%$suffix%'";
$result = $conn->query($query);

echo "--- SEARCH BY SUFFIX $suffix ---\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
         echo "FOUND: ID: " . $row['loan_id'] . " | Client: " . $row['client_id'] . " | Number: " . $row['loan_number'] . "\n";
    }
} else {
    echo "NO MATCH FOUND.\n";
}
echo "------------------------------\n";
?>
