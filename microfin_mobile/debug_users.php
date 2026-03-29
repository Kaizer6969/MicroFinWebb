<?php
require 'api/db.php';
$res = $conn->query('SELECT user_id, tenant_id, username, first_name, last_name, user_type FROM users');
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['user_id']} | Tenant: {$row['tenant_id']} | User: {$row['username']} | Name: {$row['first_name']} {$row['last_name']} | Type: {$row['user_type']}\n";
}
?>
