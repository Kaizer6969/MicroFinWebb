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
    $new_password = $data['new_password'] ?? '';
    
    if(empty($email) || empty($tenant_id) || empty($new_password)) {
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

    $password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
    
    $stmt = $conn->prepare("
        UPDATE users u
        INNER JOIN clients c
            ON c.user_id = u.user_id
           AND c.tenant_id = u.tenant_id
        SET u.password_hash = ?
        WHERE u.email = ?
          AND u.tenant_id = ?
          AND u.user_type = 'Client'
          AND c.client_status = 'Active'
    ");
    $stmt->bind_param("sss", $password_hash, $email, $tenant_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows === 1) {
            echo json_encode(['success' => true, 'message' => 'Password reset successful!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Password update failed. Make sure you are registered.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
