<?php
session_start();
require_once '../backend/db_connect.php';

if (isset($_SESSION['super_admin_logged_in']) && $_SESSION['super_admin_logged_in'] === true) {
    $destination = !empty($_SESSION['super_admin_force_password_change'])
        ? 'force_change_password.php'
        : (!empty($_SESSION['super_admin_onboarding_required']) ? 'onboarding_profile.php' : 'super_admin.php');
    header('Location: ' . $destination);
    exit;
}

function sa_password_reset_link(string $token): string
{
    $explicitBase = trim((string)(getenv('APP_BASE_URL') ?: getenv('PUBLIC_BASE_URL') ?: ''));
    if ($explicitBase !== '') {
        return rtrim($explicitBase, '/') . '/super_admin/reset_password.php?token=' . urlencode($token);
    }

    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
    $protocol = $isHttps ? 'https://' : 'http://';
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/microfin_platform/super_admin/forgot_password.php'));
    $resetPath = rtrim($scriptDir, '/') . '/reset_password.php';

    return $protocol . $domainName . $resetPath . '?token=' . urlencode($token);
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '') {
        $message_type = 'error';
        $message = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_type = 'error';
        $message = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, username, first_name, last_name, status
            FROM users
            WHERE email = ?
              AND user_type = 'Super Admin'
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $superAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$superAdmin) {
            $message_type = 'error';
            $message = 'No super admin account found with that email address.';
        } else {
            $status = trim((string)($superAdmin['status'] ?? ''));
            if (!in_array($status, ['Active', 'Inactive'], true)) {
                $message_type = 'error';
                $message = 'This account is not eligible for password reset while its status is ' . strtolower($status) . '.';
            } else {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
                $update->execute([$token, $expiry, $superAdmin['user_id']]);

                $name = trim((string)($superAdmin['first_name'] ?? '') . ' ' . (string)($superAdmin['last_name'] ?? ''));
                if ($name === '') {
                    $name = trim((string)($superAdmin['username'] ?? 'Super Admin'));
                }

                $resetLink = sa_password_reset_link($token);
                $htmlBody = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #0f172a;'>
                        <h2>Reset Your Super Admin Password</h2>
                        <p>Hello " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>
                        <p>We received a request to reset your MicroFin platform owner password.</p>
                        <p>Click the button below to set a new password. This link will expire in 1 hour.</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "' style='background-color: #0f172a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Reset Password</a>
                        </div>
                        <p>If the button does not work, copy and paste this link into your browser:</p>
                        <p style='word-break: break-all; color: #64748b;'>" . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . "</p>
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;' />
                        <p style='font-size: 12px; color: #94a3b8;'>If you did not request a password reset, you can safely ignore this email.</p>
                    </div>
                ";

                $result_msg = mf_send_brevo_email($email, 'MicroFin - Super Admin Password Reset', $htmlBody);
                if ($result_msg === 'Email sent successfully.') {
                    $message_type = 'success';
                    $message = 'A password reset link has been sent to that email address.';
                } else {
                    $message_type = 'error';
                    $message = 'Failed to send email. Error: ' . $result_msg;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin - Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-color: #0f172a;
            --bg-color: #f8fafc;
            --surface-color: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-primary);
            padding: 20px;
        }

        .card {
            background: var(--surface-color);
            width: 100%;
            max-width: 430px;
            padding: 3rem 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }

        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
        }

        p {
            margin: 0 0 1.5rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            margin-bottom: 1rem;
        }

        input:focus {
            outline: none;
            border-color: var(--brand-color);
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 0.875rem;
            background-color: var(--brand-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .back-link {
            display: inline-block;
            margin-top: 1.25rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Forgot Password?</h1>
        <p>Enter your super admin email address and we'll check the account, verify its status, and send you a reset link.</p>

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="superadmin@microfin.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>

        <a href="login.php" class="back-link">&larr; Back to Login</a>
    </div>
</body>
</html>
