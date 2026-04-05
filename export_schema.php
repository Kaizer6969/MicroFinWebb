<?php
// Export database schema to Microfin-Updated-Sql.txt
$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$database = 'railway';
$username = 'root';
$password = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $output = "";
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $output .= "-- Table: $table\n";
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $output .= $createStmt['Create Table'] . ";\n\n";
    }
    
    // Write to file
    $filePath = __DIR__ . '/microfin_platform/docs/Microfin-Updated-Sql.txt';
    file_put_contents($filePath, $output);
    
    echo "Schema exported successfully to Microfin-Updated-Sql.txt\n";
    echo "Total tables: " . count($tables) . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
