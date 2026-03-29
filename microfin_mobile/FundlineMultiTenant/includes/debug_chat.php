<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

echo "<h2>Database Debug</h2>";

// Check User 1
$result = $conn->query("SELECT user_id, username, role_id FROM users WHERE user_id = 1");
if ($result && $result->num_rows > 0) {
    echo "User 1 exists: " . json_encode($result->fetch_assoc()) . "<br>";
} else {
    echo "<strong style='color:red'>User 1 does NOT exist!</strong> (This is required for the default receiver)<br>";
}

// Check chat_messages table
$result = $conn->query("SHOW TABLES LIKE 'chat_messages'");
if ($result && $result->num_rows > 0) {
    echo "Table 'chat_messages' exists.<br>";
    
    $result = $conn->query("DESCRIBE chat_messages");
    while($row = $result->fetch_assoc()) {
        echo "Column: " . $row['Field'] . " - " . $row['Type'] . "<br>";
    }

} else {
    echo "<strong style='color:red'>Table 'chat_messages' does NOT exist!</strong><br>";
}

// Check session
session_start();
echo "<h2>Session Debug</h2>";
if (isset($_SESSION['user_id'])) {
    echo "Logged in as User ID: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "Not logged in.<br>";
}

$conn->close();
?>

