<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'db.php';

$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($tenant_id)) {
    echo json_encode(['success' => false, 'message' => 'tenant_id is required']);
    exit;
}

$stmt = $conn->prepare("SELECT product_id as id, product_name as name, product_type as type, interest_rate as rate, min_amount as min, max_amount as max, min_term_months as min_term, max_term_months as max_term, description FROM loan_products WHERE tenant_id = ? AND is_active = 1");
$stmt->bind_param("s", $tenant_id);
$stmt->execute();
$res = $stmt->get_result();
$products = [];
while($row = $res->fetch_assoc()) {
    $row['rate'] = (float)$row['rate'];
    $row['min'] = (float)$row['min'];
    $row['max'] = (float)$row['max'];
    $row['min_term'] = (int)$row['min_term'];
    $row['max_term'] = (int)$row['max_term'];
    $products[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'products' => $products]);
?>
