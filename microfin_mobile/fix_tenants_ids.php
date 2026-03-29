<?php
require_once 'api/db.php';

$tenants_to_ensure = [
    'fundline' => 'Fundline',
    'plaridel' => 'PlaridelMFB',
    'sacredheart' => 'Sacred Heart Coop'
];

foreach ($tenants_to_ensure as $id => $name) {
    $res = $conn->query("SELECT * FROM tenants WHERE tenant_id = '$id'");
    if ($res->num_rows == 0) {
        // ID missing. Check slug.
        $slug = strtolower($id);
        $res_slug = $conn->query("SELECT * FROM tenants WHERE tenant_slug = '$slug'");
        if ($res_slug->num_rows > 0) {
            $row = $res_slug->fetch_assoc();
            $old_id = $row['tenant_id'];
            echo "Changing ID for tenant $name from $old_id to $id\n";
            // Disable FK checks to avoid issues with current setup (it's a dev fix)
            $conn->query("SET foreign_key_checks = 0");
            $conn->query("UPDATE tenants SET tenant_id = '$id' WHERE tenant_id = '$old_id'");
            $conn->query("UPDATE user_roles SET tenant_id = '$id' WHERE tenant_id = '$old_id'");
            $conn->query("SET foreign_key_checks = 1");
        } else {
            echo "Inserting new tenant $name with ID $id\n";
            $conn->query("INSERT INTO tenants (tenant_id, tenant_name, tenant_slug, status) VALUES ('$id', '$name', '$slug', 'Active')");
        }
    }
    // Ensure Client role
    $conn->query("INSERT IGNORE INTO user_roles (tenant_id, role_name, role_description, is_system_role) VALUES ('$id', 'Client', 'Client role', 1)");
}
echo "Tenants fixed.\n";
?>
