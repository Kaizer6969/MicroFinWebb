<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'db.php';

$res = $conn->query("SELECT document_type_id, document_name, description, is_required FROM document_types WHERE is_active = 1");
$doc_types = [];
while($row = $res->fetch_assoc()) {
    $doc_types[] = $row;
}

echo json_encode(['success' => true, 'document_types' => $doc_types]);
?>
