<?php
session_start();
require_once '../../backend/db_connect.php';

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid Request'];

if ($action === 'send_otp') {
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $response['message'] = 'Invalid email address.';
    } else {
         // Generate 6-digit OTP
         $otp = sprintf("%06d", mt_rand(1, 999999));

         try {
             // Check if email already exists under admin/super-admin accounts in users
             $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND deleted_at IS NULL");
             $check_stmt->execute([$email]);
             $duplicate_count = $check_stmt->fetchColumn();

             if ($duplicate_count > 0) {
                 $response['message'] = 'A demo request with this email already exists. Our team will contact you shortly.';
             } else {
                 // Invalidate older OTPs for this email first
                 $stmt = $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND status = 'Pending'");
                 $stmt->execute([$email]);

                 // Insert new OTP (using MySQL's NOW() to prevent PHP/DB timezone drift)
                 $stmt = $pdo->prepare("INSERT INTO otp_verifications (email, otp_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
                 if ($stmt->execute([$email, $otp])) {
                     // Build OTP email HTML
                            $subject = 'MicroFin - Your OTP Code';
                     $message = "
                     <html>
                     <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                                <h2>MicroFin OTP</h2>
                                <p>This is your OTP:</p>
                        <h1 style='color: #10b981; letter-spacing: 5px;'>{$otp}</h1>
                        <p>This code will expire in 5 minutes.</p>
                     </body>
                     </html>
                     ";

                     // Send using Brevo API wrapper
                     $emailSent = mf_send_brevo_email($email, $subject, $message);

                     if ($emailSent === 'Email sent successfully.') {
                         $response['message'] = 'OTP sent to your email!';
                         $response['success'] = true;
                         $response['delivery_mode'] = 'brevo';
                     } else {
                         // All methods failed - expire the OTP and return error
                         $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND otp_code = ?")->execute([$email, $otp]);
                         $response['message'] = 'Unable to send verification email. Please try again later.';
                     }
             } else {
                 $response['message'] = 'Database error generating OTP.';
             }
         } // End duplicate check
         } catch (\PDOException $e) {
             $response['message'] = 'System error: ' . $e->getMessage();
         }
    }
} 
elseif ($action === 'verify_otp') {
    $email = trim($_POST['email'] ?? '');
    $otp_code = trim($_POST['otp_code'] ?? '');

    try {
        // Find a matching, non-expired OTP using MySQL time context
        $stmt = $pdo->prepare("SELECT otp_id, (expires_at < NOW()) as is_expired FROM otp_verifications WHERE email = ? AND otp_code = ? AND status = 'Pending' ORDER BY otp_id DESC LIMIT 1");
        $stmt->execute([$email, $otp_code]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
             // Check if 5-minutes have passed via MySQL evaluation
             if ($record['is_expired']) {
                 
                 // Manually force expiry update
                 $upd = $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE otp_id = ?");
                 $upd->execute([$record['otp_id']]);

                 $response['message'] = 'OTP has expired. Please request a new one.';
             } else {
                 // Valid! Mark as verified
                 $upd = $pdo->prepare("UPDATE otp_verifications SET status = 'Verified' WHERE otp_id = ?");
                 $upd->execute([$record['otp_id']]);

                 // Set session flag allowing final submission
                 $_SESSION['verified_contact_email'] = $email;

                 $response['success'] = true;
                 $response['message'] = 'Email successfully verified!';
             }
        } else {
             $response['message'] = 'Invalid OTP or originally requested email.';
        }
    } catch (\PDOException $e) {
        $response['message'] = 'System error: ' . $e->getMessage();
    }
}
elseif ($action === 'expire_otp') {
    // Called by frontend when countdown hits 0 - mark OTP as expired
    $email = trim($_POST['email'] ?? '');

    if ($email) {
        try {
            $stmt = $pdo->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE email = ? AND status = 'Pending'");
            $stmt->execute([$email]);
            $response['success'] = true;
            $response['message'] = 'OTP expired.';
        } catch (\PDOException $e) {
            $response['message'] = 'System error.';
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;

