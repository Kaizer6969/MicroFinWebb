<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$c = mysqli_connect('centerbeam.proxy.rlwy.net', 'root', 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd', 'railway', 52624);

echo "=== KYC DOCUMENT TYPES (is_required check) ===\n";
$r = mysqli_query($c, "SELECT document_type_id, document_name, is_required FROM document_types WHERE is_active = 1 AND (LOWER(document_name) LIKE '%proof%' OR LOWER(document_name) LIKE '%income%' OR LOWER(document_name) LIKE '%billing%' OR LOWER(document_name) LIKE '%legitimacy%')");

while ($row = mysqli_fetch_assoc($r)) {
    $req = $row['is_required'] ? '✓ REQUIRED' : 'optional';
    echo "  ID {$row['document_type_id']}: {$row['document_name']} ({$req})\n";
}

echo "\n=== ALL REQUIRED DOCUMENTS ===\n";
$r2 = mysqli_query($c, "SELECT document_type_id, document_name FROM document_types WHERE is_active = 1 AND is_required = 1");
while ($row = mysqli_fetch_assoc($r2)) {
    echo "  ID {$row['document_type_id']}: {$row['document_name']}\n";
}

mysqli_close($c);
