<?php
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

$hasFrontImage = isset($_FILES['front_image']) || isset($_FILES['file']);
if (!$hasFrontImage) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No ID image was provided.']);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Automatic ID scanning is not configured yet. Please complete the details manually after upload.',
    'requires_manual_entry' => true,
]);
