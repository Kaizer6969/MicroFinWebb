<?php
require_once 'api/db.php';
$res = $conn->query("SELECT tenant_id, tenant_name, tenant_slug FROM tenants");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
?>
