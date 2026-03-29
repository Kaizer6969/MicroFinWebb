<?php
require 'api/db.php';
$res = $conn->query('SELECT client_id, user_id, tenant_id, first_name, last_name FROM clients');
while($row = $res->fetch_assoc()) {
    echo "Client ID: {$row['client_id']} | User ID: {$row['user_id']} | Name: {$row['first_name']} {$row['last_name']}\n";
}
?>
