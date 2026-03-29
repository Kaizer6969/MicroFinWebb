<?php
require 'api/db.php';
$res = $conn->query('SELECT user_id, username, first_name, last_name FROM users');
while($row = $res->fetch_assoc()) {
    var_dump($row);
}
?>
