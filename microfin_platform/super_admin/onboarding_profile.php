<?php
session_start();
require_once '../backend/db_connect.php';
require_once __DIR__ . '/super_admin_auth.php';

if (!isset($_SESSION['super_admin_logged_in']) || $_SESSION['super_admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$superAdminId = (int)($_SESSION['super_admin_id'] ?? 0);
if ($superAdminId <= 0) {
    header('Location: login.php');
    exit;
}

$superAdmin = sa_load_super_admin_state($pdo, $superAdminId);
if (!$superAdmin) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

sa_sync_super_admin_session_from_state($superAdmin);

if (!empty($_SESSION['super_admin_force_password_change'])) {
    header('Location: force_change_password.php');
    exit;
}

if (!sa_super_admin_requires_onboarding($superAdmin)) {
    header('Location: super_admin.php');
    exit;
}

$provisionalUsername = sa_generate_unique_platform_username(
    $pdo,
    '',
    (string)($superAdmin['email'] ?? ''),
    '',
    '',
    $superAdminId
);
$initialUsername = (string)($superAdmin['username'] ?? '');
if ($initialUsername === $provisionalUsername) {
    $initialUsername = '';
}

function sa_onboarding_is_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

$form = [
    'username' => $initialUsername,
    'first_name' => (string)($superAdmin['first_name'] ?? ''),
    'last_name' => (string)($superAdmin['last_name'] ?? ''),
    'middle_name' => (string)($superAdmin['middle_name'] ?? ''),
    'suffix' => (string)($superAdmin['suffix'] ?? ''),
    'phone_number' => (string)($superAdmin['phone_number'] ?? ''),
    'date_of_birth' => (string)($superAdmin['date_of_birth'] ?? ''),
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['username'] = trim((string)($_POST['username'] ?? ''));
    $form['first_name'] = trim((string)($_POST['first_name'] ?? ''));
    $form['last_name'] = trim((string)($_POST['last_name'] ?? ''));
    $form['middle_name'] = trim((string)($_POST['middle_name'] ?? ''));
    $form['suffix'] = trim((string)($_POST['suffix'] ?? ''));
    $form['phone_number'] = trim((string)($_POST['phone_number'] ?? ''));
    $form['date_of_birth'] = trim((string)($_POST['date_of_birth'] ?? ''));

    $requestedUsername = $form['username'];
    $sanitizedUsername = $requestedUsername === '' ? '' : sa_sanitize_platform_username($requestedUsername);

    if ($form['first_name'] === '' || $form['last_name'] === '' || $form['phone_number'] === '' || $form['date_of_birth'] === '') {
        $error = 'First name, last name, phone number, and date of birth are required.';
    } elseif ($requestedUsername !== '' && $sanitizedUsername === '') {
        $error = 'Username can only contain letters, numbers, dots, underscores, or hyphens.';
    } elseif (!sa_onboarding_is_valid_date($form['date_of_birth'])) {
        $error = 'Please provide a valid date of birth.';
    } elseif ($sanitizedUsername !== '' && sa_platform_username_exists($pdo, $sanitizedUsername, $superAdminId)) {
        $error = 'That username is already being used by another platform admin.';
    } else {
        $resolvedUsername = $sanitizedUsername !== ''
            ? $sanitizedUsername
            : sa_generate_unique_platform_username(
                $pdo,
                '',
                (string)($superAdmin['email'] ?? ''),
                $form['first_name'],
                $form['last_name'],
                $superAdminId
            );

        $updateStmt = $pdo->prepare("
            UPDATE users
            SET username = ?,
                first_name = ?,
                last_name = ?,
                middle_name = ?,
                suffix = ?,
                phone_number = ?,
                date_of_birth = ?,
                status = 'Active'
            WHERE user_id = ?
        ");

        if ($updateStmt->execute([
            $resolvedUsername,
            $form['first_name'],
            $form['last_name'],
            $form['middle_name'] !== '' ? $form['middle_name'] : null,
            $form['suffix'] !== '' ? $form['suffix'] : null,
            $form['phone_number'],
            $form['date_of_birth'],
            $superAdminId,
        ])) {
            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action_type, entity_type, description)
                VALUES (?, 'SUPER_ADMIN_ONBOARDING_COMPLETED', 'user', ?)
            ");
            $logStmt->execute([$superAdminId, 'Super admin completed initial profile onboarding']);

            $superAdmin = sa_load_super_admin_state($pdo, $superAdminId);
            if ($superAdmin) {
                sa_sync_super_admin_session_from_state($superAdmin);
            } else {
                $_SESSION['super_admin_onboarding_required'] = false;
                $_SESSION['super_admin_username'] = $resolvedUsername;
            }

            header('Location: super_admin.php');
            exit;
        }

        $error = 'Unable to save your onboarding details. Please try again.';
    }
}

$uiTheme = sa_super_admin_theme($superAdmin);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($uiTheme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Profile - MicroFin Super Admin</title>
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
            max-width: 760px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
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
            font-size: 30px;
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

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            margin-bottom: 18px;
        }

        .field-full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            font: inherit;
        }

        input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 4px var(--brand-soft);
        }

        input[readonly] {
            opacity: 0.75;
        }

        .hint {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .required-mark {
            color: #ef4444;
            margin-left: 4px;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        button {
            min-width: 200px;
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

        @media (max-width: 720px) {
            .panel {
                padding: 24px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="eyebrow">Final Onboarding Step</div>
        <h1>Complete Your Admin Profile</h1>
        <p>
            Your password is already secured. Finish these account details to activate your super admin access and continue to the dashboard.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="grid">
                <div class="field field-full">
                    <label for="email_display">Email Address</label>
                    <input type="email" id="email_display" value="<?php echo htmlspecialchars((string)($superAdmin['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    <div class="hint">This login email was already assigned when your account was created.</div>
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional">
                    <div class="hint">Optional. Leave it blank to use the first word of your first name as the username.</div>
                </div>

                <div class="field">
                    <label for="phone_number">Phone Number<span class="required-mark">*</span></label>
                    <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($form['phone_number'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="field">
                    <label for="first_name">First Name<span class="required-mark">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($form['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="field">
                    <label for="last_name">Last Name<span class="required-mark">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($form['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="field">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($form['middle_name'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="field">
                    <label for="suffix">Suffix</label>
                    <input type="text" id="suffix" name="suffix" value="<?php echo htmlspecialchars($form['suffix'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional">
                </div>

                <div class="field">
                    <label for="date_of_birth">Date of Birth<span class="required-mark">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($form['date_of_birth'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>

            <div class="actions">
                <button type="submit">Activate Account</button>
            </div>
        </form>
    </div>
</body>
</html>
