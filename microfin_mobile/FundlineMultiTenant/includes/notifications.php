<?php
session_start();
require_once '../config/db.php';
require_once 'notification_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$avatar_letter = strtoupper(substr($username, 0, 1));
$user_type = $_SESSION['user_type'];

// Fetch all notifications
$notifications = [];
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()) $notifications[] = $r;
$stmt->close();

// Mark unread as read
markAllNotificationsRead($conn, $user_id);

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Notifications - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    <style>
        .notification-item {
            transition: background-color 0.2s;
        }
        .notification-item:hover {
            background-color: var(--color-surface-hover);
        }
        .notification-item.unread {
            background-color: rgba(59, 130, 246, 0.05); /* very light blue */
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php 
        if($user_type == 'Employee') include 'admin_sidebar.php';
        else include 'user_sidebar.php'; 
        ?>
        
        <main class="main-content w-100 bg-body-tertiary min-vh-100">
            <!-- Header -->
            <?php 
             if($user_type == 'Employee') include 'admin_header.php';
             else include 'client_header.php';
            ?>
            
            <div class="content-area">
                <div class="container-fluid p-0" style="max-width: 800px; margin: 0 auto;">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h4 fw-bold mb-0">Notifications</h2>
                        <span class="text-muted small">Showing last 50</span>
                    </div>
                    
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <ul class="list-group list-group-flush">
                            <?php if(empty($notifications)): ?>
                                <li class="list-group-item p-5 text-center text-muted">
                                    <span class="material-symbols-outlined fs-1 mb-3 text-secondary opacity-50">notifications_off</span>
                                    <p class="mb-0">No notifications found</p>
                                </li>
                            <?php else: ?>
                                <?php foreach($notifications as $notif): ?>
                                    <li class="list-group-item p-0">
                                        <a href="<?php echo $notif['link'] ? htmlspecialchars($notif['link']) : '#'; ?>" class="d-flex gap-3 p-4 text-decoration-none notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                            <?php
                                                $icon = 'info';
                                                $bg_class = 'bg-primary text-primary';
                                                if(strpos($notif['notification_type'], 'Approved') !== false) { $icon = 'check_circle'; $bg_class = 'bg-success text-success'; }
                                                if(strpos($notif['notification_type'], 'Rejected') !== false) { $icon = 'cancel'; $bg_class = 'bg-danger text-danger'; }
                                                if(strpos($notif['notification_type'], 'Payment') !== false) { $icon = 'payments'; $bg_class = 'bg-success text-success'; }
                                                if(strpos($notif['notification_type'], 'Document') !== false) { $icon = 'description'; $bg_class = 'bg-warning text-warning'; }
                                            ?>
                                            <div class="<?php echo $bg_class; ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                                <span class="material-symbols-outlined fs-5"><?php echo $icon; ?></span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-0 text-dark fw-bold"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                                    <span class="text-muted small ms-2" style="font-size: 0.75rem;"><?php echo time_elapsed_string($notif['created_at']); ?></span>
                                                </div>
                                                <p class="mb-0 text-secondary"><?php echo htmlspecialchars($notif['message']); ?></p>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

