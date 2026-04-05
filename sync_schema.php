<?php
/**
 * Sync SQL schema file with Railway database
 * Exports the current Railway DB schema and updates the local SQL file
 */

// Railway MySQL connection
$host = 'centerbeam.proxy.rlwy.net';
$port = 52624;
$dbname = 'railway';
$user = 'root';
$pass = 'zVULvPIbSyHVavTRnPFAkMWGVmvRwInd';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Connected to Railway database.\n";
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Build SQL schema
    $sql = "-- =====================================================\n";
    $sql .= "-- MicroFin DB Schema\n";
    $sql .= "-- Generated from Railway database\n";
    $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- =====================================================\n\n";
    
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sql .= "SET NAMES utf8mb4;\n\n";
    
    $sql .= "CREATE DATABASE IF NOT EXISTS `microfin_db`\n";
    $sql .= "  DEFAULT CHARACTER SET utf8mb4\n";
    $sql .= "  COLLATE utf8mb4_unicode_ci;\n\n";
    
    $sql .= "USE `microfin_db`;\n\n";
    
    // Drop tables section
    $sql .= "-- ---------------------------------------------------\n";
    $sql .= "-- Drop tables in safe order (children first)\n";
    $sql .= "-- ---------------------------------------------------\n";
    
    // Define drop order (children before parents due to FK constraints)
    $dropOrder = [
        'user_sessions',
        'tenant_website_content',
        'tenant_legitimacy_documents',
        'tenant_feature_toggles',
        'tenant_branding',
        'tenant_billing_payment_methods',
        'tenant_billing_invoices',
        'system_settings',
        'role_permissions',
        'payments',
        'payment_transactions',
        'otp_verifications',
        'notifications',
        'mobile_install_attributions',
        'email_delivery_logs',
        'credit_scores',
        'credit_investigations',
        'client_documents',
        'chat_messages',
        'backup_logs',
        'audit_logs',
        'application_documents',
        'amortization_schedule',
        'loans',
        'loan_applications',
        'loan_products',
        'document_types',
        'clients',
        'employees',
        'users',
        'user_roles',
        'permissions',
        'tenants'
    ];
    
    foreach ($dropOrder as $table) {
        if (in_array($table, $tables)) {
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        }
    }
    
    // Add any tables not in the predefined order
    foreach ($tables as $table) {
        if (!in_array($table, $dropOrder)) {
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        }
    }
    
    $sql .= "\n";
    
    // Define creation order (parents before children)
    $createOrder = [
        'tenants',
        'permissions',
        'user_roles',
        'users',
        'employees',
        'clients',
        'document_types',
        'loan_products',
        'loan_applications',
        'loans',
        'amortization_schedule',
        'application_documents',
        'audit_logs',
        'backup_logs',
        'chat_messages',
        'client_documents',
        'credit_investigations',
        'credit_scores',
        'email_delivery_logs',
        'mobile_install_attributions',
        'notifications',
        'otp_verifications',
        'payment_transactions',
        'payments',
        'role_permissions',
        'system_settings',
        'tenant_billing_invoices',
        'tenant_billing_payment_methods',
        'tenant_branding',
        'tenant_feature_toggles',
        'tenant_legitimacy_documents',
        'tenant_website_content',
        'user_sessions'
    ];
    
    // Get CREATE TABLE statements
    $processedTables = [];
    
    // First process tables in the defined order
    foreach ($createOrder as $table) {
        if (in_array($table, $tables) && !in_array($table, $processedTables)) {
            $sql .= "-- ---------------------------------------------------\n";
            $sql .= "-- Table: $table\n";
            $sql .= "-- ---------------------------------------------------\n";
            
            $result = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $result['Create Table'];
            
            // Clean up AUTO_INCREMENT values for portability
            $createSql = preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $createSql);
            
            $sql .= $createSql . ";\n\n";
            $processedTables[] = $table;
        }
    }
    
    // Then process any remaining tables not in the predefined order
    foreach ($tables as $table) {
        if (!in_array($table, $processedTables)) {
            $sql .= "-- ---------------------------------------------------\n";
            $sql .= "-- Table: $table\n";
            $sql .= "-- ---------------------------------------------------\n";
            
            $result = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $result['Create Table'];
            
            // Clean up AUTO_INCREMENT values for portability
            $createSql = preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $createSql);
            
            $sql .= $createSql . ";\n\n";
        }
    }
    
    $sql .= "-- ---------------------------------------------------\n";
    $sql .= "-- Re-enable foreign key checks\n";
    $sql .= "-- ---------------------------------------------------\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    // Write to file
    $targetFile = __DIR__ . '/microfin_platform/docs/Microfin-Updated-Sql.txt';
    file_put_contents($targetFile, $sql);
    
    echo "Schema exported successfully!\n";
    echo "File: $targetFile\n";
    echo "Tables: " . count($tables) . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
