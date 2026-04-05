<?php
$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$dbname = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "=== DOCUMENT TYPES IN DATABASE ===\n";
$docs = $pdo->query("SELECT document_type_id, document_name, is_active FROM document_types ORDER BY document_type_id")->fetchAll(PDO::FETCH_ASSOC);

if (empty($docs)) {
    echo "NO DOCUMENT TYPES FOUND! Run add_missing_perms.php first.\n";
} else {
    foreach ($docs as $d) {
        $active = $d['is_active'] ? '✓' : '✗';
        echo "  {$d['document_type_id']}: {$d['document_name']} (active: {$active})\n";
    }
    echo "\nTotal: " . count($docs) . " document types\n";
}
