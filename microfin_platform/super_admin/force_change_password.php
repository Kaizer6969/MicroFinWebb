<?php
session_start();
require_once '../backend/db_connect.php';

if (!isset($_SESSION['super_admin_logged_in']) || $_SESSION['super_admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$superAdminId = (int) ($_SESSION['super_admin_id'] ?? 0);
if ($superAdminId <= 0) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT username, force_password_change, ui_theme
    FROM users
    WHERE user_id = ?
      AND user_type = 'Super Admin'
      AND deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$superAdminId]);
$superAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$superAdmin) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['super_admin_force_password_change'] = (bool) ($superAdmin['force_password_change'] ?? false);

if (!$_SESSION['super_admin_force_password_change']) {
    header('Location: super_admin.php');
    exit;
}

$uiTheme = (($superAdmin['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';
$_SESSION['ui_theme'] = $uiTheme;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Both password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ?, force_password_change = 0 WHERE user_id = ?');

        if ($updateStmt->execute([$hashedPassword, $superAdminId])) {
            $_SESSION['super_admin_force_password_change'] = false;

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action_type, entity_type, description)
                VALUES (?, 'PASSWORD_CHANGED', 'user', ?)
            ");
            $logStmt->execute([$superAdminId, 'Super admin completed forced password reset']);

            header('Location: super_admin.php');
            exit;
        }

        $error = 'Failed to update password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($uiTheme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MicroFin Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #dbe4ee;
            --brand: #0f172a;
            --brand-soft: rgba(15, 23, 42, 0.08);
            --error-bg: #fef2f2;
            --error-text: #b91c1c;
            --error-border: #fecaca;
        }

        html[data-theme='dark'] {
            --bg: #020617;
            --card: #0f172a;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --border: #1e293b;
            --brand: #38bdf8;
            --brand-soft: rgba(56, 189, 248, 0.12);
            --error-bg: rgba(127, 29, 29, 0.35);
            --error-text: #fecaca;
            --error-border: rgba(248, 113, 113, 0.4);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at top, rgba(56, 189, 248, 0.12), transparent 28%),
                linear-gradient(180deg, var(--bg), var(--bg));
            color: var(--text);
        }
        .panel {
            width: 100%;
            max-width: 440px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--brand-soft);
            color: var(--brand);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        h1 {
            margin: 20px 0 12px;
            font-size: 28px;
            line-height: 1.15;
        }
        p {
            margin: 0 0 24px;
            color: var(--muted);
            line-height: 1.6;
        }
        .error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
            font-size: 14px;
        }
        .field {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }
        input[type="password"] {
            width: 100%;
            padding: 13px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            font: inherit;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 4px var(--brand-soft);
        }
        .hint {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }
        button {
            width: 100%;
            padding: 14px 16px;
            border: 0;
            border-radius: 12px;
            background: var(--brand);
            color: #ffffff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        button:hover {
            filter: brightness(1.05);
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="eyebrow">First-Time Security Step</div>
        <h1>Reset Your Password</h1>
        <p>
            Welcome, <?php echo htmlspecialchars((string) ($superAdmin['username'] ?? 'Super Admin'), ENT_QUOTES, 'UTF-8'); ?>.
            Before accessing the super admin dashboard, you need to replace your temporary password with a new one.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                <div class="hint">Use at least 8 characters.</div>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit">Update Password</button>
        </form>
    </div>
</body>
</html>
