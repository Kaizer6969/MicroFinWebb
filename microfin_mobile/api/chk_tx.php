<?php
require 'db.php';
$r = $conn->query("SELECT * FROM payment_transactions ORDER BY transaction_id DESC LIMIT 1");
$tx = $r->fetch_assoc();
print_r($tx);

if ($tx && $tx['status'] === 'pending') {
    $source_id = $tx['source_id'];
    $apiSecretKey = 'YOUR_SECRET_KEY';
    $ch = curl_init("https://api.paymongo.com/v1/sources/$source_id");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . base64_encode($apiSecretKey . ':')],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $srcData = json_decode($resp, true);
    $pmStatus = $srcData['data']['attributes']['status'] ?? 'pending';
    echo "Source status: $pmStatus\n";

    if ($pmStatus === 'chargeable') {
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
        
        echo "Posting Payload: \n";
        print_r($pmPayload);
        
        $chP = curl_init('https://api.paymongo.com/v1/payments');
        curl_setopt_array($chP, [
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode($apiSecretKey . ':')],
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($pmPayload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $pmResp = curl_exec($chP);
        echo "Response: \n$pmResp\n";
    }
}
