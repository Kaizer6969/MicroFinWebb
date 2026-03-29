<?php
/**
 * Notification Helper Functions
 * Handles creating and managing system notifications
 */

/**
 * Create a new notification for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id Recipient User ID
 * @param string $type Notification Type (e.g. 'Loan Approved', 'Payment Received')
 * @param string $title Short title
 * @param string $message content body
 * @param string|null $link Optional URL to redirect to
 * @param string $priority 'Low', 'Medium', 'High'
 * @return bool Success status
 */
function createNotification($conn, $user_id, $type, $title, $message, $link = null, $priority = 'Medium') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, notification_type, title, message, link, priority) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Notification Prepare Failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("isssss", $user_id, $type, $title, $message, $link, $priority);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Notification Insert Failed: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsRead($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get unread count
 */
function getUnreadNotificationCount($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        $stmt->close();
        return $data['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function time_elapsed_string($datetime, $full = false) {
    if ($datetime == '0000-00-00 00:00:00' || empty($datetime)) {
        return "Unknown";
    }
    
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

