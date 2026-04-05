<?php
$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$dbname = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "=== PERMISSIONS IN DATABASE ===\n";
$perms = $pdo->query("SELECT permission_id, permission_code FROM permissions ORDER BY permission_code")->fetchAll(PDO::FETCH_ASSOC);
foreach ($perms as $p) {
    echo "  {$p['permission_id']}: {$p['permission_code']}\n";
}

echo "\n=== DASHBOARD EXPECTS THESE ===\n";
$expected = ['VIEW_APPLICATIONS', 'MANAGE_APPLICATIONS', 'PROCESS_PAYMENTS', 'VIEW_USERS', 'VIEW_CLIENTS', 'CREATE_CLIENTS', 'VIEW_LOANS', 'CREATE_LOANS', 'APPROVE_LOANS', 'VIEW_REPORTS', 'VIEW_CREDIT_ACCOUNTS'];
foreach ($expected as $e) {
    $found = false;
    foreach ($perms as $p) {
        if ($p['permission_code'] === $e) { $found = true; break; }
    }
    echo "  " . ($found ? "✓" : "✗") . " $e\n";
}
