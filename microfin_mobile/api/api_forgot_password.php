<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $email = $data['email'] ?? '';
    $tenant_id = $data['tenant_id'] ?? '';
    
    if(empty($email) || empty($tenant_id)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    $tenant_stmt = $conn->prepare("SELECT tenant_id FROM tenants WHERE tenant_id = ? AND deleted_at IS NULL LIMIT 1");
    $tenant_stmt->bind_param("s", $tenant_id);
    $tenant_stmt->execute();
    $tenant_exists = $tenant_stmt->get_result()->num_rows === 1;
    $tenant_stmt->close();

    if (!$tenant_exists) {
        echo json_encode(['success' => false, 'message' => 'Invalid tenant_id. Tenant does not exist.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND tenant_id = ?");
    $stmt->bind_param("ss", $email, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        // User found. In a real app, generate a token and send an email.
        // For this functional demo, we'll return success and allow them to proceed to reset.
        echo json_encode(['success' => true, 'message' => 'User found. You can now reset your password.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No account found with that email for this tenant.']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
