<?php
session_start();
require_once '../backend/db_connect.php';

$token = trim((string)($_GET['token'] ?? ''));
$message = '';
$message_type = '';
$user_id = null;

if ($token !== '') {
    $stmt = $pdo->prepare("
        SELECT user_id, status
        FROM users
        WHERE reset_token = ?
          AND reset_token_expiry > NOW()
          AND user_type = 'Super Admin'
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $superAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$superAdmin) {
        $message_type = 'error';
        $message = 'This password reset link is invalid or has expired. Please request a new one.';
    } elseif (!in_array(trim((string)($superAdmin['status'] ?? '')), ['Active', 'Inactive'], true)) {
        $message_type = 'error';
        $message = 'This account is not eligible for password reset.';
    } else {
        $user_id = (int)$superAdmin['user_id'];
    }
} else {
    $message_type = 'error';
    $message = 'No reset token was provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if (strlen($new_password) < 8) {
        $message_type = 'error';
        $message = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $message_type = 'error';
        $message = 'Passwords do not match.';
    } else {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("
            UPDATE users
            SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL, force_password_change = 0
            WHERE user_id = ?
        ");

        if ($update->execute([$password_hash, $user_id])) {
            $message_type = 'success';
            $message = 'Your password has been successfully reset. You can now sign in.';
            $user_id = null;
        } else {
            $message_type = 'error';
            $message = 'A system error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin - Reset Password</title>
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

        .btn-submit,
        .login-link {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.875rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-submit {
            background-color: var(--brand-color);
            color: white;
            border: none;
            cursor: pointer;
        }

        .login-link {
            margin-top: 1rem;
            color: var(--brand-color);
            border: 1px solid var(--brand-color);
            background: transparent;
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
    </style>
</head>
<body>
    <div class="card">
        <h1>Set New Password</h1>
        <p>Choose a new password for your super admin account.</p>

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($user_id): ?>
        <form method="POST">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" minlength="8" required>

            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

            <button type="submit" class="btn-submit">Reset Password</button>
        </form>
        <?php endif; ?>

        <?php if ($message_type === 'success' || !$user_id): ?>
        <a href="login.php" class="login-link">Return to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
