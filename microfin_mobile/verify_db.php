<?php
require_once 'api/db.php';

echo "--- TENANTS ---\n";
$res = $conn->query("SELECT tenant_id, tenant_name, tenant_slug FROM tenants");
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['tenant_id'] . " | Name: " . $row['tenant_name'] . " | Slug: " . $row['tenant_slug'] . "\n";
}

echo "\n--- USER ROLES ---\n";
$res = $conn->query("SELECT role_id, tenant_id, role_name FROM user_roles");
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['role_id'] . " | Tenant: " . $row['tenant_id'] . " | Role: " . $row['role_name'] . "\n";
}
?>
