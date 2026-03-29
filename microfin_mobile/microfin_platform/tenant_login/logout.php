<?php
session_start();

$tenant_slug = $_SESSION['tenant_slug'] ?? '';
$tenant_key = $_SESSION['tenant_key'] ?? '';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id']) && empty($_SESSION['super_admin_logged_in'])) {
    require_once "../backend/db_connect.php";
    try {
        $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, description) VALUES (?, ?, 'STAFF_LOGOUT', 'user', 'Staff logged out of the system')")->execute([$_SESSION['user_id'], $_SESSION['tenant_id']]);
    } catch (PDOException $e) {
        // Log error to PHP error log but allow logout to proceed
        error_log("Logout audit log failed: " . $e->getMessage());
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(
		session_name(),
		'',
		time() - 42000,
		$params['path'],
		$params['domain'],
		$params['secure'],
		$params['httponly']
	);
}

session_destroy();

$redirect = 'login.php';
if ($tenant_slug !== '') {
	$redirect = '../site.php?site=' . urlencode($tenant_slug);
}

header('Location: ' . $redirect);
exit;
?>
