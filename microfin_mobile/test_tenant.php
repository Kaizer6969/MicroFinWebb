<?php
require_once 'api/db.php';
$res = $conn->query("SHOW CREATE TABLE users");
print_r($res->fetch_all(MYSQLI_ASSOC));
$res = $conn->query("SHOW CREATE TABLE clients");
print_r($res->fetch_all(MYSQLI_ASSOC));
