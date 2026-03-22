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
    
    $username = $data['username'] ?? '';
    // $email mapped to same param if user types email
    $password = $data['password'] ?? '';
    $tenant_id = $data['tenant_id'] ?? '';
    
    if(empty($username) || empty($password) || empty($tenant_id)) {
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

    $stmt = $conn->prepare("SELECT user_id, password_hash, status FROM users WHERE (username = ? OR email = ?) AND tenant_id = ?");
    $stmt->bind_param("sss", $username, $username, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if ($user['status'] !== 'Active') {
            echo json_encode(['success' => false, 'message' => 'Account is not active.']);
        } elseif (password_verify($password, $user['password_hash'])) {
            // Password matches
            echo json_encode(['success' => true, 'message' => 'Login successful!', 'user_id' => $user['user_id']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password for this tenant.']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
