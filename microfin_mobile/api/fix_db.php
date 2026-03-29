<?php
require_once 'db.php';
try {
    $conn->query("ALTER TABLE payments MODIFY COLUMN received_by INT NULL DEFAULT NULL");
    echo "Successfully altered payments table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
