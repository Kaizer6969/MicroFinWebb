<?php
/**
 * api_verify_id_didit.php
 * Proxies ID photo to Didit's standalone ID Verification API.
 * API Key is kept server-side — never exposed to the Flutter client.
 *
 * POST multipart/form-data:
 *   front_image  (file)   - required: front of the ID
 *   back_image   (file)   - optional: back of the ID (if dual-sided)
 *   vendor_data  (string) - optional: user_id for Didit tracking
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
define('DIDIT_API_KEY', 'XsIVLkIOTGTiiI0HW2eYIdmfVBgsPNI8lDRF36_4LBc');
define('DIDIT_ENDPOINT', 'https://verification.didit.me/v3/id-verification/');

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

$frontFile  = $_FILES['front_image'];
$backFile   = isset($_FILES['back_image']) ? $_FILES['back_image'] : null;
$vendorData = isset($_POST['vendor_data']) ? trim($_POST['vendor_data']) : 'unknown';

// Allowed MIME types
$allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

// Validate front image
$frontMime = mime_content_type($frontFile['tmp_name']);
if (!in_array($frontMime, $allowedMimes)) {
    file_put_contents(__DIR__ . '/didit_log.txt', date('[Y-m-d H:i:s] ') . "Validation Failed: Invalid mime $frontMime\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => "Invalid front image type ($frontMime). Use JPEG, PNG, or WebP."]);
    exit;
}

// File size check (5 MB limit by Didit; recommend < 1 MB)
if ($frontFile['size'] > 5 * 1024 * 1024) {
    file_put_contents(__DIR__ . '/didit_log.txt', date('[Y-m-d H:i:s] ') . "Validation Failed: File too large " . $frontFile['size'] . " bytes\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Front image too large (max 5 MB)']);
    exit;
}

// ─── BUILD CURL MULTIPART REQUEST ────────────────────────────────────────────
$postFields = [
    'front_image'                        => new CURLFile($frontFile['tmp_name'], $frontMime, $frontFile['name']),
    'perform_document_liveness'          => 'false',
    'expiration_date_not_detected_action'=> 'NO_ACTION',
    'invalid_mrz_action'                 => 'NO_ACTION',
    'inconsistent_data_action'           => 'NO_ACTION',
    'preferred_characters'               => 'latin',
    'save_api_request'                   => 'true',
    'vendor_data'                        => $vendorData,
];

// Add back image if provided
if ($backFile && $backFile['error'] === UPLOAD_ERR_OK) {
    $backMime = mime_content_type($backFile['tmp_name']);
    if (in_array($backMime, $allowedMimes) && $backFile['size'] <= 5 * 1024 * 1024) {
        $postFields['back_image'] = new CURLFile($backFile['tmp_name'], $backMime, $backFile['name']);
    }
}

// ─── CALL DIDIT ──────────────────────────────────────────────────────────────
$ch = curl_init(DIDIT_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_HTTPHEADER     => [
        'x-api-key: ' . DIDIT_API_KEY,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$rawResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_error($ch);
curl_close($ch);

// DEBUG LOGGING
file_put_contents(__DIR__ . '/didit_log.txt', date('[Y-m-d H:i:s] ') . "HTTP $httpCode\nError: $curlError\nRaw Response: $rawResponse\n\n", FILE_APPEND);

// ─── HANDLE ERRORS ───────────────────────────────────────────────────────────
if ($curlError) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Network error: ' . $curlError]);
    exit;
}

$diditData = json_decode($rawResponse, true);

if (!is_array($diditData)) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Invalid response from verification service.']);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    $errMsg = $diditData['error'] ?? $diditData['detail'] ?? $diditData['message'] ?? 'Verification service error (HTTP ' . $httpCode . ')';
    echo json_encode(['success' => false, 'message' => $errMsg, 'http_code' => $httpCode]);
    exit;
}

// ─── EXTRACT USEFUL FIELDS ───────────────────────────────────────────────────
$idVer = $diditData['id_verification'] ?? [];

$status        = $idVer['status']          ?? 'Unknown';   // "Approved" | "Declined" | "In Review"
$docNumber     = $idVer['document_number'] ?? '';
$docType       = $idVer['document_type']   ?? '';
$firstName     = $idVer['first_name']      ?? '';
$lastName      = $idVer['last_name']       ?? '';
$fullName      = $idVer['full_name']       ?? trim("$firstName $lastName");
$dob           = $idVer['date_of_birth']   ?? '';           // YYYY-MM-DD
$expiryDate    = $idVer['expiration_date'] ?? '';           // YYYY-MM-DD
$issueDate     = $idVer['date_of_issue']   ?? '';           // YYYY-MM-DD
$gender        = $idVer['gender']          ?? '';           // M / F
$nationality   = $idVer['nationality']     ?? '';
$issuingState  = $idVer['issuing_state']   ?? '';
$warnings      = $idVer['warnings']        ?? [];
$requestId     = $diditData['request_id']  ?? '';

// Build human-readable warning list
$warningMessages = array_map(fn($w) => $w['short_description'] ?? '', $warnings);
$warningMessages = array_filter($warningMessages);

// ─── RESPONSE ────────────────────────────────────────────────────────────────
echo json_encode([
    'success'         => true,
    'request_id'      => $requestId,
    'status'          => $status,            // Approved / Declined / In Review
    'is_approved'     => ($status === 'Approved'),
    'document_type'   => $docType,
    'document_number' => $docNumber,
    'full_name'       => $fullName,
    'first_name'      => $firstName,
    'last_name'       => $lastName,
    'date_of_birth'   => $dob,
    'expiry_date'     => $expiryDate,
    'issue_date'      => $issueDate,
    'gender'          => $gender,
    'nationality'     => $nationality,
    'issuing_state'   => $issuingState,
    'warnings'        => array_values($warningMessages),
    'raw_id_verification' => $idVer,         // full object for debugging
]);
