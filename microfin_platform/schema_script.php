<?php
require_once 'backend/db_connect.php';
$stmt = $pdo->query("SHOW CREATE TABLE otp_verifications");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
file_put_contents('schema_query.txt', $res['Create Table']);
