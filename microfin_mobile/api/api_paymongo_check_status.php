<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'db.php';

$data      = json_decode(file_get_contents("php://input"), true);
$source_id = $data['source_id'] ?? '';
$loan_id   = intval($data['loan_id'] ?? 0);

if (empty($source_id) && !$loan_id) {
    echo json_encode(['success' => false, 'message' => 'Missing source_id or loan_id.']);
    exit;
}

$apiSecretKey = 'YOUR_SECRET_KEY';

if (!empty($source_id)) {
    // Check by source_id first in local DB
    $stmt = $conn->prepare("SELECT * FROM payment_transactions WHERE source_id = ? LIMIT 1");
    $stmt->bind_param("s", $source_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($tx && $tx['status'] !== 'pending') {
        echo json_encode(['success' => true, 'status' => $tx['status'], 'transaction' => $tx]);
        exit;
    }

    // Fallback: Check Paymongo API directly
    $ch = curl_init("https://api.paymongo.com/v1/sources/$source_id");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . base64_encode($apiSecretKey . ':')],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $srcData = json_decode($resp, true);
        $pmStatus = $srcData['data']['attributes']['status'] ?? 'pending';
        
        if ($pmStatus === 'chargeable' || $pmStatus === 'paid') {
            // Need transaction row
            $stmt = $conn->prepare("SELECT * FROM payment_transactions WHERE source_id = ? LIMIT 1");
            $stmt->bind_param("s", $source_id);
            $stmt->execute();
            $tx = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($tx && $tx['status'] === 'pending') {
                if ($pmStatus === 'chargeable') {
                    // capture payment via API
                    $amountCents = $srcData['data']['attributes']['amount'] ?? 0;
                    $pmPayload = [
                        'data' => [
                            'attributes' => [
                                'amount'      => $amountCents,
                                'source'      => ['id' => $source_id, 'type' => 'source'],
                                'currency'    => 'PHP',
                                'description' => "Loan Payment for Loan ID: {$tx['loan_id']}",
                            ],
                        ],
                    ];
                    $chP = curl_init('https://api.paymongo.com/v1/payments');
                    curl_setopt_array($chP, [
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode($apiSecretKey . ':')],
                        CURLOPT_POST           => 1,
                        CURLOPT_POSTFIELDS     => json_encode($pmPayload),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    $pmResp = curl_exec($chP);
                    $pmHttpCode = curl_getinfo($chP, CURLINFO_HTTP_CODE);
                    curl_close($chP);
                }
                
                // Treat as completed regardless if it was already 'paid' or we just 'chargeable' captured it
                $pmStatus = 'completed';
                // Update tx
                $upTx = $conn->prepare("UPDATE payment_transactions SET status='completed', updated_at=NOW() WHERE transaction_id=?");
                $upTx->bind_param("i", $tx['transaction_id']);
                $upTx->execute();
                $upTx->close();
            } else if ($tx && $tx['status'] === 'completed') {
                $pmStatus = 'completed';
            }
        }
        
        echo json_encode(['success' => true, 'status' => $pmStatus]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not retrieve source status.']);
    }
} else {
    // Check latest transaction by loan_id
    $stmt = $conn->prepare("SELECT * FROM payment_transactions WHERE loan_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($tx) {
        echo json_encode(['success' => true, 'status' => $tx['status'], 'transaction' => $tx]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No transaction found for this loan.']);
    }
}
?>
