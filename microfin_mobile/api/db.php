<?php
require_once __DIR__ . '/config.php';

$dbConfig = microfin_database_config();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database'],
        $dbConfig['port']
    );
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error: ' . $e->getMessage(),
    ]);
    exit;
}
?>
