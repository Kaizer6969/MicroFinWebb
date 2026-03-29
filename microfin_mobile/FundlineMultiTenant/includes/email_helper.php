<?php
/**
 * Email Helper - Fundline Email Notifications
 * Provides reusable functions for sending branded email notifications
 * Uses the same design template as OTP emails for consistency
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once '../phpmailer/phpmailer/src/Exception.php';
require_once '../phpmailer/phpmailer/src/PHPMailer.php';
require_once '../phpmailer/phpmailer/src/SMTP.php';

/**
 * Get configured PHPMailer instance
 */
function getMailer() {
    $mail = new PHPMailer(true);
    
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
    
    $mail->setFrom('no-reply@fundline.com', 'Fundline Microfinancing');
    $mail->isHTML(true);
    
    return $mail;
}

/**
 * Get email template base (matching OTP email design)
 */
function getEmailTemplate($title, $greeting, $content) {
    return "
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
                .info-box { background-color: #f9fafb; border-left: 4px solid #EF4444; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .info-label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
                .info-value { font-size: 16px; font-weight: 600; color: #111827; }
                .highlight-box { background-color: #fce7f3; border: 2px solid #be185d; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .highlight-amount { font-size: 32px; font-weight: bold; color: #be185d; }
                .button { display: inline-block; background-color: #EF4444; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
                .footer { background-color: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #9CA3AF; border-top: 1px solid #e5e7eb; }
                .divider { border-top: 1px solid #e5e7eb; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>FUNDLINE</h1>
                </div>
                <div class='content'>
                    <div class='greeting'>{$greeting}</div>
                    {$content}
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Fundline Micro Financing Services | Marilao Branch
                </div>
            </div>
        </body>
        </html>
    ";
}

/**
 * Send Document Verification Approval Email
 */
function sendVerificationApprovalEmail($email, $clientName, $creditLimit) {
    try {
        $mail = getMailer();
        $mail->addAddress($email, $clientName);
        $mail->Subject = 'Documents Approved - Fundline';
        
        $formattedLimit = number_format($creditLimit, 2);
        
        $content = "
            <div class='message'>
                Great news! Your submitted documents have been reviewed and <strong>approved</strong>.
            </div>
            
            <div class='highlight-box'>
                <div class='info-label'>Your Credit Limit</div>
                <div class='highlight-amount'>₱{$formattedLimit}</div>
            </div>
            
            <div class='message'>
                You can now proceed to apply for a loan up to your approved credit limit. Your credit limit was automatically calculated based on your monthly income and will increase as you successfully repay loans.
            </div>
            
            <div class='info-box'>
                <div class='info-label'>Next Steps</div>
                <div class='info-value'>
                    1. Log in to your Fundline account<br>
                    2. Navigate to Apply for Loan<br>
                    3. Choose your loan product and amount<br>
                    4. Submit your loan application
                </div>
            </div>
            
            <div class='message'>
                If you have any questions, please don't hesitate to contact us.
            </div>
        ";
        
        $mail->Body = getEmailTemplate('Documents Approved', "Hello {$clientName},", $content);
        $mail->AltBody = "Hello {$clientName},\n\nYour documents have been approved! Your credit limit is ₱{$formattedLimit}.\n\nYou can now apply for a loan through your Fundline account.";
        
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Verification Approval Email Error: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Send Document Verification Rejection Email
 */
function sendVerificationRejectionEmail($email, $clientName, $rejectionReason) {
    try {
        $mail = getMailer();
        $mail->addAddress($email, $clientName);
        $mail->Subject = 'Document Review Update - Fundline';
        
        $content = "
            <div class='message'>
                Thank you for submitting your documents for verification. After careful review, we regret to inform you that your documents could not be approved at this time.
            </div>
            
            <div class='info-box' style='border-left-color: #f59e0b;'>
                <div class='info-label'>Reason for Rejection</div>
                <div class='info-value' style='color: #d97706;'>{$rejectionReason}</div>
            </div>
            
            <div class='message'>
                <strong>What you can do:</strong><br>
                • Review the reason above carefully<br>
                • Prepare the correct or updated documents<br>
                • Resubmit your documents through your profile<br>
                • Ensure all documents are clear and valid
            </div>
            
            <div class='message'>
                We're here to help! If you have questions about the required documents or need assistance, please contact our support team.
            </div>
        ";
        
        $mail->Body = getEmailTemplate('Document Review Update', "Hello {$clientName},", $content);
        $mail->AltBody = "Hello {$clientName},\n\nYour document verification was not approved.\n\nReason: {$rejectionReason}\n\nPlease review and resubmit the correct documents through your Fundline account.";
        
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Verification Rejection Email Error: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Send Payment Receipt Email
 */
function sendPaymentReceiptEmail($email, $clientName, $paymentData) {
    try {
        $mail = getMailer();
        $mail->addAddress($email, $clientName);
        $mail->Subject = 'Payment Receipt - Fundline';
        
        $amount = number_format($paymentData['amount'], 2);
        $principal = number_format($paymentData['principal_paid'], 2);
        $interest = number_format($paymentData['interest_paid'], 2);
        $penalty = number_format($paymentData['penalty_paid'], 2);
        $balance = number_format($paymentData['remaining_balance'], 2);
        $loanNumber = htmlspecialchars($paymentData['loan_number']);
        $productName = htmlspecialchars($paymentData['product_name']);
        $paymentRef = htmlspecialchars($paymentData['payment_reference']);
        $paymentDate = date('F d, Y', strtotime($paymentData['payment_date']));
        
        $nextDueDate = '';
        if (!empty($paymentData['next_payment_due'])) {
            $nextDueDate = date('F d, Y', strtotime($paymentData['next_payment_due']));
        }
        
        $content = "
            <div class='message'>
                Your payment has been successfully received and processed. Thank you for your payment!
            </div>
            
            <div class='highlight-box'>
                <div class='info-label'>Amount Paid</div>
                <div class='highlight-amount'>₱{$amount}</div>
            </div>
            
            <div class='divider'></div>
            
            <div class='info-box'>
                <div class='info-label'>Payment Reference</div>
                <div class='info-value'>{$paymentRef}</div>
            </div>
            
            <div class='info-box'>
                <div class='info-label'>Payment Date</div>
                <div class='info-value'>{$paymentDate}</div>
            </div>
            
            <div class='info-box'>
                <div class='info-label'>Loan Number</div>
                <div class='info-value'>{$loanNumber}</div>
            </div>
            
            <div class='info-box'>
                <div class='info-label'>Product</div>
                <div class='info-value'>{$productName}</div>
            </div>
            
            <div class='divider'></div>
            
            <div class='message'>
                <strong>Payment Breakdown:</strong>
            </div>
            
            <div class='info-box'>
                <div class='info-label'>Principal Paid</div>
                <div class='info-value'>₱{$principal}</div>
            </div>
            
            <div class='info-box'>
                <div class='info-label'>Interest Paid</div>
                <div class='info-value'>₱{$interest}</div>
            </div>
            
            " . ($paymentData['penalty_paid'] > 0 ? "
            <div class='info-box'>
                <div class='info-label'>Penalty Paid</div>
                <div class='info-value'>₱{$penalty}</div>
            </div>
            " : "") . "
            
            <div class='divider'></div>
            
            <div class='info-box' style='background-color: #ecfdf5; border-left-color: #10b981;'>
                <div class='info-label'>Remaining Balance</div>
                <div class='info-value' style='color: #059669; font-size: 20px;'>₱{$balance}</div>
            </div>
            
            " . (!empty($nextDueDate) ? "
            <div class='info-box'>
                <div class='info-label'>Next Payment Due</div>
                <div class='info-value'>{$nextDueDate}</div>
            </div>
            " : "") . "
            
            <div class='message'>
                Keep this email as your payment receipt. You can also view your payment history anytime through your Fundline account.
            </div>
        ";
        
        $mail->Body = getEmailTemplate('Payment Receipt', "Hello {$clientName},", $content);
        $mail->AltBody = "Hello {$clientName},\n\nPayment Receipt\n\nAmount Paid: ₱{$amount}\nPayment Reference: {$paymentRef}\nPayment Date: {$paymentDate}\nLoan Number: {$loanNumber}\n\nPayment Breakdown:\nPrincipal: ₱{$principal}\nInterest: ₱{$interest}\nPenalty: ₱{$penalty}\n\nRemaining Balance: ₱{$balance}";
        
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Payment Receipt Email Error: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>

