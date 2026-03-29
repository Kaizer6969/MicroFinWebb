<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

$user_id   = intval($data['user_id']   ?? 0);
$tenant_id = $data['tenant_id'] ?? '';
$loan_id   = intval($data['loan_id']   ?? 0);
$amount    = floatval($data['amount']  ?? 0);
$method    = $data['payment_method']   ?? 'GCash';

if (!$user_id || !$tenant_id || !$loan_id || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required payment data.']);
    exit;
}

// Paymongo source types
$sourceTypeMap = ['GCash' => 'gcash', 'PayMaya' => 'paymaya'];
$sourceType = $sourceTypeMap[$method] ?? 'gcash';

$apiSecretKey  = 'YOUR_SECRET_KEY';
$amountCents   = round($amount * 100);

$baseUrl    = 'http://127.0.0.1/Integ/config/Model/Activity3_5PageUp/microfin_mobile/api';
$successUrl = $baseUrl . '/paymongo_return.php?status=success&loan_id=' . $loan_id . '&amount=' . $amount . '&method=' . urlencode($method);
$failUrl    = $baseUrl . '/paymongo_return.php?status=failed&loan_id=' . $loan_id;

$payload = [
    'data' => [
        'attributes' => [
            'amount'   => $amountCents,
            'redirect' => ['success' => $successUrl, 'failed' => $failUrl],
            'type'     => $sourceType,
            'currency' => 'PHP',
        ],
    ],
];

$ch = curl_init('https://api.paymongo.com/v1/sources');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode($apiSecretKey . ':')],
    CURLOPT_POST           => 1,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    $result      = json_decode($response, true);
    $sourceId    = $result['data']['id'];
    $checkoutUrl = $result['data']['attributes']['redirect']['checkout_url'];

    try {
        // Ensure payment_transactions table exists
        $conn->query("
            CREATE TABLE IF NOT EXISTS payment_transactions (
                transaction_id INT AUTO_INCREMENT PRIMARY KEY,
                source_id      VARCHAR(100) NOT NULL,
                loan_id        INT NOT NULL,
                client_id      INT,
                user_id        INT,
                tenant_id      VARCHAR(50),
                amount         DECIMAL(15,2) NOT NULL,
                payment_method VARCHAR(50),
                status         VARCHAR(30) DEFAULT 'pending',
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Fetch client_id
        $cStmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ? LIMIT 1");
        $cStmt->bind_param("i", $user_id);
        $cStmt->execute();
        $cRow = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();
        $client_id = $cRow['client_id'] ?? 0;

        // Generate a unique transaction reference
        $transactionRef = 'TXN-' . strtoupper(uniqid()) . '-' . time();

        // Store pending transaction
        $ins = $conn->prepare("
            INSERT INTO payment_transactions 
                (transaction_ref, source_id, loan_id, client_id, user_id, tenant_id, amount, payment_method, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        if (!$ins) throw new Exception("Prepare failed: " . $conn->error);
        $ins->bind_param("ssiiisds", $transactionRef, $sourceId, $loan_id, $client_id, $user_id, $tenant_id, $amount, $method);
        if (!$ins->execute()) throw new Exception("Execute failed: " . $ins->error);
        $ins->close();

        echo json_encode([
            'success'      => true,
            'source_id'    => $sourceId,
            'checkout_url' => $checkoutUrl,
            'amount'       => $amount,
            'method'       => $method,
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    $errData = json_decode($response, true);
    $errMsg  = $errData['errors'][0]['detail'] ?? 'Failed to create payment source.';
    echo json_encode(['success' => false, 'message' => $errMsg]);
}
?>
