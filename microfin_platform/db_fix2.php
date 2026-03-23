<?php
require_once 'c:/xampp/htdocs/admin-draft-withmobile/admin-draft/microfin_platform/backend/db_connect.php';

// Set existing Admins to have can_manage_billing = 1 so they aren't locked out immediately.
$pdo->exec("UPDATE users SET can_manage_billing = 1 WHERE user_type = 'Tenant_Admin' OR role_id IN (SELECT role_id FROM user_roles WHERE role_name = 'Admin')");
echo "Updated existing admins.\n";
