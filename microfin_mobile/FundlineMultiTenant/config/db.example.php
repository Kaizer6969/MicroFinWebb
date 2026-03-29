<?php
/**
 * Database Configuration Template
 * Copy this file to db.php and update with your actual credentials
 */

// ==========================================
// LOCAL DEVELOPMENT SETTINGS
// ==========================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'fundline_microfinancing');

// ==========================================
// INFINITYFREE HOSTING SETTINGS
// Uncomment and update these when deploying
// ==========================================
// define('DB_HOST', 'sql123.infinityfree.com');  // Your actual DB host
// define('DB_USER', 'if0_12345678');              // Your actual DB username
// define('DB_PASS', 'your_database_password');    // Your actual DB password
// define('DB_NAME', 'if0_12345678_fundline');     // Your actual DB name

// Set default timezone to Manila
date_default_timezone_set('Asia/Manila');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for proper character support
$conn->set_charset("utf8mb4");

// Set MySQL timezone to Manila (UTC+8)
$conn->query("SET time_zone = '+08:00'");

/**
 * Function to close database connection
 */
function closeConnection() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}
?>
