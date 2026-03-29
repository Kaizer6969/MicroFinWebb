<?php
/**
 * Registration Page - Fundline Web Application
 * Handles new user registration with Email OTP Verification
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Start session
session_start();

// Include database connection
require_once '../config/db.php';

// Include PHPMailer
require_once '../phpmailer/phpmailer/src/Exception.php';
require_once '../phpmailer/phpmailer/src/PHPMailer.php';
require_once '../phpmailer/phpmailer/src/SMTP.php';

// Initialize variables
$error_message = '';
$success_message = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
    exit();
}

/**
 * Validate Strong Password
 */
function validateStrongPassword($password) {
    $errors = [];
    if (strlen($password) < 12) {
        $errors[] = "Password must be at least 12 characters long";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    return $errors;
}

/**
 * Send OTP Email
 */
function sendOtpEmail($email, $username, $otp) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'onecallnoreplynotification@gmail.com'; 
        $mail->Password   = 'gjjjnkpcbqsqqofc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('no-reply@fundline.com', 'Fundline Microfinancing');
        $mail->addAddress($email, $username);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Account - Fundline';
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                    .email-container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                    .header { background-color: #EF4444; padding: 30px 20px; text-align: center; }
                    .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 1px; }
                    .content { padding: 40px 30px; color: #374151; }
                    .greeting { font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #111827; }
                    .message { margin-bottom: 30px; line-height: 1.6; font-size: 16px; }
                    .otp-box { background-color: #fce7f3; color: #be185d; border: 2px dashed #be185d; padding: 15px; font-size: 28px; font-weight: bold; letter-spacing: 5px; text-align: center; margin: 20px 0; border-radius: 8px; }
                    .footer { background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9CA3AF; border-top: 1px solid #e5e7eb; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='header'>
                        <h1>FUNDLINE</h1>
                    </div>
                    <div class='content'>
                        <div class='greeting'>Hello {$username},</div>
                        <div class='message'>
                            Thank you for registering with Fundline Microfinancing. To complete your account creation, please use the verification code below:
                        </div>
                        <div class='otp-box'>
                            {$otp}
                        </div>
                        <div class='message'>
                            This code is valid for 10 minutes. Do not share this code with anyone.
                        </div>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " Fundline Micro Financing Services | Marilao Branch
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Hello {$username},\n\nYour verification code is: {$otp}\n\nThis code is valid for 10 minutes.";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        $errorMsg = $mail->ErrorInfo;
        error_log("Mailer Error: " . $errorMsg);
        
        // DEV MODE FALLBACK
        $log_message = "[" . date('Y-m-d H:i:s') . "] To: $email | OTP: $otp | Error: $errorMsg" . PHP_EOL;
        if (!is_dir('../uploads')) mkdir('../uploads', 0777, true);
        file_put_contents('../uploads/otp_debug.txt', $log_message, FILE_APPEND);
        
        return ['success' => false, 'error' => $errorMsg];
    }
}


// Handle Request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'send_otp'; 
    
    header('Content-Type: application/json');

    // -------------------------------------------------------------------------
    // ACTION: SEND OTP (Step 1)
    // -------------------------------------------------------------------------
    if ($action === 'send_otp') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name']);
        $suffix = trim($_POST['suffix'] ?? '');
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Grab tenant parameter or default to fundline
        $tenant_slug = isset($_POST['tenant']) ? trim($_POST['tenant']) : (isset($_GET['tenant']) ? trim($_GET['tenant']) : 'fundline');

        // Backend Validation
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
            echo json_encode(['success' => false, 'message' => "All fields (except middle name & suffix) are required."]);
            exit();
        }
        if ($password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => "Passwords do not match."]);
            exit();
        }
        
        $t_stmt = $conn->prepare("SELECT tenant_id FROM tenants WHERE tenant_slug = ?");
        $t_stmt->bind_param("s", $tenant_slug);
        $t_stmt->execute();
        $t_res = $t_stmt->get_result();
        $tenant_data = $t_res->fetch_assoc();
        $check_tenant_id = $tenant_data ? $tenant_data['tenant_id'] : 1;
        $t_stmt->close();
        
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND tenant_id = ?");
        $stmt->bind_param("ssi", $username, $email, $check_tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => "Username or Email already exists."]);
            $stmt->close();
            exit();
        }
        $stmt->close();

        // Password Strength Check
        $pw_errors = validateStrongPassword($password);
        if (!empty($pw_errors)) {
             echo json_encode(['success' => false, 'message' => implode(" ", $pw_errors)]);
             exit();
        }

        // Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store in Session (Temporary)
        $_SESSION['registration_temp'] = [
            'username' => $username,
            'email' => $email,
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'last_name' => $last_name,
            'suffix' => $suffix,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'otp' => $otp,
            'otp_expiry' => time() + 600, // 10 minutes
            'tenant_slug' => $tenant_slug
        ];

        // Send Email
        $emailResult = sendOtpEmail($email, $username, $otp);
        if ($emailResult['success']) {
            echo json_encode(['success' => true, 'message' => "OTP sent to your email.", 'step' => 'verify_otp']);
        } else {
            // Dev Fallback response
            $debugMsg = " (SMTP Error: " . $emailResult['error'] . "). DEV MODE: OTP saved to uploads/otp_debug.txt";
            echo json_encode([
                'success' => true, 
                'message' => "Warning: Email failed" . $debugMsg, 
                'step' => 'verify_otp'
            ]);
        }
        exit();
    }

    // -------------------------------------------------------------------------
    // ACTION: VERIFY OTP (Step 2)
    // -------------------------------------------------------------------------
    elseif ($action === 'verify_otp') {
        $user_otp = trim($_POST['otp']);
        
        if (!isset($_SESSION['registration_temp'])) {
             echo json_encode(['success' => false, 'message' => "Session expired. Please register again."]);
             exit();
        }

        $temp_data = $_SESSION['registration_temp'];
        
        // Check Expiry
        if (time() > $temp_data['otp_expiry']) {
             echo json_encode(['success' => false, 'message' => "OTP has expired. Please try again."]);
             exit();
        }

        // Check OTP Match
        if ($user_otp !== $temp_data['otp']) {
             echo json_encode(['success' => false, 'message' => "Invalid OTP. Please check your email or debug file."]);
             exit();
        }

        // Proceed to Create Account
        $conn->begin_transaction();
        try {
            // Fetch Tenant ID
            $tenant_slug = $temp_data['tenant_slug'] ?? 'fundline';
            $t_stmt = $conn->prepare("SELECT tenant_id FROM tenants WHERE tenant_slug = ?");
            $t_stmt->bind_param("s", $tenant_slug);
            $t_stmt->execute();
            $t_res = $t_stmt->get_result();
            $tenant_data = $t_res->fetch_assoc();
            $tenant_id = $tenant_data ? $tenant_data['tenant_id'] : 1;
            $t_stmt->close();
        
            // Insert User
            $verification_token = bin2hex(random_bytes(32));
            $default_role_id = 3; // User
            $user_type = 'Client';

            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role_id, user_type, status, email_verified, verification_token, tenant_id) VALUES (?, ?, ?, ?, ?, 'Active', 1, ?, ?)");
            $stmt->bind_param("sssissi", $temp_data['username'], $temp_data['email'], $temp_data['password_hash'], $default_role_id, $user_type, $verification_token, $tenant_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create user account: " . $stmt->error);
            }
            $user_id = $conn->insert_id;
            $stmt->close();

            // Insert Client Profile
            $client_code = 'CLT' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("INSERT INTO clients (user_id, client_code, first_name, middle_name, last_name, suffix, date_of_birth, contact_number, email_address, client_status, registration_date, tenant_id) VALUES (?, ?, ?, ?, ?, ?, '1990-01-01', '', ?, 'Active', CURDATE(), ?)");
            $stmt->bind_param("issssssi", $user_id, $client_code, $temp_data['first_name'], $temp_data['middle_name'], $temp_data['last_name'], $temp_data['suffix'], $temp_data['email'], $tenant_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create client profile." . $stmt->error);
            }
            $stmt->close();

            // Log Audit
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, description, ip_address, tenant_id) VALUES (?, 'REGISTRATION', 'New user registered via OTP', ?, ?)");
            $stmt->bind_param("isi", $user_id, $ip_address, $tenant_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            
            // Clear Session
            unset($_SESSION['registration_temp']);

            echo json_encode(['success' => true, 'message' => "Account verified and created! Redirecting...", 'redirect' => "login.php?tenant=" . urlencode($tenant_slug)]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
        }
        exit();
    }
    
    // -------------------------------------------------------------------------
    // ACTION: RESEND OTP CODE (New)
    // -------------------------------------------------------------------------
    elseif ($action === 'resend_otp_code') {
        if (!isset($_SESSION['registration_temp'])) {
             echo json_encode(['success' => false, 'message' => "Session expired. Please register again."]);
             exit();
        }
        
        $temp_data = $_SESSION['registration_temp'];
        
        // Generate NEW OTP
        $new_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Update Session
        $_SESSION['registration_temp']['otp'] = $new_otp;
        $_SESSION['registration_temp']['otp_expiry'] = time() + 600; // Extend expiry
        
        // Send Email
        $emailResult = sendOtpEmail($temp_data['email'], $temp_data['username'], $new_otp);
        
        if ($emailResult['success']) {
            echo json_encode(['success' => true, 'message' => "New OTP sent."]);
        } else {
             $debugMsg = " (SMTP Error: " . $emailResult['error'] . "). DEV MODE: OTP saved to uploads/otp_debug.txt";
             echo json_encode([
                'success' => true, 
                'message' => "Warning: Email failed" . $debugMsg
            ]);
        }
        exit();
    }
}
?>
