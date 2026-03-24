<?php
// DELETE CANDIDATE: No in-repo references found; appears to be legacy, test, backup, or export-only.
require_once '../backend/db_connect.php';
$stmt = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'status'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
