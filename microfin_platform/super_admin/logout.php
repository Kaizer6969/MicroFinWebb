<?php
require_once '../backend/session_auth.php';
mf_start_backend_session();
require_once '../backend/db_connect.php';
require_once '../backend/login_activity.php';

$superAdminId = (int)($_SESSION['super_admin_id'] ?? 0);
if ($superAdminId > 0) {
    mf_update_user_last_login($pdo, $superAdminId);
}

mf_destroy_backend_session($pdo);

header('Location: login.php');
exit;
?>
