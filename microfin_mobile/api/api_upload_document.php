<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$tenant_id = $_POST['tenant_id'] ?? 'default_tenant';
// Sanitize tenant string
$tenant_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $tenant_id);

// Ensure uploads directory exists relative to API directory (e.g. at the root level)
$apiDir = __DIR__;
// We assume there's an `uploads` directory at the same level as `api`
$rootUploadDir = dirname($apiDir) . DIRECTORY_SEPARATOR . 'uploads';
$uploadDir = $rootUploadDir . DIRECTORY_SEPARATOR . $tenant_id;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$file = $_FILES['file'];
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('doc_', true) . '.' . $extension;
$targetFile = $uploadDir . DIRECTORY_SEPARATOR . $filename;

if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    // Return relative path intended for DB storage, e.g. "uploads/tenant_01/doc_xxxx.png"
    $dbPath = 'uploads/' . $tenant_id . '/' . $filename;
    echo json_encode([
        'success' => true,
        'file_name' => $file['name'],
        'file_path' => $dbPath,
        'message' => 'File uploaded successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
}
?>
