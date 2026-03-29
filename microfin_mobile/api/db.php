<?php
// db.php
$servername = "centerbeam.proxy.rlwy.net";
$username = "root";
$password = "zVULvPIbSyHVavTRnPFAkMWGVmvRwInd";
$dbname = "railway";
$port = 52624;

try {
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
}
catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Database connection error: " . $e->getMessage()]);
    exit;
}
?>
