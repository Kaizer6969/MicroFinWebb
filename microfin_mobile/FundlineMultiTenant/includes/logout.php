<?php
/**
 * Logout Page - Fundline Multi-Tenant Web Application
 * Destroys session and redirects to login
 */

session_start();

require_once '../config/db.php';

$tenant_slug = $_SESSION['tenant_slug'] ?? 'fundline';

// Log audit before destroying session
if (isset($_SESSION['user_id'])) {
    $user_id   = $_SESSION['user_id'];
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    $ip_address = $_SERVER['REMOTE_ADDR'];

    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, tenant_id, action_type, description, ip_address)
        VALUES (?, ?, 'LOGOUT', 'User logged out', ?)
    ");
    $stmt->bind_param("iis", $user_id, $tenant_id, $ip_address);
    $stmt->execute();
    $stmt->close();

    // Delete session token from database
    if (isset($_SESSION['session_token'])) {
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->bind_param("s", $_SESSION['session_token']);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Delete remember me cookie if exists
if (isset($_COOKIE['fundline_user'])) {
    setcookie('fundline_user', '', time() - 3600, '/');
}

// Redirect back to login for this tenant
header("Location: login.php?tenant=" . urlencode($tenant_slug) . "&logout=success");
exit();
?>
