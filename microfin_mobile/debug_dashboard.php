<?php
require 'api/db.php';
$user_id = 7;
$tenant_id = '92ETR4W4Q3';

echo "Querying for user_id=$user_id on tenant_id=$tenant_id\n";

$stmt = $conn->prepare("
    SELECT u.first_name as u_first, u.last_name as u_last, u.username as u_name, c.first_name as c_first, c.last_name as c_last 
    FROM users u 
    LEFT JOIN clients c ON u.user_id = c.user_id AND c.tenant_id = ? 
    WHERE u.user_id = ?
");
$stmt->bind_param("si", $tenant_id, $user_id);
$stmt->execute();
$resUser = $stmt->get_result();
echo "ResUser rows: " . $resUser->num_rows . "\n";
if ($resUser->num_rows > 0) {
    $u = $resUser->fetch_assoc();
    print_r($u);
}
?>
