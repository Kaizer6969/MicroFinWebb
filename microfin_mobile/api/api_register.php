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
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $first_name = $data['first_name'] ?? '';
    $middle_name = $data['middle_name'] ?? '';
    $last_name = $data['last_name'] ?? '';
    $suffix = $data['suffix'] ?? '';
    $tenant_id = trim((string)($data['tenant_id'] ?? ''));
    
    if(empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($tenant_id)) {
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
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE tenant_id = ? AND (username = ? OR email = ?)");
    $stmt->bind_param("sss", $tenant_id, $username, $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or Email already exists for this tenant.']);
        exit;
    }
    $stmt->close();

    $conn->begin_transaction();
    try {
        $password_hash = password_hash($password, PASSWORD_ARGON2ID);
        $role_stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client' AND tenant_id = ? LIMIT 1");
        $role_stmt->bind_param("s", $tenant_id);
        $role_stmt->execute();
        $role_res = $role_stmt->get_result();
        
        if ($role_res->num_rows === 0) {
            // Auto-create the Client role for this tenant if it doesn't exist
            $insert_role_stmt = $conn->prepare("INSERT INTO user_roles (tenant_id, role_name, role_description) VALUES (?, 'Client', 'Default Client Role')");
            $insert_role_stmt->bind_param("s", $tenant_id);
            if (!$insert_role_stmt->execute()) {
                throw new Exception("Failed to auto-create Client role: " . $insert_role_stmt->error);
            }
            $role_id = $insert_role_stmt->insert_id;
            $insert_role_stmt->close();
        } else {
            $role_id = (int)$role_res->fetch_assoc()['role_id'];
        }
        $role_stmt->close();

        $user_type = 'Client'; 
        $verification_token = bin2hex(random_bytes(32));

        $stmt = $conn->prepare("INSERT INTO users (tenant_id, username, email, password_hash, role_id, user_type, status, email_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, 'Active', 1, ?)");
        $stmt->bind_param("ssssiss", $tenant_id, $username, $email, $password_hash, $role_id, $user_type, $verification_token);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert user: " . $stmt->error);
        }
        $user_id = $conn->insert_id;
        $stmt->close();

        $client_code = 'CLT' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
        
        $stmt = $conn->prepare("INSERT INTO clients (user_id, tenant_id, client_code, first_name, middle_name, last_name, suffix, date_of_birth, contact_number, email_address, client_status, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, '1990-01-01', '', ?, 'Active', CURDATE())");
        $stmt->bind_param("isssssss", $user_id, $tenant_id, $client_code, $first_name, $middle_name, $last_name, $suffix, $email);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert client: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Registration successful!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
