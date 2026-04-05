<?php
/**
 * Fix missing document_type_id 21 (Scanned ID)
 * 
 * The mobile app hardcodes document_type_id 21 for scanned IDs.
 * After database truncation, this ID no longer exists, causing verification to fail.
 */

$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$dbname = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "=== FIXING DOCUMENT TYPE ID 21 ===\n\n";

// Check if ID 21 already exists
$check = $pdo->query("SELECT document_type_id, document_name FROM document_types WHERE document_type_id = 21");
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo "✓ Document type ID 21 already exists: '{$existing['document_name']}'\n";
} else {
    // Insert with specific ID 21
    $pdo->exec("INSERT INTO document_types (document_type_id, document_name, description, is_required, is_active) 
                VALUES (21, 'Scanned ID', 'Scanned government-issued ID from mobile app', 1, 1)");
    echo "✓ Added document type ID 21: 'Scanned ID'\n";
}

// Also add Selfie with ID (ID 22) which might be needed
$check2 = $pdo->query("SELECT document_type_id FROM document_types WHERE document_type_id = 22");
if (!$check2->fetch()) {
    $pdo->exec("INSERT INTO document_types (document_type_id, document_name, description, is_required, is_active) 
                VALUES (22, 'Selfie with ID', 'Selfie holding the ID document', 0, 1)");
    echo "✓ Added document type ID 22: 'Selfie with ID'\n";
}

echo "\n=== CURRENT DOCUMENT TYPES (relevant to mobile app) ===\n";
$result = $pdo->query("SELECT document_type_id, document_name, is_required FROM document_types 
                       WHERE document_type_id IN (21, 22) OR LOWER(document_name) LIKE '%proof%' 
                       ORDER BY document_type_id");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $req = $row['is_required'] ? 'required' : 'optional';
    echo "  ID {$row['document_type_id']}: {$row['document_name']} ($req)\n";
}

echo "\n=== DONE ===\n";
echo "Mobile app verification should now work!\n";
