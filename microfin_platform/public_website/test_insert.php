<?php
// DELETE CANDIDATE: No in-repo references found; appears to be legacy, test, backup, or export-only.
require_once '../backend/db_connect.php';
try {
    $tenant_id = uniqid();
    $tenant_name = 'Test';
    $first_name = 'Test';
    $last_name = 'Test';
    $mi = '';
    $suffix = '';
    $branch_name = 'Test';
    $plan_tier = 'Starter';
    $email = 'test@test.com';
    $mrr = 0;
    $max_c = 10;
    $max_u = 10;
    
    $demo_schedule_date = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        INSERT INTO tenants (
            tenant_id, tenant_name, first_name, last_name,
            mi, suffix, branch_name, plan_tier,
            email, demo_schedule_date, mrr, max_clients, max_users, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");
    $stmt->execute([
        $tenant_id, $tenant_name, $first_name, $last_name,
        $mi, $suffix, $branch_name, $plan_tier,
        $email, $demo_schedule_date, $mrr, $max_c, $max_u
    ]);
    echo "Success!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
