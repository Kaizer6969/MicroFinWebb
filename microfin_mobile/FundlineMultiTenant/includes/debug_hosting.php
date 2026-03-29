<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1> Fundline Hosting Debugger </h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// 1. Check DB Config
$config_file = '../config/db.php';
if (!file_exists($config_file)) {
    die("<p style='color:red'>CRITICAL: ../config/db.php not found! Check case sensitivity (Config vs config) or path.</p>");
} else {
    echo "<p style='color:green'>Found config/db.php</p>";
    require_once $config_file;
}

// 2. Test Connection
if ($conn->connect_error) {
    die("<p style='color:red'>Connection Failed: " . $conn->connect_error . "</p>");
}
echo "<p style='color:green'>Database Connected Successfully</p>";

// 3. Check Clients Table Columns
echo "<h2>Schema Check: Clients Table</h2>";
$required_cols = ['document_verification_status', 'verification_rejection_reason'];
$result = $conn->query("SHOW COLUMNS FROM clients");
$existing_cols = [];
while ($row = $result->fetch_assoc()) {
    $existing_cols[] = $row['Field'];
}

foreach ($required_cols as $col) {
    if (in_array($col, $existing_cols)) {
        echo "<p style='color:green'>Column '$col' EXISTS</p>";
    } else {
        echo "<p style='color:red'>Column '$col' MISSING - Run the SQL update!</p>";
    }
}

// 4. Check Client Documents Table
echo "<h2>Schema Check: Document Tables</h2>";
$tables = ['client_documents', 'kyc_documents'];
foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows > 0) {
        echo "<p style='color:green'>Table '$table' EXISTS</p>";
    } else {
        echo "<p style='color:red'>Table '$table' NOT FOUND</p>";
    }
}

// 5. Check Output Buffering / Headers
echo "<h2>Session/Header Check</h2>";
if (headers_sent($file, $line)) {
    echo "<p style='color:red'>Headers already sent in $file on line $line</p>";
} else {
    echo "<p style='color:green'>Headers not sent yet (Good)</p>";
}

echo "<hr><p>Debug Complete. Delete this file after use.</p>";
?>

