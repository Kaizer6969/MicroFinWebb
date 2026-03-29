<?php
require_once 'api/db.php';
$res = $conn->query("SELECT tenant_id FROM tenants");
print_r($res->fetch_all(MYSQLI_ASSOC));
