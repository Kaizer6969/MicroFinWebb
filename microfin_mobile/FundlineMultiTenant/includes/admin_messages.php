<?php
/**
 * Admin Chat Interface
 * View and reply to client messages
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure only Admin/Super Admin/Employee can access
if ($_SESSION['user_type'] === 'Client') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_name = $_SESSION['role_name'] ?? 'Employee';
$avatar_letter = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Customer Support Chat - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <style>
        .chat-container {
            height: calc(100vh - 180px);
            min-height: 500px;
            background-color: var(--color-surface-light);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border-subtle);
            overflow: hidden;
            display: flex;
        }

        .chat-list {
            width: 300px;
            border-right: 1px solid var(--color-border-subtle);
            display: flex;
            flex-direction: column;
            background-color: var(--color-surface-light-alt);
        }

        .chat-list-header {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
        }

        .conversation-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .conversation-item:hover, .conversation-item.active {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .conversation-item.active {
            border-left: 4px solid var(--color-primary);
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--color-surface-light);
        }

        .chat-area-header {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .messages-container {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background-color: rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .dark .messages-container {
            background-color: rgba(255,255,255,0.02);
        }

        .message {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            font-size: 0.95rem;
            position: relative;
        }

        .message.sent {
            align-self: flex-end;
            background-color: var(--color-primary);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }

        .message.received {
            align-self: flex-start;
            background-color: var(--color-surface-light-alt);
            color: var(--color-text-main);
            border-bottom-left-radius: 0.25rem;
            border: 1px solid var(--color-border-subtle);
        }

        .dark .message.received {
            background-color: var(--color-surface-dark-alt);
            color: var(--color-text-dark);
            border-color: var(--color-border-dark);
        }

        .message-box {
            padding: 1rem;
            border-top: 1px solid var(--color-border-subtle);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="main-content w-100 bg-body-tertiary min-vh-100">
            <?php include 'admin_header.php'; ?>
            
            <div class="content-area">
                <div class="container-fluid p-0">
                    <h1 class="h3 fw-bold text-main mb-4">Customer Support Chat</h1>
                    
                    <div class="chat-container">
                        <!-- Left: Conversation List -->
                        <div class="chat-list">
                            <div class="chat-list-header">
                                <h6 class="fw-bold mb-0">Conversations</h6>
                            </div>
                            <div class="conversation-list" id="conversationList">
                                <!-- Loaded via JS -->
                                <div class="text-center p-4 text-muted small">Loading...</div>
                            </div>
                        </div>
                        
                        <!-- Right: Chat Area -->
                        <div class="chat-area" id="chatArea">
                            <div class="chat-area-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                        <span class="material-symbols-outlined">person</span>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0" id="chatUserName">Select a conversation</h6>
                                        <small class="text-muted" id="chatUserStatus">...</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="messages-container" id="messagesContainer">
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                                    <span class="material-symbols-outlined fs-1 mb-2">chat</span>
                                    <p>Select a conversation to start chatting</p>
                                </div>
                            </div>
                            
                            <div class="message-box">
                                <form id="adminChatForm" class="d-flex gap-2">
                                    <input type="hidden" id="currentAdminId" value="<?php echo $user_id; ?>">
                                    <input type="hidden" id="currentChatUserId" value="">
                                    <input type="text" class="form-control" id="adminChatInput" placeholder="Type your reply..." disabled autocomplete="off">
                                    <button type="submit" class="btn btn-primary" id="adminSendBtn" disabled>
                                        <span class="material-symbols-outlined">send</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const conversationList = document.getElementById('conversationList');
        const messagesContainer = document.getElementById('messagesContainer');
        const chatUserName = document.getElementById('chatUserName');
        const chatUserStatus = document.getElementById('chatUserStatus');
        const currentChatUserId = document.getElementById('currentChatUserId');
        const adminChatInput = document.getElementById('adminChatInput');
        const adminSendBtn = document.getElementById('adminSendBtn');
        const adminChatForm = document.getElementById('adminChatForm');
        
        let activeChatId = null;
        let lastMessageId = 0;
        let isPolling = false;

        // Load conversations list (Smooth update)
        async function loadConversations() {
            try {
                const response = await fetch('support_api.php?action=get_conversations');
                const data = await response.json();
                
                if (data.success) {
                    if (data.conversations.length === 0) {
                        // Only show empty state if list was already empty to avoid blink
                        if (conversationList.children.length === 0) {
                             conversationList.innerHTML = '<div class="text-center p-4 text-muted small">No active conversations</div>';
                        }
                        return;
                    }
                    
                    // Simple rebuild if empty (first load)
                    if (conversationList.innerHTML.includes('No active conversations') || conversationList.innerHTML.includes('Loading...')) {
                        conversationList.innerHTML = '';
                    }

                    // Map for diffing could be better, but simple rebuild is fast enough IF we don't clear innerHTML first? 
                    // No, keeping it simple: just rebuild. DOM is small. 
                    // To prevent blink: Build string, then replace active class.
                    
                    // Better: construct HTML string then set innerHTML. 
                    // This blinks less than clearing first. 
                    // BUT: we lose scroll position if we had one (unlikely for short list).
                    // Best: Update DOM in place.
                    
                    const newIds = new Set(data.conversations.map(c => c.user_id));
                    
                    // Remove old
                    Array.from(conversationList.children).forEach(child => {
                        const uid = child.dataset.userId;
                        if (uid && !newIds.has(parseInt(uid))) {
                            child.remove();
                        }
                    });

                    // Add/Update
                    data.conversations.forEach(conv => {
                        let div = conversationList.querySelector(`.conversation-item[data-user-id="${conv.user_id}"]`);
                        const isActive = activeChatId == conv.user_id ? 'active' : '';
                        const unreadBadge = conv.unread > 0 ? `<span class="badge bg-danger rounded-pill ms-auto">${conv.unread}</span>` : '';
                        
                        // Handler Badge logic
                        let handlerBadge = '';
                        // CURRENT ADMIN ID logic needed: We need to know 'my' ID in JS.
                        // We can get it from PHP block or a hidden input.
                        // For now assuming we check handler_id != current and handler_id != 1 (Bot)
                        
                        const myId = <?php echo $user_id; ?>; 
                        // Note: To make this robust, we inject PHP variable into JS scope at top of script, or here.
                        // Since this is inside a JS string in a PHP file, it's tricky if the JS is separate.
                        // But here it IS in PHP file. However, `loadConversations` is async and `conv` is from API.
                        // We need `myId` available. Let's add a hidden input for it.
                        
                        // Assuming hidden input exists or valid injection:
                        const myUserId = document.getElementById('currentAdminId').value;
                        
                        if (conv.handled_by && conv.handler_id != myUserId && conv.handler_id != 1) {
                            handlerBadge = `<span class="badge bg-warning text-dark me-2" style="font-size:0.6em">Handled by ${conv.handled_by}</span>`;
                        }

                        const htmlContent = `
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;color:var(--color-text-main)">
                                    ${conv.username.substring(0,1).toUpperCase()}
                                </div>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-bold text-truncate">${conv.username}</span>
                                        <small class="text-muted" style="font-size:0.7em">${conv.last_active}</small>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center text-truncate" style="max-width:140px">
                                             ${handlerBadge}
                                             <span class="text-muted small text-truncate">${conv.user_type}</span>
                                        </div>
                                        ${unreadBadge}
                                    </div>
                                </div>
                            </div>
                        `;

                        if (!div) {
                            div = document.createElement('div');
                            div.className = `conversation-item ${isActive}`;
                            div.dataset.userId = conv.user_id;
                            div.onclick = () => selectChat(conv.user_id, conv.username);
                            div.innerHTML = htmlContent;
                            conversationList.appendChild(div);
                        } else {
                            // Only update if changed (basic check by innerHTML might be expensive, so just update parts or replace)
                            // To keep it smooth, simply update className (for active) and innerHTML
                            if (div.className !== `conversation-item ${isActive}`) div.className = `conversation-item ${isActive}`;
                            // Check content diff to avoid repaint if same?
                            // For now just replace, it's fast.
                            if (div.innerHTML !== htmlContent) div.innerHTML = htmlContent;
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
            }
        }

        // Select a chat
        function selectChat(userId, username) {
            if (activeChatId === userId) return; // Prevent reload if clicking same

            activeChatId = userId;
            lastMessageId = 0; // Reset for new chat
            currentChatUserId.value = userId;
            chatUserName.textContent = username;
            chatUserStatus.textContent = 'Active now';
            
            // Enable inputs
            adminChatInput.disabled = false;
            adminSendBtn.disabled = false;
            adminChatInput.focus();
            
            // Clear messages container specifically
            messagesContainer.innerHTML = '';
            
            // Load immediately
            loadConversations(); // Updates active state
            loadMessages(userId);
        }

        // Load messages (Append only)
        async function loadMessages(userId) {
            if (!userId) return;

            try {
                const response = await fetch(`support_api.php?action=get_messages&user_id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    data.messages.forEach(msg => {
                        if (msg.id > lastMessageId) {
                            appendMessage(msg);
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        function appendMessage(msg) {
            if (document.getElementById(`msg-${msg.id}`)) return;

            const msgClass = msg.type === 'sent' ? 'sent' : 'received';
            
            const div = document.createElement('div');
            div.id = `msg-${msg.id}`;
            div.className = `message ${msgClass}`;
            
            // Build message content with role badge
            let messageContent = msg.message;
            let roleBadge = '';
            
            // Add role badge for received messages from other admins
            if (msg.type === 'received' && msg.sender_role) {
                if (msg.sender_role === 'Super Admin') {
                    roleBadge = '<span class="badge bg-danger bg-opacity-75 text-white me-2 mb-1" style="font-size: 0.65rem; padding: 0.2rem 0.5rem;">Super Admin</span><br>';
                } else if (msg.sender_role === 'Admin') {
                    roleBadge = '<span class="badge bg-primary bg-opacity-75 text-white me-2 mb-1" style="font-size: 0.65rem; padding: 0.2rem 0.5rem;">Admin</span><br>';
                }
            }
            
            div.innerHTML = `
                ${roleBadge}${messageContent}
                <span class="d-block text-end opacity-50 mt-1" style="font-size:0.7em">${msg.time}</span>
            `;
            messagesContainer.appendChild(div);
            scrollToBottom();
            
            if (msg.id > lastMessageId) {
                lastMessageId = msg.id;
            }
        }

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Send message
        async function sendMessage(text) {
            if (!activeChatId) return;

            adminChatInput.disabled = true;
            adminSendBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_id', activeChatId);
            formData.append('message', text);

            try {
                const response = await fetch('support_api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    adminChatInput.value = '';
                    loadMessages(activeChatId); 
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error sending message:', error);
            } finally {
                adminChatInput.disabled = false;
                adminSendBtn.disabled = false;
                adminChatInput.focus();
            }
        }

        adminChatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const text = adminChatInput.value.trim();
            if (text) sendMessage(text);
        });

        // Initialize
        loadConversations();
        
        // Fast Polling (1s)
        setInterval(() => {
            loadConversations();
            if (activeChatId) {
                loadMessages(activeChatId);
            }
        }, 1000);

    </script>
</body>
</html>

