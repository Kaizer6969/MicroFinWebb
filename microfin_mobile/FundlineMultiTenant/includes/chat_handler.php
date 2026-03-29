<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
// Fetch user role to determine permissions
$sql_role = "SELECT ur.role_name FROM users u JOIN user_roles ur ON u.role_id = ur.role_id WHERE u.user_id = ?";
$stmt_role = $conn->prepare($sql_role);
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$res_role = $stmt_role->get_result();
$row_role = $res_role->fetch_assoc();
$is_admin = ($row_role['role_name'] === 'Admin' || $row_role['role_name'] === 'Super Admin');
$stmt_role->close();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    $receiver_id = $_POST['receiver_id'] ?? 1; // Default to admin for clients

    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit();
    }

    // If client, force receiver to be Admin (or specific support user)
    // If admin, they can specify receiver (the client)
    if (!$is_admin) {
        $receiver_id = 1; 
    }

    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $receiver_id, $message);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
    $stmt->close();

} elseif ($action === 'get_messages') {
    $other_user_id = $_GET['user_id'] ?? 1; // Default to admin for clients

    // If client, they can only chat with admin (1)
    if (!$is_admin) {
        $other_user_id = 1;
    } 

    $stmt = $conn->prepare("
        SELECT m.*, 
               u.username as sender_name,
               CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as type
        FROM chat_messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    
    $stmt->bind_param("iiiii", $user_id, $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'message' => htmlspecialchars($row['message']),
            'type' => $row['type'],
            'sender' => htmlspecialchars($row['sender_name']),
            'time' => date('h:i A', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    $stmt->close();

} elseif ($action === 'get_conversations' && $is_admin) {
    // Get list of users who have chatted
    // This looks for unique sender_ids where receiver is Admin (1), or receiver is a client where sender was Admin
    // Simplification: Group by the 'other' person
    
    // We want latest message for each conversation
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

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        // Fetch unread count
        $partner_id = $row['chat_partner_id'];
        $unread_sql = "SELECT COUNT(*) as count FROM chat_messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
        $stmt_unread = $conn->prepare($unread_sql);
        $stmt_unread->bind_param("ii", $partner_id, $user_id);
        $stmt_unread->execute();
        $unread_res = $stmt_unread->get_result()->fetch_assoc();
        $unread_count = $unread_res['count'];
        $stmt_unread->close();

        $conversations[] = [
            'user_id' => $row['chat_partner_id'],
            'username' => htmlspecialchars($row['username']),
            'user_type' => $row['user_type'],
            'last_active' => date('M d, h:i A', strtotime($row['last_msg_time'])),
            'unread' => $unread_count
        ];
    }

    echo json_encode(['success' => true, 'conversations' => $conversations]);
    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>
