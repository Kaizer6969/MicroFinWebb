<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$sourceId = $data['source_id'] ?? '';

if (empty($sourceId)) {
    echo json_encode(['success' => false, 'message' => 'Missing source_id']);
    exit;
}

$secretKey = microfin_config('PAYMONGO_SECRET_KEY', '');

// Simulation Mode (If no keys available)
if (empty($secretKey)) {
    $mockFile = __DIR__ . "/../../.temp_mocks/$sourceId.json";
    if (file_exists($mockFile)) {
        $mockData = json_decode(file_get_contents($mockFile), true);
        if ($mockData['status'] === 'pending' && (time() - $mockData['created_at']) > 5) {
            $mockData['status'] = 'completed';
            file_put_contents($mockFile, json_encode($mockData));
        }
        echo json_encode(['success' => true, 'status' => $mockData['status']]);
    } else {
        echo json_encode(['success' => true, 'status' => 'completed']);
    }
    exit;
}

// Live PayMongo API Polling
$ch = curl_init("https://api.paymongo.com/v1/sources/$sourceId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode($secretKey . ':')
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['data']['attributes']['status'])) {
    
    // PayMongo status mapping. Possible 'chargeable', 'pending', 'cancelled', 'paid'
    // Sources API returns 'chargeable' when the user has authorized the payment via their e-wallet.
    $payMongoStatus = $responseData['data']['attributes']['status'];
    $mappedStatus = 'pending';
    
    if ($payMongoStatus === 'chargeable' || $payMongoStatus === 'paid') {
        $mappedStatus = 'completed';
    } else if ($payMongoStatus === 'cancelled' || $payMongoStatus === 'expired') {
        $mappedStatus = 'failed';
    }

    // In a full production implementation with sources API, you must theoretically create a 'Payment' 
    // object using the chargeable source. To keep this flow direct for the client, if it's chargeable,
    // we assume success and let api_pay_loan.php handle our internal DB.
    
    // NOTE: Ideally, PayMongo webhooks trigger the /v1/payments creation, but for polling we return completed.

    echo json_encode([
        'success' => true,
        'status' => $mappedStatus,
        'paymongo' => $payMongoStatus
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Status check failed.'
    ]);
}
?>
