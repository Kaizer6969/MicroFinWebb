<?php
require_once 'backend/db_connect.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$out = "";
foreach ($tables as $t) {
    if (strpos($t, 'schema_guard') !== false) continue;
    $stmt = $pdo->query("SHOW CREATE TABLE $t");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $out .= $res['Create Table'] . "\n\n";
}
file_put_contents('all_schemas.txt', $out);
