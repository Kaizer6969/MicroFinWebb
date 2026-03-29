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
    $reset_code = $data['reset_code'] ?? '';
    $new_password = $data['new_password'] ?? '';
    
    if(empty($email) || empty($tenant_id) || empty($reset_code) || empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    // Verify Code
    $stmt_check = $conn->prepare("SELECT reset_code FROM password_resets WHERE email = ? AND tenant_id = ? AND reset_code = ? AND expires_at > NOW()");
    $stmt_check->bind_param("sss", $email, $tenant_id, $reset_code);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        exit;
    }
    $stmt_check->close();

    $password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
    
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ? AND tenant_id = ?");
    $stmt->bind_param("sss", $password_hash, $email, $tenant_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows === 1) {
            // Delete reset code after successful reset
            $stmt_del = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND tenant_id = ?");
            $stmt_del->bind_param("ss", $email, $tenant_id);
            $stmt_del->execute();
            $stmt_del->close();

            echo json_encode(['success' => true, 'message' => 'Password reset successful!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Password update failed. User not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>

