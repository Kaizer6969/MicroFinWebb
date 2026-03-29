<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'db.php';

// PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $email = $data['email'] ?? '';
    $tenant_id = $data['tenant_id'] ?? '';
    
    if(empty($email) || empty($tenant_id)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND tenant_id = ?");
    $stmt->bind_param("ss", $email, $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        // Generate a 6-digit verification code
        $reset_code = sprintf("%06d", mt_rand(100000, 999999));

        // Save or update reset code in database
        $stmt_reset = $conn->prepare("INSERT INTO password_resets (email, tenant_id, reset_code, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE reset_code = VALUES(reset_code), expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)");
        $stmt_reset->bind_param("sss", $email, $tenant_id, $reset_code);
        $stmt_reset->execute();
        $stmt_reset->close();

        // Send Email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jrbtruckingservices.2014@gmail.com';
            $mail->Password   = 'orfx wkgt vuae yfds';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('jrbtruckingservices.2014@gmail.com', 'MicroFin Security');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Verification Code';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Password Reset Request</h2>
                    <p>Hello,</p>
                    <p>You requested to reset your password for your MicroFin account. Please use the following code to verify your identity:</p>
                    <div style='background: #f4f4F4; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #DC2626; border-radius: 8px;'>
                        $reset_code
                    </div>
                    <p>This code will expire in 30 minutes.</p>
                    <p>If you did not request this, please ignore this email.</p>
                    <hr>
                    <p style='font-size: 12px; color: #777;'>MicroFin | Powered by Fundline</p>
                </div>";

            if ($mail->send()) {
                echo json_encode(['success' => true, 'message' => 'Verification code sent to your email.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send email.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'No account found with that email for this tenant.']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>

