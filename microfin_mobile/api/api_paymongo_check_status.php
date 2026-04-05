<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$sourceId = $data['source_id'] ?? '';

if (empty($sourceId)) {
    echo json_encode(['success' => false, 'message' => 'Missing source_id']);
    exit;
}

$mockFile = __DIR__ . "/../../.temp_mocks/$sourceId.json";

if (file_exists($mockFile)) {
    $mockData = json_decode(file_get_contents($mockFile), true);
    
    // Automatically transition to completed after 5 seconds to simulate user paying on the mock portal
    if ($mockData['status'] === 'pending' && (time() - $mockData['created_at']) > 5) {
        $mockData['status'] = 'completed';
        file_put_contents($mockFile, json_encode($mockData));
    }

    echo json_encode([
        'success' => true,
        'status' => $mockData['status']
    ]);
} else {
    // If the file doesn't exist, just simulate a fast checkout
    echo json_encode([
        'success' => true,
        'status' => 'completed'
    ]);
}
?>
