<?php
require_once '../backend/db_connect.php';
$stmt = $pdo->query('SELECT tenant_id, tenant_name, status, max_clients, max_users, created_at FROM tenants ORDER BY created_at DESC LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
