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
    
    if(empty($email) || empty($tenant_id) || empty($reset_code)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    $stmt_check = $conn->prepare("SELECT reset_code FROM password_resets WHERE email = ? AND tenant_id = ? AND reset_code = ? AND expires_at > NOW()");
    $stmt_check->bind_param("sss", $email, $tenant_id, $reset_code);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        exit;
    }
    $stmt_check->close();

    echo json_encode(['success' => true, 'message' => 'Verification code is valid.']);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
