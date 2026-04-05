<?php
$host = 'centerbeam.proxy.rlwy.net';
$db   = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';
$port = 52624;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("SHOW TABLES");
    $db_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $sql_file = __DIR__ . '/microfin_platform/docs/Microfin-Updated-Sql.txt';
    $sql_content = file_get_contents($sql_file);

    preg_match_all('/CREATE TABLE `(.*?)`/', $sql_content, $matches);
    $sql_tables = $matches[1] ?? [];

    echo "Tables in Railway DB (" . count($db_tables) . "):\n";
    print_r($db_tables);

    echo "\nTables in SQL File (" . count($sql_tables) . "):\n";
    print_r($sql_tables);

    $missing_in_db = array_diff($sql_tables, $db_tables);
    $extra_in_db = array_diff($db_tables, $sql_tables);

    echo "\nMissing in Railway DB: " . implode(', ', $missing_in_db) . "\n";
    echo "Extra in Railway DB (will be lost or orphaned): " . implode(', ', $extra_in_db) . "\n";

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
