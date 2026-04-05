<?php
/**
 * Fix staff permissions - assign all permissions to a role
 */

$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$dbname = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    echo "Fetching all roles and permissions...\n\n";
    
    // Get all permissions
    $permissions = $pdo->query("SELECT permission_id, permission_code FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($permissions) . " permissions\n";
    
    // Get all roles (excluding Super Admin which has tenant_id = NULL)
    $roles = $pdo->query("SELECT role_id, role_name, tenant_id FROM user_roles WHERE tenant_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($roles) . " tenant roles\n\n";
    
    if (count($roles) === 0) {
        echo "No tenant roles found. You need to create a tenant first.\n";
        exit;
    }
    
    // For each role, assign all permissions
    $insert_stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    
    foreach ($roles as $role) {
        $count = 0;
        foreach ($permissions as $perm) {
            $insert_stmt->execute([$role['role_id'], $perm['permission_id']]);
            if ($insert_stmt->rowCount() > 0) $count++;
        }
        echo "✓ Role '{$role['role_name']}' (ID: {$role['role_id']}) - assigned $count new permissions\n";
    }
    
    echo "\n========================================\n";
    echo "Done! All roles now have all permissions.\n";
    echo "Refresh the staff dashboard to see all tabs.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
