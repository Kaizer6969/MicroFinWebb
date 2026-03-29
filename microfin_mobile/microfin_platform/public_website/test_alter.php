<?php
// DELETE CANDIDATE: No in-repo references found; appears to be legacy, test, backup, or export-only.
require_once '../backend/db_connect.php';
try {
    $pdo->exec("ALTER TABLE tenants MODIFY COLUMN status ENUM('Active', 'Draft', 'CONSIDER', 'Suspended', 'Archived', 'Deleted', 'Compromised', 'Pending', 'Contacted', 'Accepted', 'Rejected', 'Demo Requested') DEFAULT 'Pending'");
    $pdo->exec("UPDATE tenants SET status = 'Pending' WHERE status = '' OR status = 'Demo Requested'");
    echo "SUCCESS";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
