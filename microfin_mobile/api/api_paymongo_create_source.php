<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$amount = (float)($data['amount'] ?? 0);
$method = $data['payment_method'] ?? 'GCash';

// 1. Generate a mock source ID
$sourceId = 'src_mock_' . time() . '_' . rand(1000, 9999);

// 2. We'll store the mock state in a simple file or temp directory for polling
$mockDir = __DIR__ . '/../../.temp_mocks';
if (!is_dir($mockDir)) { mkdir($mockDir, 0777, true); }
file_put_contents("$mockDir/$sourceId.json", json_encode([
    'status' => 'pending', // will change to completed after a delay
    'created_at' => time()
]));

// 3. Fake checkout URL - we just send them to a blank dummy PHP file that simulates a success page
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
// If 'web-production' is the host, it will use that automatically
$checkoutUrl = "$scheme://$host/admin-draft/microfin_mobile/api/api_paymongo_mock_portal.php?source=$sourceId&amount=$amount&method=$method";

echo json_encode([
    'success' => true,
    'source_id' => $sourceId,
    'checkout_url' => $checkoutUrl
]);
?>
