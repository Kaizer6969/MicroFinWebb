<?php
/**
 * api_verify_id_idnorm.php
 * Powered by Google Gemini Vision API (replaces dead IDnorm)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── CONFIG ──────────────────────────────────────────────────────────────────
// GOOGLE GEMINI API KEY - Replace this with your own fresh key if this one is leaked!
define('GEMINI_API_KEY', 'AIzaSyCFnCPcyRkSEIF_D_c8b5P45x4J0Y0fvoQ');
define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . GEMINI_API_KEY);

// Set to true to use mock data, false to use Real Gemini AI
define('USE_MOCK', false);

// ─── VALIDATION ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['front_image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'front_image is required']);
    exit;
}

$frontFile = $_FILES['front_image'];
$allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

$frontMime = mime_content_type($frontFile['tmp_name']);
if (!in_array($frontMime, $allowedMimes)) {
    echo json_encode(['success' => false, 'message' => "Invalid front image type. Use JPEG, PNG, or WebP."]);
    exit;
}

// ─── MOCK RESPONSE (for local testing) ──────────────────────────────────────
if (USE_MOCK) {
    usleep(1500000);
    echo json_encode([
        'success'         => true,
        'status'          => 'Approved',
        'is_approved'     => true,
        'document_type'   => 'National ID',
        'document_number' => 'IDN-' . rand(1000, 9999) . '-PH',
        'full_name'       => 'Juan Dela Cruz',
        'date_of_birth'   => '1990-01-01',
        'gender'          => 'M',
        'nationality'     => 'PHL'
    ]);
    exit;
}

// ─── GEMINI VISION REQUEST ──────────────────────────────────────────────────
$imageData = base64_encode(file_get_contents($frontFile['tmp_name']));

$prompt = "Extract the identity card details from this image. Return ONLY a valid JSON object without any markdown wrapping or formatting. Use the exact keys: 'first_name', 'last_name', 'document_number', 'date_of_birth' (YYYY-MM-DD), 'gender' (M/F), 'nationality', 'document_type', 'address_street', 'address_barangay', 'address_city', 'address_province', 'address_postal_code'. If a field is not found, leave it empty. Ensure it parses cleanly with json_decode in PHP.";

$payload = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => [
                    'mime_type' => $frontMime,
                    'data' => $imageData
                ]]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.1,
        'responseMimeType' => 'application/json'
    ]
]);

$ch = curl_init(GEMINI_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$rawResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'Network (cURL) error: ' . $curlError]);
    exit;
}

$geminiData = json_decode($rawResponse, true);

// Handle API Key leaks or Google API Errors
if ($httpCode !== 200) {
    $errMsg = $geminiData['error']['message'] ?? 'Gemini API Error (HTTP ' . $httpCode . ')';
    echo json_encode(['success' => false, 'message' => $errMsg, 'http_code' => $httpCode]);
    exit;
}

// ─── MAP GEMINI JSON RESPONSE ───────────────────────────────────────────────
$extractedText = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
$result = json_decode($extractedText, true);

if (!is_array($result)) {
    echo json_encode([
        'success' => false,
        'message' => 'Gemini failed to return valid JSON format.',
        'raw_response' => $extractedText
    ]);
    exit;
}

$firstName = $result['first_name'] ?? '';
$lastName  = $result['last_name'] ?? '';
$fullName  = trim($firstName . ' ' . $lastName);

// Fallback if full name isn't combined
if (empty($fullName) && !empty($result['full_name'])) {
    $fullName = $result['full_name'];
}

echo json_encode([
    'success'         => true,
    'status'          => 'Approved',
    'is_approved'     => true,
    'full_name'       => $fullName,
    'document_number' => $result['document_number'] ?? '',
    'date_of_birth'   => $result['date_of_birth'] ?? '',
    'document_type'   => $result['document_type'] ?? 'ID Card',
    'gender'          => $result['gender'] ?? '',
    'nationality'     => $result['nationality'] ?? '',
    'address_street'  => $result['address_street'] ?? '',
    'address_barangay'=> $result['address_barangay'] ?? '',
    'address_city'    => $result['address_city'] ?? '',
    'address_province'=> $result['address_province'] ?? '',
    'address_postal_code' => $result['address_postal_code'] ?? '',
    'raw_response'    => $result,
]);