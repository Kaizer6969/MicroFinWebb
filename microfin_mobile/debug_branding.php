<?php
require 'api/db.php';
$res = $conn->query('SELECT tenant_id, logo_path FROM tenant_branding');
while($row = $res->fetch_assoc()) {
    echo "Tenant: {$row['tenant_id']} | Logo Path: {$row['logo_path']}\n";
}
?>
