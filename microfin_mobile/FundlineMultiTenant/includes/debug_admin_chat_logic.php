<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../config/db.php';

echo "<h1>Debug Admin Chat Logic</h1>";

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in. Simulating user_id = 1 (Admin)<br>";
    $user_id = 1;
} else {
    $user_id = $_SESSION['user_id'];
    echo "Logged in as User ID: $user_id<br>";
}

echo "<h2>Testing get_conversations query</h2>";

$sql = "
    SELECT 
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id 
            ELSE m.sender_id 
        END as chat_partner_id,
        MAX(m.created_at) as last_msg_time,
        u.username,
        u.user_type
    FROM chat_messages m
    JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.user_id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY chat_partner_id, u.username, u.user_type
    ORDER BY last_msg_time DESC
";

// Note: I added u.username, u.user_type to GROUP BY just in case of strict mode

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    echo "Query executed successfully.<br>";
    echo "Found " . $result->num_rows . " conversations.<br>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<strong style='color:red'>Error: " . $e->getMessage() . "</strong>";
}

$conn->close();
?>
