<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$amount = (float)($data['amount'] ?? 0);
$method = strtolower($data['payment_method'] ?? 'gcash'); 

// PayMongo expects 'gcash' or 'paymaya'
if ($method === 'paymaya') $method = 'paymaya';
else $method = 'gcash';

// Amount must be in centavos (multiply by 100)
$amountInCents = (int)round($amount * 100);

$secretKey = microfin_config('PAYMONGO_SECRET_KEY', '');

// If NO key is provided in Railway variables, fallback to simulation mode so app doesn't crash
if (empty($secretKey)) {
    $sourceId = 'src_mock_' . time() . '_' . rand(1000, 9999);
    $mockDir = __DIR__ . '/../../.temp_mocks';
    if (!is_dir($mockDir)) { mkdir($mockDir, 0777, true); }
    file_put_contents("$mockDir/$sourceId.json", json_encode(['status' => 'pending', 'created_at' => time()]));
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $checkoutUrl = "$scheme://$host/admin-draft/microfin_mobile/api/api_paymongo_mock_portal.php?source=$sourceId&amount=$amount&method=$method";

    echo json_encode([
        'success' => true,
        'source_id' => $sourceId,
        'checkout_url' => $checkoutUrl,
        'mode' => 'simulated'
    ]);
    exit;
}

// Proceed with real PayMongo API
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$successUrl = "$scheme://$host/admin-draft/microfin_mobile/api/api_paymongo_mock_portal.php?status=success&amount=$amount";
$failedUrl  = "$scheme://$host/admin-draft/microfin_mobile/api/api_paymongo_mock_portal.php?status=failed&amount=$amount";

$payload = [
    'data' => [
        'attributes' => [
            'amount' => $amountInCents,
            'redirect' => [
                'success' => $successUrl,
                'failed' => $failedUrl
            ],
            'type' => $method,
            'currency' => 'PHP'
        ]
    ]
];

$ch = curl_init('https://api.paymongo.com/v1/sources');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode($secretKey . ':')
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['data']['id'])) {
    $sourceId = $responseData['data']['id'];
    $checkoutUrl = $responseData['data']['attributes']['redirect']['checkout_url'];
    
    echo json_encode([
        'success' => true,
        'source_id' => $sourceId,
        'checkout_url' => $checkoutUrl,
        'mode' => 'live'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'PayMongo error: ' . ($responseData['errors'][0]['detail'] ?? 'Unknown error'),
        'raw' => $responseData
    ]);
}
?>
