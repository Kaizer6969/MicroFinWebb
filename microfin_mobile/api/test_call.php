<?php
$apiKey = 'AIzaSyCQjvl_I_Qc70oohXXId3EQ70a-nNauA8k';
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" . $apiKey;

// 1x1 pixel base64 image
$b64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

$payload = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => "Return a valid JSON with keys: first_name, last_name, document_number, dob. If not found, output 'Not Found'."],
                ['inline_data' => [
                    'mime_type' => 'image/png',
                    'data' => $b64
                ]]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.1,
        'responseMimeType' => 'application/json'
    ]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $httpCode\n";
echo "Response: $response\n";
