<?php
header('Content-Type: application/json');
require_once 'session_auth.php';
mf_start_backend_session();
require_once 'db_connect.php';

mf_require_tenant_session($pdo, [
    'response' => 'json',
    'status' => 401,
    'message' => 'Unauthorized access.',
]);

if (($_SESSION['user_type'] ?? '') !== 'Employee') {
    echo json_encode(['status' => 'error', 'message' => 'Only staff members can perform this action.']);
    exit;
}

$tenant_id = (string) ($_SESSION['tenant_id'] ?? '');
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);

$content_type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$is_json_payload = strpos($content_type, 'application/json') !== false;

if ($is_json_payload) {
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true) ?: [];
} else {
    $data = $_POST;
}

$first_name = trim((string) ($data['first_name'] ?? ''));
$last_name = trim((string) ($data['last_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$phone = trim((string) ($data['phone_number'] ?? ''));
$dob = trim((string) ($data['date_of_birth'] ?? ''));
$address = trim((string) ($data['address'] ?? ''));

if ($first_name === '' || $last_name === '' || $email === '' || $dob === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required account fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
    exit;
}

function generateUniqueUsername(PDO $pdo, string $tenant_id, string $first_name, string $last_name): string {
    $base = strtolower(trim($first_name . '.' . $last_name));
    $base = preg_replace('/[^a-z0-9.]+/', '', $base);
    $base = trim($base, '.');
    if ($base === '') $base = 'client';

    for ($i = 0; $i < 20; $i++) {
        $candidate = $base . ($i > 0 ? random_int(100, 9999) : '');
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE tenant_id = ? AND username = ? LIMIT 1');
        $stmt->execute([$tenant_id, $candidate]);
        if (!$stmt->fetchColumn()) return $candidate;
    }
    return $base . random_int(10000, 99999);
}

try {
    $pdo->beginTransaction();

    $dup_stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND tenant_id = ? LIMIT 1');
    $dup_stmt->execute([$email, $tenant_id]);
    if ($dup_stmt->fetchColumn()) {
        throw new Exception('Email is already registered in this branch/company.');
    }

    $role_stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE role_name = 'Client' AND tenant_id = ? LIMIT 1");
    $role_stmt->execute([$tenant_id]);
    $role_id = (int) $role_stmt->fetchColumn();

    if ($role_id <= 0) {
        $insert_role = $pdo->prepare("INSERT INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES (?, 'Client', 'Client app access', 1)");
        $insert_role->execute([$tenant_id]);
        $role_id = (int) $pdo->lastInsertId();
    }

    $username = generateUniqueUsername($pdo, $tenant_id, $first_name, $last_name);
    // Dummy password since they will set it via email reset flow
    $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    
    // Generate reset token for email flow
    $reset_token = bin2hex(random_bytes(32));
    $reset_expiry = date('Y-m-d H:i:s', strtotime('+48 hours'));

    $user_insert = $pdo->prepare('
        INSERT INTO users (
            tenant_id, username, email, phone_number, password_hash, email_verified,
            first_name, last_name, date_of_birth,
            role_id, user_type, status, reset_token, reset_token_expiry
        ) VALUES (
            ?, ?, ?, ?, ?, 0,
            ?, ?, ?,
            ?, \'Client\', \'Active\', ?, ?
        )
    ');
    $user_insert->execute([
        $tenant_id, $username, $email, ($phone !== '' ? $phone : null), $dummy_password,
        $first_name, $last_name, $dob, $role_id, $reset_token, $reset_expiry
    ]);

    $new_user_id = (int) $pdo->lastInsertId();

    $employee_stmt = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $employee_stmt->execute([$session_user_id, $tenant_id]);
    $registered_by = $employee_stmt->fetchColumn() ?: null;

    $client_insert = $pdo->prepare('
        INSERT INTO clients (
            tenant_id, user_id, first_name, last_name,
            date_of_birth, contact_number, present_street, email_address,
            registration_date, registered_by, client_status, document_verification_status
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            CURDATE(), ?, \'Active\', \'Verified\'
        )
    ');
    $client_insert->execute([
        $tenant_id, $new_user_id, $first_name, $last_name, $dob,
        ($phone !== '' ? $phone : null), ($address !== '' ? $address : null), $email, $registered_by
    ]);
    
    $new_client_id = (int) $pdo->lastInsertId();

    // Handle Documents if any (simplified)
    $uploaded_count = 0;
    if (isset($_FILES['uploaded_documents']) && is_array($_FILES['uploaded_documents']['name'])) {
        $tenant_upload_key = preg_replace('/[^A-Za-z0-9_-]+/', '_', $tenant_id);
        $upload_dir = __DIR__ . '/../uploads/walk_in_documents/' . $tenant_upload_key . '/' . date('Y/m');
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0775, true);

        foreach ($_FILES['uploaded_documents']['name'] as $doc_type_key => $original_name) {
            $doc_type_id = (int) $doc_type_key;
            if ($doc_type_id <= 0 || $_FILES['uploaded_documents']['error'][$doc_type_key] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $tmp_name = $_FILES['uploaded_documents']['tmp_name'][$doc_type_key];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])) {
                $stored_name = 'client_' . $new_client_id . '_doc_' . $doc_type_id . '_' . time() . '.' . $ext;
                $dest_path = $upload_dir . '/' . $stored_name;
                
                if (move_uploaded_file($tmp_name, $dest_path)) {
                    $rel_path = 'uploads/walk_in_documents/' . $tenant_upload_key . '/' . date('Y/m') . '/' . $stored_name;
                    $doc_stmt = $pdo->prepare('INSERT INTO client_documents (client_id, tenant_id, document_type_id, file_name, file_path, verification_status) VALUES (?, ?, ?, ?, ?, \'Verified\')');
                    $doc_stmt->execute([$new_client_id, $tenant_id, $doc_type_id, $original_name, $rel_path]);
                    $uploaded_count++;
                }
            }
        }
    }

    $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, entity_id, description) VALUES (?, ?, 'WALK_IN_REGISTERED', 'client', ?, 'Walk-in client registered and marked as Active/Verified')");
    $audit_stmt->execute([$session_user_id > 0 ? $session_user_id : null, $tenant_id, $new_client_id]);

    $pdo->commit();

    $tenant_slug = (string) ($_SESSION['tenant_slug'] ?? $tenant_id);
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Construct the actual URL to the reset password page for this tenant
    $reset_url = $protocol . $host . "/tenant_login/reset_password.php?token=" . $reset_token . "&slug=" . urlencode($tenant_slug);
    
    $tenant_name = htmlspecialchars((string) ($_SESSION['tenant_name'] ?? 'Microfin Partner'));
    
    $subject = "Welcome to " . $tenant_name . " - Set up your password";
    $htmlContent = "
        <div style='font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; color: #333;'>
            <h2 style='color: #2563eb;'>Welcome to {$tenant_name}!</h2>
            <p>Hi " . htmlspecialchars($first_name) . ",</p>
            <p>An account has been created for you. To complete your setup, please click the button below to choose your password.</p>
            <p style='margin: 30px 0;'>
                <a href='{$reset_url}' style='background-color: #2563eb; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; font-size: 16px;'>Set My Password</a>
            </p>
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <p><a href='{$reset_url}' style='color: #2563eb; word-break: break-all;'>{$reset_url}</a></p>
            <p>This link will expire in 48 hours for security reasons.</p>
            <br>
            <p>Thank you,<br>The {$tenant_name} Team</p>
        </div>
    ";

    $email_result = '';
    if (function_exists('mf_send_brevo_email') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_result = mf_send_brevo_email($email, $subject, $htmlContent);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Client account created instantly (Active & Verified). An email has been sent to ' . htmlspecialchars($email) . ' for password setup.',
        'client_id' => $new_client_id,
        'uploaded_document_count' => $uploaded_count,
        'email_status' => $email_result
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
