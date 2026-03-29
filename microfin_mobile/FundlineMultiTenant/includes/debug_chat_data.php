<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/db.php';

echo "<h1>Chat Data Debug</h1>";

// Check User Count
$res = $conn->query("SELECT COUNT(*) as cnt FROM users");
$row = $res->fetch_assoc();
echo "Total Users: " . $row['cnt'] . "<br>";

// Check Chat Messages
$sql = "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.created_at, u_sender.username as sender, u_receiver.username as receiver 
        FROM chat_messages m
        LEFT JOIN users u_sender ON m.sender_id = u_sender.user_id
        LEFT JOIN users u_receiver ON m.receiver_id = u_receiver.user_id
        ORDER BY m.id DESC LIMIT 10";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Sender</th><th>Receiver</th><th>Message</th><th>Time</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['sender_id']} ({$row['sender']})</td>";
        echo "<td>{$row['receiver_id']} ({$row['receiver']})</td>";
        echo "<td>" . htmlspecialchars($row['message']) . "</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No messages found in 'chat_messages' table.<br>";
}

echo "<h2>Check Admin User (ID 1)</h2>";
$res = $conn->query("SELECT * FROM users WHERE user_id = 1");
if ($res->num_rows > 0) {
    $u = $res->fetch_assoc();
    echo "Admin User Found: ID {$u['user_id']}, User: {$u['username']}, Role: {$u['role_id']}, Type: {$u['user_type']}<br>";
} else {
    echo "<strong style='color:red'>Admin User ID 1 NOT FOUND!</strong><br>";
    // Check if there is ANY admin
    $res2 = $conn->query("SELECT * FROM users WHERE role_id IN (1,2) LIMIT 1");
    if ($res2->num_rows > 0) {
        $u2 = $res2->fetch_assoc();
        echo "Found another Admin: ID {$u2['user_id']}, User: {$u2['username']}<br>";
    }
}

$conn->close();
?>
