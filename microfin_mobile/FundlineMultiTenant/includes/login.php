<?php
/**
 * Login Page - Fundline Multi-Tenant Web Application
 * Handles user authentication with tenant scoping
 * Usage: login.php?tenant=fundline (or plaridel, sacredheart)
 */

// Start session
session_start();

// Include database connection
require_once '../config/db.php';

// Initialize variables
$error_message = '';
$username = '';

// -----------------------------------------------
// STEP 1: Determine which tenant we're serving
// -----------------------------------------------
$tenant_slug = trim($_GET['tenant'] ?? $_POST['tenant_slug'] ?? $_SESSION['tenant_slug'] ?? 'fundline');

// Look up tenant from DB
$tenant_stmt = $conn->prepare("SELECT tenant_id, tenant_name, tenant_slug, theme_primary_color, theme_secondary_color, logo_path FROM tenants WHERE tenant_slug = ? AND is_active = 1");
$tenant_stmt->bind_param("s", $tenant_slug);
$tenant_stmt->execute();
$tenant_result = $tenant_stmt->get_result();
$tenant_data = $tenant_result->fetch_assoc();
$tenant_stmt->close();

// Default to tenant 1 (Fundline) if slug not found
if (!$tenant_data) {
    $tenant_stmt2 = $conn->prepare("SELECT tenant_id, tenant_name, tenant_slug, theme_primary_color, theme_secondary_color, logo_path FROM tenants WHERE tenant_id = 1 AND is_active = 1");
    $tenant_stmt2->execute();
    $tenant_data = $tenant_stmt2->get_result()->fetch_assoc();
    $tenant_stmt2->close();
    $tenant_slug = $tenant_data['tenant_slug'] ?? 'fundline';
}

$tenant_id   = $tenant_data['tenant_id']         ?? 1;
$tenant_name = $tenant_data['tenant_name']        ?? 'Fundline';
$primary_color   = $tenant_data['theme_primary_color']   ?? '#dc2626';
$secondary_color = $tenant_data['theme_secondary_color'] ?? '#991b1b';

// -----------------------------------------------
// STEP 2: Redirect already-logged-in users
// -----------------------------------------------
if (isset($_SESSION['user_id']) && isset($_SESSION['tenant_id'])) {
    if ($_SESSION['user_type'] === 'Employee') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

// -----------------------------------------------
// STEP 3: Process login form submission
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Get and sanitize input
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['rememberMe']);

    // Validate inputs
    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password";
    } else {
        // Look up user scoped to this tenant
        $stmt = $conn->prepare("
            SELECT u.user_id, u.username, u.email, u.password_hash, u.role_id,
                   u.user_type, u.status, u.failed_login_attempts, ur.role_name
            FROM users u
            LEFT JOIN user_roles ur ON u.role_id = ur.role_id
            WHERE (u.username = ? OR u.email = ?)
              AND u.tenant_id = ?
              AND u.email_verified = TRUE
        ");
        $stmt->bind_param("ssi", $username, $username, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Check account status
            if ($user['status'] === 'Locked') {
                $error_message = "Account is locked. Please contact administrator.";
            } elseif ($user['status'] === 'Suspended') {
                $error_message = "Account is suspended. Please contact administrator.";
            } elseif ($user['status'] === 'Inactive') {
                $error_message = "Account is inactive. Please contact administrator.";
            } else {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Reset failed attempts and record last login
                    $update_stmt = $conn->prepare("
                        UPDATE users
                        SET failed_login_attempts = 0,
                            last_login = NOW()
                        WHERE user_id = ?
                    ");
                    $update_stmt->bind_param("i", $user['user_id']);
                    $update_stmt->execute();
                    $update_stmt->close();

                    // Set session variables (including tenant context)
                    $_SESSION['user_id']     = $user['user_id'];
                    $_SESSION['username']    = $user['username'];
                    $_SESSION['email']       = $user['email'];
                    $_SESSION['role_id']     = $user['role_id'];
                    $_SESSION['role_name']   = $user['role_name'];
                    $_SESSION['user_type']   = $user['user_type'];
                    $_SESSION['tenant_id']   = $tenant_id;
                    $_SESSION['tenant_slug'] = $tenant_slug;
                    $_SESSION['tenant_name'] = $tenant_name;

                    // Create session token
                    $session_token = bin2hex(random_bytes(32));
                    $expires_at    = date('Y-m-d H:i:s', strtotime('+2 hours'));
                    $ip_address    = $_SERVER['REMOTE_ADDR'];
                    $user_agent    = $_SERVER['HTTP_USER_AGENT'];

                    $session_stmt = $conn->prepare("
                        INSERT INTO user_sessions (user_id, tenant_id, session_token, ip_address, user_agent, expires_at)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $session_stmt->bind_param("iissss", $user['user_id'], $tenant_id, $session_token, $ip_address, $user_agent, $expires_at);
                    $session_stmt->execute();
                    $session_stmt->close();

                    $_SESSION['session_token'] = $session_token;

                    // Log audit
                    $audit_stmt = $conn->prepare("
                        INSERT INTO audit_logs (user_id, tenant_id, action_type, description, ip_address)
                        VALUES (?, ?, 'LOGIN', 'User logged in successfully', ?)
                    ");
                    $audit_stmt->bind_param("iis", $user['user_id'], $tenant_id, $ip_address);
                    $audit_stmt->execute();
                    $audit_stmt->close();

                    // Set remember me cookie
                    if ($remember_me) {
                        setcookie('fundline_user', $user['username'], time() + (86400 * 30), "/");
                    }

                    // Determine redirect URL
                    $redirect_url = "dashboard.php";
                    if ($user['role_name'] === 'Super Admin') {
                        $redirect_url = "super_admin_dashboard.php";
                    } elseif ($user['user_type'] === 'Employee') {
                        $redirect_url = "admin_dashboard.php";
                    }

                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'redirect' => $redirect_url]);
                        exit();
                    } else {
                        header("Location: " . $redirect_url);
                        exit();
                    }
                } else {
                    // Invalid password — increment failed attempts
                    $failed_attempts = $user['failed_login_attempts'] + 1;
                    $status = ($failed_attempts >= 5) ? 'Locked' : $user['status'];

                    $update_stmt = $conn->prepare("
                        UPDATE users
                        SET failed_login_attempts = ?,
                            status = ?
                        WHERE user_id = ?
                    ");
                    $update_stmt->bind_param("isi", $failed_attempts, $status, $user['user_id']);
                    $update_stmt->execute();
                    $update_stmt->close();

                    if ($status === 'Locked') {
                        $error_message = "Account locked due to multiple failed login attempts.";
                    } else {
                        $error_message = "Invalid username or password";
                    }
                }
            }
        } else {
            $error_message = "Invalid username or password";
        }

        $stmt->close();
    }

    if ($is_ajax && !empty($error_message)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit();
    }
}

// Redirect non-POST requests to index
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?tenant=" . urlencode($tenant_slug));
    exit();
}

$conn->close();
?>
