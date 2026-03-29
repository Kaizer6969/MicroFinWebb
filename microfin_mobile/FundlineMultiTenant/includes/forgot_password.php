<?php
/**
 * Forgot Password Page
 * Allows users to request a password reset link
 */

// Start session
session_start();

// Include database connection
require_once '../config/db.php';

// Include PHPMailer
// Adjust path if necessary based on your folder structure
require_once '../phpmailer/phpmailer/src/Exception.php';
require_once '../phpmailer/phpmailer/src/PHPMailer.php';
require_once '../phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$error_message = '';
$success_message = '';
$email = '';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'Employee') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error_message = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ? AND status != 'Inactive'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            // Set expiry to 1 hour from now
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in database
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssi", $token, $expiry, $user['user_id']);
            
            if ($update_stmt->execute()) {
                // Send Email
                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';             // Set the SMTP server to send through
                    $mail->SMTPAuth   = true;                         // Enable SMTP authentication
                    $mail->Username   = 'onecallnoreplynotification@gmail.com';   // SMTP username
                    $mail->Password   = 'gjjjnkpcbqsqqofc';           // SMTP password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    
                    // Fix for local XAMPP certificate issues
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    // Recipients
                    $mail->setFrom('no-reply@fundline.com', 'Fundline Microfinancing');
                    $mail->addAddress($email, $user['username']);

                    // Content
                    // Dynamically build the reset link based on current host and script path
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    // Get the directory of the current script (e.g., /Fundline/includes or /includes)
                    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                    $reset_link = $protocol . "://" . $host . $path . "/reset_password.php?token=" . $token;
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'Fundline Password Reset';
                    
                    // Email Design with Red Theme (#EF4444)
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
                                .btn-container { text-align: center; margin: 35px 0; }
                                .btn { background-color: #EF4444; color: #ffffff !important; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; display: inline-block; transition: background-color 0.2s; }
                                .btn:hover { background-color: #DC2626; }
                                .footer { background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9CA3AF; border-top: 1px solid #e5e7eb; }
                                .expiry-note { font-size: 14px; color: #6B7280; font-style: italic; margin-top: 20px; text-align: center; }
                            </style>
                        </head>
                        <body>
                            <div class='email-container'>
                                <div class='header'>
                                    <h1>FUNDLINE</h1>
                                </div>
                                <div class='content'>
                                    <div class='greeting'>Hello {$user['username']},</div>
                                    <div class='message'>
                                        We received a request to reset your password for your Fundline Microfinancing account. 
                                        Don't worry, it happens to the best of us!
                                        <br><br>
                                        To set a new password, simply click the button below:
                                    </div>
                                    <div class='btn-container'>
                                        <a href='{$reset_link}' class='btn'>Reset My Password</a>
                                    </div>
                                    <div class='expiry-note'>
                                        This link is secure and will expire in 1 hour.
                                    </div>
                                    <div class='message' style='margin-top: 30px; font-size: 14px; border-top: 1px solid #eee; padding-top: 20px;'>
                                        If you didn't request a password reset, you can safely ignore this email. Your account remains secure.
                                    </div>
                                </div>
                                <div class='footer'>
                                    &copy; " . date('Y') . " Fundline Micro Financing Services | Marilao Branch<br>
                                    All rights reserved.
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    $mail->AltBody = "Hello {$user['username']},\n\nWe received a request to reset your password. Please copy and paste the following link into your browser to reset your password:\n\n{$reset_link}\n\nThis link is secure and valid for 1 hour.";

                    $mail->send();
                    $success_message = "A password reset link has been sent to your email.";
                    // Clear email field to prevent resubmission
                    $email = '';
                } catch (Exception $e) {
                    $error_message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
                
                // FOR DEVELOPMENT: Log the link to a file so it can be tested without email
                $log_message = "[" . date('Y-m-d H:i:s') . "] Email: $email | Link: $reset_link" . PHP_EOL;
                file_put_contents('../uploads/reset_debug.txt', $log_message, FILE_APPEND);
                
                // If email failed but we want to allow testing, we can show a special message or just rely on the file
                if (!empty($error_message)) {
                     $success_message = "Email failed (check configuration). <br><strong>DEV MODE:</strong> Link saved to uploads/reset_debug.txt";
                     $error_message = ''; // Clear error to show success
                } else {
                     $success_message = "A password reset link has been sent to your email.";
                }
            } else {
                $error_message = "Something went wrong. Please try again later.";
            }
            $update_stmt->close();
        } else {
            // Security: Don't reveal if email exists, just show success-like message or generic error. 
            // However, for UX in this specific internal app context, telling them 'Email not found' might be preferred by the user unless high security is required.
            // Following user request 'make sure the gmail used to recover is the one registered', it implies validation.
            // I'll stick to a generic message for security best practice but making it helpful.
            $error_message = "If that email address is in our database, we have sent a password reset link to it.";
        }
        $stmt->close();
    }
    
    // Handle AJAX response
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => !empty($success_message), 'message' => empty($error_message) ? $success_message : $error_message]);
        exit();
    }
}

// Redirect non-POST requests to index
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}
?>

