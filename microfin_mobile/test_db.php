<?php
$conn = new mysqli("centerbeam.proxy.rlwy.net", "root", "zVULvPIbSyHVavTRnPFAkMWGVmvRwInd", "railway", 52624);
$res = $conn->query("SELECT tenant_id FROM tenants;");
while ($row = $res->fetch_assoc()) {
    echo "Tenant: " . $row['tenant_id'] . "\n";
}
?>
