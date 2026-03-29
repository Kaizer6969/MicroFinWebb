<?php
require 'api/db.php';
$res = $conn->query('SELECT user_id, tenant_id, username, first_name, last_name FROM users');
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['user_id']} | T: {$row['tenant_id']} | U: {$row['username']} | F: '{$row['first_name']}' | L: '{$row['last_name']}'\n";
}
?>
