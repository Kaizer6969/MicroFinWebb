<?php
$dbConfig = [
    'host' => 'centerbeam.proxy.rlwy.net', 
    'port' => 52624, 
    'user' => 'root', 
    'pass' => 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd', 
    'db' => 'railway'
];
$conn = new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['db'], $dbConfig['port']);
if ($conn->connect_error) die('Conn failed: ' . $conn->connect_error);

$_GET['user_id'] = 14;
$_GET['tenant_id'] = 'B90FVN9PT5';
$_SERVER['REQUEST_METHOD'] = 'GET';
include('microfin_mobile/api/api_get_dashboard.php');
echo "DASHBOARD RESPONSE:\n";
print_r(json_decode($res, true));

