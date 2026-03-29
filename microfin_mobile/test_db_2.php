<?php
$conn = new mysqli("centerbeam.proxy.rlwy.net", "root", "zVULvPIbSyHVavTRnPFAkMWGVmvRwInd", "railway", 52624);
$res = $conn->query("SELECT * FROM tenants;");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
