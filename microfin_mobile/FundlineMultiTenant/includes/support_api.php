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

if ($action === 'bot_auto_reply') {
    $topic = $_POST['topic'] ?? '';
    
    // Define bot responses
    $responses = [
        'apply' => "To apply for a loan, navigate to the 'Loans' section in your sidebar and click 'Apply for Loan'. You'll need to submit valid ID and income proof.",
        'requirements' => "Common requirements include: \n1. Valid Government ID\n2. Proof of Income (Payslip/ITR)\n3. Proof of Billing\n4. Co-maker (optional depending on loan amount).",
        'status' => "You can check your application status in the 'Applications' tab. If it's 'Pending', our team is still reviewing it. This usually takes 1-2 business days.",
        'payment' => "We currently accept payments via **GCash only**. \n\nPlease upload your proof of payment in the 'Payments' tab.",
        'rates' => "Our interest rates start at 3% per month, depending on the loan type and term. You can view the full schedule in the Loan Calculator.",
        'location' => "Our main office is located at: \nMarilao Branch, Bulacan\nOpen Mon-Sat, 8:00 AM - 5:00 PM.",
        'agent' => "I am connecting you to a live chat agent now. Please hold on, someone will be with you shortly. You can type your specific concern in the meantime."
    ];

    $reply = $responses[$topic] ?? "I'm sorry, I didn't understand that. You can type your question to speak with an agent.";
    
    // Insert Bot Message (Sender ID 1 = Admin)
    // We treat this as an immediate reply from "Admin"
    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $admin_id = 1; 
    $stmt->bind_param("iis", $admin_id, $user_id, $reply);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate reply']);
    }
    $stmt->close();

} elseif ($action === 'send_message') {
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

    // If client, we don't strictly filter the "other" side to just ID 1.
    // We want to see messages from ANY admin (sender) to the client (receiver),
    // and messages from client (sender) to ANY admin (receiver).
    
    // So if not admin, we just look for messages involving $user_id.
    
    if (!$is_admin) {
        $stmt = $conn->prepare("
            SELECT m.*, 
                   u.username as sender_name,
                   ur.role_name as sender_role,
                   CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as type
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.user_id
            LEFT JOIN user_roles ur ON u.role_id = ur.role_id
            WHERE m.sender_id = ? OR m.receiver_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    } else {
        // Admin logic: Show ALL messages involving the selected client
        // This allows Super Admins to see messages from other admins too
        $stmt = $conn->prepare("
            SELECT m.*, 
                   u.username as sender_name,
                   ur.role_name as sender_role,
                   CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as type
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.user_id
            LEFT JOIN user_roles ur ON u.role_id = ur.role_id
            WHERE m.sender_id = ? OR m.receiver_id = ?
            ORDER BY m.created_at ASC
        ");
        
        $stmt->bind_param("iii", $user_id, $other_user_id, $other_user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'message' => htmlspecialchars($row['message']),
            'type' => $row['type'],
            'sender' => htmlspecialchars($row['sender_name']),
            'sender_role' => htmlspecialchars($row['sender_role'] ?? 'User'),
            'time' => date('h:i A', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    $stmt->close();

} elseif ($action === 'get_conversations' && $is_admin) {
    // Get list of all client conversations
    // We group by the Client ID
    // We also want to know WHO (which admin) last replied to this client to show "Handled by X"
    
    $sql = "
        SELECT 
            CASE 
                WHEN u_sender.user_type = 'Client' THEN m.sender_id 
                ELSE m.receiver_id 
            END as chat_partner_id,
            MAX(m.created_at) as last_msg_time,
            CASE 
                WHEN u_sender.user_type = 'Client' THEN u_sender.username 
                ELSE u_receiver.username 
            END as username,
            'Client' as user_type
        FROM chat_messages m
        LEFT JOIN users u_sender ON m.sender_id = u_sender.user_id
        LEFT JOIN users u_receiver ON m.receiver_id = u_receiver.user_id
        WHERE u_sender.user_type = 'Client' OR u_receiver.user_type = 'Client'
        GROUP BY chat_partner_id, username
        ORDER BY last_msg_time DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $partner_id = $row['chat_partner_id'];
        
        // Find last ADMIN reply to this partner
        // We look for the latest message where receiver is this partner AND sender is NOT this partner (so it's an admin)
        // AND sender is NOT 1 (Bot/System default) if possible, but 1 is also an admin so we count it.
        // Actually, we want the name of the admin.
        
        $sql_handler = "
            SELECT u.username, u.user_id
            FROM chat_messages m 
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.receiver_id = ? AND m.sender_id != ?
            ORDER BY m.created_at DESC LIMIT 1
        ";
        $stmt_h = $conn->prepare($sql_handler);
        $stmt_h->bind_param("ii", $partner_id, $partner_id);
        $stmt_h->execute();
        $res_h = $stmt_h->get_result();
        
        $handled_by = null;
        $handler_id = null;
        
        if ($row_h = $res_h->fetch_assoc()) {
             $handled_by = $row_h['username'];
             $handler_id = $row_h['user_id'];
        }
        $stmt_h->close();

        // Count unread
        $unread_sql = "SELECT COUNT(*) as count FROM chat_messages m 
                       JOIN users u ON m.sender_id = u.user_id
                       WHERE m.sender_id = ? AND m.is_read = 0 AND u.user_type = 'Client'";
        $stmt_unread = $conn->prepare($unread_sql);
        $stmt_unread->bind_param("i", $partner_id);
        $stmt_unread->execute();
        $unread_res = $stmt_unread->get_result()->fetch_assoc();
        $unread_count = $unread_res['count'];
        $stmt_unread->close();

        $conversations[] = [
            'user_id' => $row['chat_partner_id'],
            'username' => htmlspecialchars($row['username']),
            'user_type' => 'Client',
            'last_active' => date('M d, h:i A', strtotime($row['last_msg_time'])),
            'unread' => $unread_count,
            'handled_by' => $handled_by, // Name of last admin who replied
            'handler_id' => $handler_id
        ];
    }

    echo json_encode(['success' => true, 'conversations' => $conversations]);
    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>

