<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$tenantId = trim((string) ($_POST['tenant_id'] ?? ''));
if ($tenantId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing tenant ID.']);
    exit;
}

$tenantStmt = $conn->prepare("
    SELECT tenant_id
    FROM tenants
    WHERE tenant_id = ?
      AND deleted_at IS NULL
    LIMIT 1
");

if (!$tenantStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare tenant lookup.']);
    exit;
}

$tenantStmt->bind_param('s', $tenantId);
$tenantStmt->execute();
$tenantExists = $tenantStmt->get_result()->num_rows === 1;
$tenantStmt->close();

if (!$tenantExists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Tenant not found.']);
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No file was uploaded.']);
    exit;
}

$uploadedFile = $_FILES['file'];
$errorCode = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errorCode !== UPLOAD_ERR_OK) {
    $message = 'File upload failed.';
    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        $message = 'Uploaded file is too large.';
    } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
        $message = 'No file was uploaded.';
    }

    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$originalName = basename((string) ($uploadedFile['name'] ?? 'upload.bin'));
$tmpName = (string) ($uploadedFile['tmp_name'] ?? '');
$sizeBytes = (int) ($uploadedFile['size'] ?? 0);
$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
$allowedMimeTypes = [
    'jpg' => ['image/jpeg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/pjpeg'],
    'png' => ['image/png'],
    'pdf' => ['application/pdf'],
];

if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Uploaded file source is invalid.']);
    exit;
}

if ($sizeBytes <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Uploaded file is empty.']);
    exit;
}

if ($sizeBytes > 10 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Uploaded file must be 10MB or smaller.']);
    exit;
}

if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed types: JPG, JPEG, PNG, PDF.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
if ($finfo) {
    finfo_close($finfo);
}

if ($mimeType !== '' && isset($allowedMimeTypes[$extension]) && !in_array($mimeType, $allowedMimeTypes[$extension], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Uploaded file content does not match the selected file type.']);
    exit;
}

$tenantKey = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenantId);
if (!is_string($tenantKey) || $tenantKey === '') {
    $tenantKey = 'tenant';
}

$uploadRelativeDir = 'microfin_mobile/uploads/client_documents/' . $tenantKey . '/' . date('Y') . '/' . date('m');
$uploadAbsoluteDir = dirname(__DIR__) . '/uploads/client_documents/' . $tenantKey . '/' . date('Y') . '/' . date('m');

if (!is_dir($uploadAbsoluteDir) && !mkdir($uploadAbsoluteDir, 0775, true) && !is_dir($uploadAbsoluteDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare the upload folder.']);
    exit;
}

$safeOriginalName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName);
$storedName = 'doc_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$destinationPath = rtrim($uploadAbsoluteDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;

if (!move_uploaded_file($tmpName, $destinationPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

$relativeFilePath = $uploadRelativeDir . '/' . $storedName;

echo json_encode([
    'success' => true,
    'message' => 'File uploaded successfully.',
    'file_name' => $safeOriginalName,
    'stored_name' => $storedName,
    'file_path' => $relativeFilePath,
    'file_size' => $sizeBytes,
    'file_type' => $mimeType !== '' ? $mimeType : mime_content_type($destinationPath),
]);
