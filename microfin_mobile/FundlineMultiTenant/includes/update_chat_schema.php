<?php
require_once 'config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
    -- receiver_id might not always be a user_id if it's a generic support inbox, 
    -- but for now let's assume it maps to a user or 0 for system
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'chat_messages' created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
