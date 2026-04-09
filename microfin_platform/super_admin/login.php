<?php
require_once '../backend/session_auth.php';
mf_start_backend_session();
require_once '../backend/db_connect.php';

$allowManualSuperAdminLogin = $_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['auth']) || isset($_GET['switch']);

if (!$allowManualSuperAdminLogin && mf_refresh_backend_session_state($pdo, 'super_admin') && isset($_SESSION['super_admin_logged_in']) && $_SESSION['super_admin_logged_in'] === true) {
    $destination = !empty($_SESSION['super_admin_force_password_change'])
        ? 'force_change_password.php'
        : (!empty($_SESSION['super_admin_onboarding_required']) ? 'onboarding_profile.php' : 'super_admin.php');
    header('Location: ' . $destination);
    exit;
}

$error_msg = '';
$active_browser_session = mf_get_active_browser_backend_session($pdo);
$browser_session_block_message = $active_browser_session
    ? 'This browser already has an active session. Please log out of the current account before signing in again.'
    : '';

if ($error_msg === '' && $browser_session_block_message !== '') {
    $error_msg = $browser_session_block_message;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../backend/login_activity.php';
    require_once __DIR__ . '/super_admin_auth.php';

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($browser_session_block_message !== '') {
        $error_msg = $browser_session_block_message;
    } elseif ($email === '' || $password === '') {
        $error_msg = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id AS super_admin_id,
                   username,
                   password_hash,
                   ui_theme,
                   force_password_change,
                   status
            FROM users
            WHERE email = ?
              AND user_type = 'Super Admin'
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $status = trim((string)($admin['status'] ?? ''));
            if (!in_array($status, ['Active', 'Inactive'], true)) {
                $error_msg = 'This account is not available. Please contact another platform owner.';
            } else {
                mf_update_user_last_login($pdo, (int) $admin['super_admin_id']);

                unset(
                    $_SESSION['user_logged_in'],
                    $_SESSION['user_id'],
                    $_SESSION['username'],
                    $_SESSION['tenant_id'],
                    $_SESSION['tenant_name'],
                    $_SESSION['tenant_slug'],
                    $_SESSION['role'],
                    $_SESSION['user_type'],
                    $_SESSION['theme']
                );

                $_SESSION['super_admin_logged_in'] = true;
                $_SESSION['super_admin_id'] = (int) $admin['super_admin_id'];
                $_SESSION['super_admin_username'] = $admin['username'];
                $_SESSION['ui_theme'] = sa_super_admin_theme($admin);
                $_SESSION['super_admin_force_password_change'] = (bool) ($admin['force_password_change'] ?? false);
                $_SESSION['super_admin_onboarding_required'] = ($status === 'Inactive' && empty($admin['force_password_change']));

                mf_create_backend_session($pdo, (int) $admin['super_admin_id'], null, 'super_admin');

                $destination = !empty($_SESSION['super_admin_force_password_change'])
                    ? 'force_change_password.php'
                    : (!empty($_SESSION['super_admin_onboarding_required']) ? 'onboarding_profile.php' : 'super_admin.php');
                header('Location: ' . $destination);
                exit;
            }
        } else {
            $error_msg = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MicroFin - Super Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="super_admin_theme.css">
    <link rel="stylesheet" href="super_admin_auth.css">
</head>
<body class="platform-auth auth-compact">

    <div class="loader-overlay" id="loader">
        <div class="spinner"></div>
        <p class="loader-message">Authenticating...</p>
    </div>

    <div class="login-container">
        <div class="logo-header">
            <span class="material-symbols-outlined logo-icon">admin_panel_settings</span>
            <h1 class="company-name">MicroFin OS</h1>
            <p class="subtitle">Platform Owner Login</p>
        </div>

        <form id="login-form" method="POST" action=""<?php echo $browser_session_block_message !== '' ? ' onsubmit="return false;"' : ''; ?>>
            <?php if ($error_msg !== ''): ?>
            <div class="auth-alert auth-alert-error">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="superadmin@microfin.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"<?php echo $browser_session_block_message !== '' ? ' disabled' : ''; ?> required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>

            <div style="text-align: right; margin-top: -0.5rem; margin-bottom: 0.5rem;">
                <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-submit" id="submit-btn"<?php echo $browser_session_block_message !== '' ? ' disabled' : ''; ?>>Sign In to Dashboard</button>
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="../public_website/index.php" class="home-link" style="justify-content: center;">
                    <span class="material-symbols-outlined" style="font-size: 1rem;">arrow_back</span>
                    Back to Home
                </a>
            </div>
        </form>
    </div>

    <script src="login.js"></script>
</body>
</html>
